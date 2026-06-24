<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorTeachingModeTest extends TestCase
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

    private function makeCourse(): Course
    {
        return Course::create([
            'name'       => 'Corso ' . uniqid(),
            'slug'       => 'corso-' . uniqid(),
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function makeModule(Course $course, int $order = 1): Module
    {
        return Module::create([
            'course_id'  => $course->id,
            'title'      => 'Modulo ' . $order,
            'sort_order' => $order,
            'is_active'  => true,
        ]);
    }

    private function actingAsStudent(Student $student): self
    {
        return $this->withSession([
            'student_id'    => $student->id,
            'student_email' => $student->email,
            'student_name'  => $student->name,
        ]);
    }

    private function makeTeachingInstructor(Course $course): Student
    {
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course->instructors()->attach($instructor->id);
        return $instructor;
    }

    // ============================================================
    // Dashboard
    // ============================================================

    public function test_dashboard_shows_taught_course_with_teaching_badge(): void
    {
        $course = $this->makeCourse();
        $course->name = 'Corso Speciale Docenza';
        $course->save();
        $instructor = $this->makeTeachingInstructor($course);

        $this->actingAsStudent($instructor)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Corso Speciale Docenza')
            ->assertSee('Insegni questo corso');
    }

    // ============================================================
    // Access
    // ============================================================

    public function test_teaching_instructor_can_open_course_show(): void
    {
        $course = $this->makeCourse();
        $instructor = $this->makeTeachingInstructor($course);

        $this->actingAsStudent($instructor)
            ->get(route('student.course.show', $course))
            ->assertOk()
            ->assertSee('Modalit'); // banner "Modalità docenza"
    }

    public function test_non_teaching_non_enrolled_instructor_gets_403(): void
    {
        $course = $this->makeCourse();
        $instructor = $this->makeStudent(['role' => 'instructor']); // NOT attached

        $this->actingAsStudent($instructor)
            ->get(route('student.course.show', $course))
            ->assertForbidden();
    }

    // ============================================================
    // No progress writes in teaching mode
    // ============================================================

    public function test_opening_module_in_teaching_mode_creates_no_progress_row(): void
    {
        $course = $this->makeCourse();
        $module = $this->makeModule($course);
        $instructor = $this->makeTeachingInstructor($course);

        $countBefore = StudentModuleProgress::count();

        $this->actingAsStudent($instructor)
            ->get(route('student.module.show', [$course, $module]))
            ->assertOk();

        $this->assertSame($countBefore, StudentModuleProgress::count(),
            'No StudentModuleProgress row must be created when opening a module in teaching mode');
    }

    public function test_complete_module_in_teaching_mode_creates_no_progress_row(): void
    {
        $course = $this->makeCourse();
        $module = $this->makeModule($course);
        $instructor = $this->makeTeachingInstructor($course);

        $countBefore = StudentModuleProgress::count();

        $this->actingAsStudent($instructor)
            ->post(route('student.module.complete', [$course, $module]))
            ->assertRedirect();

        $this->assertSame($countBefore, StudentModuleProgress::count(),
            'completeModule in teaching mode must not create any progress row');
    }

    // ============================================================
    // No quiz attempts / no certificate in teaching mode
    // ============================================================

    public function test_quiz_start_in_teaching_mode_creates_no_attempt(): void
    {
        $course = $this->makeCourse();
        $module = $this->makeModule($course);
        $quiz = Quiz::create([
            'course_id'      => $course->id,
            'module_id'      => $module->id,
            'title'          => 'Quiz modulo',
            'passing_score'  => 60,
            'is_active'      => true,
        ]);
        $instructor = $this->makeTeachingInstructor($course);

        $this->actingAsStudent($instructor)
            ->post(route('student.quiz.start', $quiz))
            ->assertForbidden();

        $this->assertSame(0, QuizAttempt::count(),
            'Quiz start in teaching mode must not persist a QuizAttempt');
    }

    public function test_quiz_submit_in_teaching_mode_creates_no_attempt_and_no_certificate(): void
    {
        $course = $this->makeCourse();
        $quiz = Quiz::create([
            'course_id'     => $course->id,
            'title'         => 'Quiz finale',
            'passing_score' => 60,
            'is_active'     => true,
        ]);
        $instructor = $this->makeTeachingInstructor($course);

        $this->actingAsStudent($instructor)
            ->post(route('student.quiz.submit', $quiz), ['answers' => []])
            ->assertForbidden();

        $this->assertSame(0, QuizAttempt::count());
        $this->assertSame(0, Certificate::count());
    }

    // ============================================================
    // Enrolled-instructor regression: still a full discente
    // ============================================================

    public function test_enrolled_instructor_is_not_in_teaching_mode(): void
    {
        $course = $this->makeCourse();
        $module = $this->makeModule($course);
        $instructor = $this->makeTeachingInstructor($course);

        // Also enroll as student
        $instructor->courses()->attach($course->id, [
            'enrolled_at' => now(),
            'is_active'   => true,
        ]);

        $this->actingAsStudent($instructor)
            ->get(route('student.module.show', [$course, $module]))
            ->assertOk();

        $this->assertSame(1, StudentModuleProgress::where('student_id', $instructor->id)
            ->where('module_id', $module->id)
            ->count(),
            'Enrolled instructor must get a real progress row (no teaching mode)');
    }

    // ============================================================
    // Auto-enroll instructor regression
    // ============================================================

    public function test_auto_enroll_instructor_is_not_in_teaching_mode(): void
    {
        $course = $this->makeCourse();
        $module = $this->makeModule($course);
        $instructor = $this->makeStudent([
            'role' => 'instructor',
            'auto_enroll_all_courses' => true,
        ]);
        $course->instructors()->attach($instructor->id);

        $this->actingAsStudent($instructor)
            ->get(route('student.module.show', [$course, $module]))
            ->assertOk();

        $this->assertSame(1, StudentModuleProgress::where('student_id', $instructor->id)
            ->where('module_id', $module->id)
            ->count());
    }

    // ============================================================
    // Plain student regression
    // ============================================================

    public function test_plain_enrolled_student_progress_still_works(): void
    {
        $course = $this->makeCourse();
        $module = $this->makeModule($course);
        $student = $this->makeStudent();
        $student->courses()->attach($course->id, [
            'enrolled_at' => now(),
            'is_active'   => true,
        ]);

        $this->actingAsStudent($student)
            ->get(route('student.module.show', [$course, $module]))
            ->assertOk();

        $this->assertSame(1, StudentModuleProgress::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->count());
    }

    // ============================================================
    // InstructorMaterialController scoping
    // ============================================================

    public function test_teaching_instructor_can_open_instructor_only_material(): void
    {
        $course = $this->makeCourse();
        $material = Material::create([
            'course_id'          => $course->id,
            'title'              => 'Manuale formatore',
            'is_instructor_only' => true,
            'sort_order'         => 1,
        ]);
        $instructor = $this->makeTeachingInstructor($course);

        $this->actingAsStudent($instructor)
            ->get(route('student.instructor.material.show', [$course->slug, $material]))
            ->assertOk();
    }

    public function test_instructor_403_on_instructor_only_material_of_course_not_taught(): void
    {
        $taughtCourse = $this->makeCourse();
        $otherCourse  = $this->makeCourse();
        $material = Material::create([
            'course_id'          => $otherCourse->id,
            'title'              => 'Manuale formatore altro corso',
            'is_instructor_only' => true,
            'sort_order'         => 1,
        ]);
        $instructor = $this->makeTeachingInstructor($taughtCourse);

        $this->actingAsStudent($instructor)
            ->get(route('student.instructor.material.show', [$otherCourse->slug, $material]))
            ->assertForbidden();
    }
}
