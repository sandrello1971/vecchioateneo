<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Student;
use App\Services\InstructorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstructorCourseTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name'                 => 'Mario Rossi',
            'email'                => 'mario+' . uniqid() . '@example.com',
            'password'             => bcrypt('secret-pw'),
            'is_active'            => true,
            'is_demo'              => false,
            'must_change_password' => false,
        ], $attrs));
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'name'       => 'Corso ' . uniqid(),
            'slug'       => 'corso-' . uniqid(),
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function actingAsAdmin(): self
    {
        return $this->withSession([
            'admin_logged_in' => true,
            'admin_email'     => 'admin@example.com',
        ]);
    }

    // ============================================================
    // Resolver (unit-style)
    // ============================================================

    public function test_resolver_zero_instructors_returns_null(): void
    {
        $course = $this->makeCourse();

        $this->assertNull(app(InstructorResolver::class)->resolveForCourse($course->id, null));
        $this->assertNull(app(InstructorResolver::class)->resolveForCourse($course->id, 'irrelevant'));
    }

    public function test_resolver_one_instructor_autoassigns(): void
    {
        $course = $this->makeCourse();
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach($instructor->id);

        $this->assertSame(
            $instructor->id,
            app(InstructorResolver::class)->resolveForCourse($course->id, null)
        );

        $other = $this->makeStudent(['role' => 'instructor']);
        $this->assertSame(
            $instructor->id,
            app(InstructorResolver::class)->resolveForCourse($course->id, $other->id),
            'With 1 instructor the requested value must be ignored'
        );
    }

    public function test_resolver_multiple_instructors_no_choice_throws(): void
    {
        $course = $this->makeCourse();
        $a = $this->makeStudent(['role' => 'instructor']);
        $b = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach([$a->id, $b->id]);

        $this->expectException(ValidationException::class);
        app(InstructorResolver::class)->resolveForCourse($course->id, null);
    }

    public function test_resolver_multiple_instructors_invalid_choice_throws(): void
    {
        $course = $this->makeCourse();
        $a = $this->makeStudent(['role' => 'instructor']);
        $b = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach([$a->id, $b->id]);

        $stranger = $this->makeStudent(['role' => 'instructor']);

        $this->expectException(ValidationException::class);
        app(InstructorResolver::class)->resolveForCourse($course->id, $stranger->id);
    }

    public function test_resolver_multiple_instructors_valid_choice(): void
    {
        $course = $this->makeCourse();
        $a = $this->makeStudent(['role' => 'instructor']);
        $b = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach([$a->id, $b->id]);

        $this->assertSame(
            $b->id,
            app(InstructorResolver::class)->resolveForCourse($course->id, $b->id)
        );
    }

    // ============================================================
    // Course ↔ Instructor association
    // ============================================================

    public function test_instructor_can_be_attached_to_course(): void
    {
        $course = $this->makeCourse();
        $instructor = $this->makeStudent(['role' => 'instructor']);

        $course->instructors()->attach($instructor->id);

        $this->assertTrue($course->fresh()->instructors->contains($instructor->id));
        $this->assertTrue($instructor->fresh()->taughtCourses->contains($course->id));
    }

    // ============================================================
    // Admin list separation
    // ============================================================

    public function test_admin_students_list_excludes_instructors(): void
    {
        $student = $this->makeStudent(['name' => 'Discente Uno']);
        $instructor = $this->makeStudent(['name' => 'Formatore Uno', 'role' => 'instructor']);

        $this->actingAsAdmin()
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('Discente Uno')
            ->assertDontSee('Formatore Uno');
    }

    public function test_admin_instructors_list_shows_only_instructors(): void
    {
        $student = $this->makeStudent(['name' => 'Discente Due']);
        $instructor = $this->makeStudent(['name' => 'Formatore Due', 'role' => 'instructor']);

        $this->actingAsAdmin()
            ->get(route('admin.instructors.index'))
            ->assertOk()
            ->assertSee('Formatore Due')
            ->assertDontSee('Discente Due');
    }

    // ============================================================
    // Enrollment flows through assignCourse
    // ============================================================

    public function test_assign_course_with_zero_instructors_sets_null(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();

        $this->actingAsAdmin()
            ->post(route('admin.students.assign-course', $student), [
                'course_id' => $course->id,
            ])
            ->assertRedirect();

        $row = DB::table('student_course')
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->instructor_id);
    }

    public function test_assign_course_with_one_instructor_auto_assigns(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach($instructor->id);

        $this->actingAsAdmin()
            ->post(route('admin.students.assign-course', $student), [
                'course_id' => $course->id,
            ])
            ->assertRedirect();

        $row = DB::table('student_course')
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertSame($instructor->id, $row->instructor_id);
    }

    public function test_assign_course_with_multiple_instructors_no_choice_fails(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $a = $this->makeStudent(['role' => 'instructor']);
        $b = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach([$a->id, $b->id]);

        $this->actingAsAdmin()
            ->from(route('admin.students.edit', $student))
            ->post(route('admin.students.assign-course', $student), [
                'course_id' => $course->id,
            ])
            ->assertSessionHasErrors('instructor_id');

        $this->assertSame(0, DB::table('student_course')
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->count());
    }

    public function test_assign_course_with_multiple_instructors_valid_choice(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $a = $this->makeStudent(['role' => 'instructor']);
        $b = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach([$a->id, $b->id]);

        $this->actingAsAdmin()
            ->post(route('admin.students.assign-course', $student), [
                'course_id'     => $course->id,
                'instructor_id' => $b->id,
            ])
            ->assertRedirect();

        $row = DB::table('student_course')
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertSame($b->id, $row->instructor_id);
    }

    public function test_assign_course_with_instructor_not_in_course_fails(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $a = $this->makeStudent(['role' => 'instructor']);
        $b = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach([$a->id, $b->id]);

        $stranger = $this->makeStudent(['role' => 'instructor']);

        $this->actingAsAdmin()
            ->from(route('admin.students.edit', $student))
            ->post(route('admin.students.assign-course', $student), [
                'course_id'     => $course->id,
                'instructor_id' => $stranger->id,
            ])
            ->assertSessionHasErrors('instructor_id');
    }

    // ============================================================
    // Detach orphan cleanup
    // ============================================================

    public function test_detach_instructor_clears_orphan_student_assignments(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach($instructor->id);

        $student->courses()->attach($course->id, [
            'enrolled_at'   => now(),
            'is_active'     => true,
            'instructor_id' => $instructor->id,
        ]);

        $this->actingAsAdmin()
            ->delete(route('admin.instructors.detach-course', [$instructor, $course]))
            ->assertRedirect();

        $row = DB::table('student_course')
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->instructor_id, 'Orphan student_course.instructor_id must be cleared');
        $this->assertSame(0, DB::table('course_instructor')
            ->where('course_id', $course->id)
            ->where('instructor_id', $instructor->id)
            ->count());
    }

    public function test_attach_course_requires_instructor_role(): void
    {
        $notInstructor = $this->makeStudent(['role' => 'student']);
        $course = $this->makeCourse();

        $this->actingAsAdmin()
            ->post(route('admin.instructors.attach-course', $notInstructor), [
                'course_id' => $course->id,
            ])
            ->assertForbidden();
    }
}
