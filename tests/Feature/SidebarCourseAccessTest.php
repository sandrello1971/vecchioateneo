<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Student;
use App\Support\StudentCourseAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarCourseAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name'                 => 'Tizio ' . uniqid(),
            'email'                => 'tizio+' . uniqid() . '@example.com',
            'password'             => bcrypt('secret-pw'),
            'is_active'            => true,
            'is_demo'              => false,
            'must_change_password' => false,
        ], $attrs));
    }

    private function makeCourse(string $slug = null, int $order = 1): Course
    {
        return Course::create([
            'name'       => 'Corso ' . ($slug ?? uniqid()),
            'slug'       => $slug ?? ('corso-' . uniqid()),
            'is_active'  => true,
            'sort_order' => $order,
        ]);
    }

    private function enroll(Student $student, Course $course): void
    {
        $student->courses()->attach($course->id, [
            'enrolled_at' => now(),
            'is_active'   => true,
        ]);
    }

    private function svc(): StudentCourseAccess
    {
        return app(StudentCourseAccess::class);
    }

    public function test_enrolled_student_gets_only_enrolled(): void
    {
        $student = $this->makeStudent();
        $c1 = $this->makeCourse(null, 1);
        $c2 = $this->makeCourse(null, 2);
        $c3 = $this->makeCourse(null, 3);
        $this->makeCourse(null, 4); // not enrolled
        $this->enroll($student, $c1);
        $this->enroll($student, $c2);
        $this->enroll($student, $c3);

        $result = $this->svc()->navigableCourses($student);

        $this->assertSame(3, $result->count());
        $this->assertTrue($result->every(fn($c) => $c->access_kind === 'enrolled'));
        $this->assertEquals(
            [$c1->id, $c2->id, $c3->id],
            $result->pluck('id')->all()
        );
    }

    public function test_instructor_teaches_two_not_enrolled_plus_one_enrolled(): void
    {
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $taught1 = $this->makeCourse(null, 1);
        $taught2 = $this->makeCourse(null, 2);
        $enrolledOnly = $this->makeCourse(null, 3);
        $taught1->instructors()->attach($instructor->id);
        $taught2->instructors()->attach($instructor->id);
        $this->enroll($instructor, $enrolledOnly);

        $result = $this->svc()->navigableCourses($instructor);

        $this->assertSame(3, $result->count(), 'No doppione');
        $byId = $result->keyBy('id');
        $this->assertSame('teaching', $byId[$taught1->id]->access_kind);
        $this->assertSame('teaching', $byId[$taught2->id]->access_kind);
        $this->assertSame('enrolled', $byId[$enrolledOnly->id]->access_kind);
    }

    public function test_instructor_enrolled_in_taught_course_appears_once_as_enrolled(): void
    {
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course = $this->makeCourse();
        $course->instructors()->attach($instructor->id);
        $this->enroll($instructor, $course);

        $result = $this->svc()->navigableCourses($instructor);

        $this->assertSame(1, $result->count());
        $this->assertSame('enrolled', $result->first()->access_kind);
        $this->assertSame($course->id, $result->first()->id);
    }

    public function test_demo_returns_only_primus(): void
    {
        $demo = $this->makeStudent(['is_demo' => true]);
        $primus = $this->makeCourse('primus', 1);
        $other  = $this->makeCourse('other', 2);
        $this->enroll($demo, $primus);
        $this->enroll($demo, $other); // attached but should be filtered out

        $result = $this->svc()->navigableCourses($demo);

        $this->assertSame(1, $result->count());
        $this->assertSame('primus', $result->first()->slug);
        $this->assertSame('enrolled', $result->first()->access_kind);
    }

    public function test_auto_enroll_returns_all_active_courses(): void
    {
        $student = $this->makeStudent(['auto_enroll_all_courses' => true]);
        $a = $this->makeCourse(null, 1);
        $b = $this->makeCourse(null, 2);
        $c = $this->makeCourse(null, 3);
        // Inactive course should be excluded
        Course::create([
            'name' => 'Disabled', 'slug' => 'disabled-' . uniqid(),
            'is_active' => false, 'sort_order' => 99,
        ]);

        $result = $this->svc()->navigableCourses($student);

        $this->assertSame(3, $result->count());
        $this->assertTrue($result->every(fn($c) => $c->access_kind === 'enrolled'));
        $this->assertTrue($result->every(fn($c) => $c->is_active === true));
    }

    public function test_instructor_with_auto_enroll_has_no_teaching_cards(): void
    {
        $instructor = $this->makeStudent([
            'role' => 'instructor',
            'auto_enroll_all_courses' => true,
        ]);
        $course = $this->makeCourse();
        $course->instructors()->attach($instructor->id);

        $result = $this->svc()->navigableCourses($instructor);

        $this->assertSame(1, $result->count());
        $this->assertSame('enrolled', $result->first()->access_kind,
            'Auto-enroll instructor accesses everything as enrolled, no teaching mode');
    }

    public function test_dashboard_render_shows_teaching_course_in_both_sidebar_and_main(): void
    {
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course = $this->makeCourse('teaching-only-' . uniqid(), 1);
        $course->name = 'Corso Solo Insegnato';
        $course->save();
        $course->instructors()->attach($instructor->id);

        $response = $this->withSession([
            'student_id'    => $instructor->id,
            'student_email' => $instructor->email,
            'student_name'  => $instructor->name,
        ])->get(route('student.dashboard'));

        $response->assertOk();
        $html = $response->getContent();

        // Once in sidebar (with "insegni" badge), once in main dashboard area
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'Corso Solo Insegnato'),
            'Course must appear in both sidebar and dashboard area'
        );
        $this->assertStringContainsString('insegni', strtolower($html));
    }
}
