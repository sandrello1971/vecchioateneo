<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Support\ExamState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamAttemptCapTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Tizio ' . uniqid(),
            'email' => 'tizio+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'is_active' => true, 'is_demo' => false, 'must_change_password' => false,
        ], $attrs));
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'name' => 'Corso ' . uniqid(), 'slug' => 'corso-' . uniqid(),
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function makeExamQuiz(Course $course, ?int $maxAttempts): Quiz
    {
        return Quiz::create([
            'course_id'     => $course->id,
            'title'         => 'Esame',
            'passing_score' => 60,
            'max_attempts'  => $maxAttempts,
            'is_active'     => true,
        ]);
    }

    private function makeModuleQuiz(Course $course, ?int $maxAttempts): Quiz
    {
        $module = Module::create([
            'course_id' => $course->id, 'title' => 'M1',
            'sort_order' => 1, 'is_active' => true,
        ]);
        return Quiz::create([
            'course_id' => $course->id, 'module_id' => $module->id,
            'title' => 'Quiz modulo', 'passing_score' => 60,
            'max_attempts' => $maxAttempts, 'is_active' => true,
        ]);
    }

    private function actingAsStudent(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name,
        ]);
    }

    private function fillCompletedAttempt(Quiz $quiz, Student $student, bool $passed = false, bool $abandoned = false): QuizAttempt
    {
        return QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now()->subMinutes(5), 'completed_at' => now(),
            'score' => $passed ? 80 : 0, 'passed' => $passed,
            'abandoned' => $abandoned, 'attempt_number' => 1,
        ]);
    }

    public function test_cap_blocks_after_max_completed_attempts(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeExamQuiz($course, 2);

        // 2 completed (failed) → next start must be 403
        $this->fillCompletedAttempt($quiz, $student);
        $this->fillCompletedAttempt($quiz, $student);

        $this->actingAsStudent($student)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertStatus(403)
            ->assertJson(['attempts_exhausted' => true]);
    }

    public function test_abandoned_attempt_consumes_one_slot(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeExamQuiz($course, 1);

        // Un tentativo ABBANDONATO (già chiuso da abandon()/reaper) consuma uno slot.
        // Semantica nuova: start() su un tentativo APERTO lo RIPRENDE (refresh); qui
        // il tentativo è già completato-abbandonato → conta verso il cap.
        QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now()->subMinutes(10), 'completed_at' => now()->subMinutes(8),
            'score' => 0, 'passed' => false, 'abandoned' => true, 'attempt_number' => 1,
        ]);

        // Nessuno slot residuo (max=1, già 1 abbandonato) → nuovo start bloccato.
        $this->actingAsStudent($student)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertStatus(403)
            ->assertJson(['attempts_exhausted' => true]);

        // Nessun nuovo tentativo creato.
        $this->assertSame(1, QuizAttempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_zero_or_null_max_means_unlimited(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeExamQuiz($course, null);

        // 5 completed attempts, still allows another start
        for ($i = 0; $i < 5; $i++) $this->fillCompletedAttempt($quiz, $student);

        $this->actingAsStudent($student)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertOk();
    }

    public function test_module_quiz_has_no_cap(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeModuleQuiz($course, 1); // cap=1 but should be ignored

        $this->fillCompletedAttempt($quiz, $student);

        $this->actingAsStudent($student)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertOk();
    }

    public function test_already_passed_returns_409_not_403(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeExamQuiz($course, 2);

        $passed = $this->fillCompletedAttempt($quiz, $student, passed: true);
        Certificate::create([
            'student_id' => $student->id,
            'course_id'  => $course->id,
            'quiz_attempt_id' => $passed->id,
            'code'       => 'TEST-' . uniqid(),
            'score'      => 80,
            'issued_at'  => now(),
            'certification_name' => 'Test cert',
        ]);

        $this->actingAsStudent($student)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertStatus(409)
            ->assertJson(['already_passed' => true]);
    }

    public function test_demo_bypasses_cap(): void
    {
        $demo = $this->makeStudent(['is_demo' => true]);
        $course = $this->makeCourse();
        $quiz = $this->makeExamQuiz($course, 1);

        $this->fillCompletedAttempt($quiz, $demo);
        $this->fillCompletedAttempt($quiz, $demo);

        $resp = $this->actingAsStudent($demo)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertOk();
        $this->assertStringStartsWith('demo-', $resp->json('attempt_id'));
    }

    public function test_admin_grant_extends_cap(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeExamQuiz($course, 1);
        $this->fillCompletedAttempt($quiz, $student);

        // Cap exhausted
        $this->actingAsStudent($student)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertStatus(403);

        // Admin grants +1
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@example.com'])
            ->post(route('admin.quizzes.grant-attempt', $quiz->id), [
                'student_id'     => $student->id,
                'extra_attempts' => 1,
                'reason'         => 'caduta rete',
            ])
            ->assertRedirect();

        // Now allowed
        $this->actingAsStudent($student)
            ->postJson(route('student.quiz.start', $quiz))
            ->assertOk();
    }

    public function test_double_grant_increments_does_not_duplicate(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $quiz = $this->makeExamQuiz($course, 1);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@example.com'])
            ->post(route('admin.quizzes.grant-attempt', $quiz->id), [
                'student_id' => $student->id, 'extra_attempts' => 2,
            ]);
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@example.com'])
            ->post(route('admin.quizzes.grant-attempt', $quiz->id), [
                'student_id' => $student->id, 'extra_attempts' => 3,
            ]);

        $this->assertSame(1, DB::table('exam_attempt_grants')
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->count(), 'Must be a single row per (quiz,student)');

        $svc = app(ExamState::class);
        $this->assertSame(1 + 2 + 3, $svc->effectiveMaxAttempts($quiz, $student->id),
            'Base cap (1) + 2 + 3 = 6');
    }

    public function test_effective_max_returns_null_for_unlimited(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $quizUnlimited = $this->makeExamQuiz($course, null);
        $quizZero = $this->makeExamQuiz($course, 0);

        $svc = app(ExamState::class);
        $this->assertNull($svc->effectiveMaxAttempts($quizUnlimited, $student->id));
        $this->assertNull($svc->effectiveMaxAttempts($quizZero, $student->id),
            '0 max_attempts must be treated as unlimited (null)');
    }

    public function test_admin_edit_view_renders(): void
    {
        $course = $this->makeCourse();
        $quiz = $this->makeExamQuiz($course, 3);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@example.com'])
            ->get(route('admin.quizzes.edit', $quiz->id))
            ->assertOk()
            ->assertSee($quiz->title)
            ->assertSee('Salva modifiche');
    }

    public function test_admin_update_persists_max_attempts(): void
    {
        $course = $this->makeCourse();
        $quiz = $this->makeExamQuiz($course, 3);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@example.com'])
            ->put(route('admin.quizzes.update', $quiz->id), [
                'title'         => $quiz->title,
                'course_id'     => $course->id,
                'passing_score' => 60,
                'max_attempts'  => 7,
                'is_active'     => '1',
            ])
            ->assertRedirect();

        $this->assertSame(7, (int) $quiz->fresh()->max_attempts);
    }

    public function test_admin_update_without_optional_nullable_fields_does_not_500(): void
    {
        $course = $this->makeCourse();
        $quiz = $this->makeExamQuiz($course, 3);

        // Update con SOLO i campi required: time_limit_minutes, max_attempts e
        // course_id ASSENTI dalla request. Regressione del bug "Undefined array
        // key" in QuizController@update (500 per l'admin, dal 20/05): i campi
        // nullable mancanti non compaiono nei dati validati.
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@example.com'])
            ->put(route('admin.quizzes.update', $quiz->id), [
                'title'         => $quiz->title,
                'passing_score' => 60,
            ])
            ->assertRedirect();

        $fresh = $quiz->fresh();
        $this->assertNull($fresh->time_limit_minutes);
        $this->assertNull($fresh->max_attempts);
        $this->assertNull($fresh->course_id);
    }

    public function test_grant_attempt_rejected_on_non_exam_quiz(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $moduleQuiz = $this->makeModuleQuiz($course, 1);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@example.com'])
            ->post(route('admin.quizzes.grant-attempt', $moduleQuiz->id), [
                'student_id' => $student->id, 'extra_attempts' => 1,
            ])
            ->assertStatus(422);
    }
}
