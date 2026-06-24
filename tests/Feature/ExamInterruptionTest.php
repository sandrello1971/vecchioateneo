<?php

namespace Tests\Feature;

use App\Console\Commands\FailStaleExamAttempts;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamInterruptionTest extends TestCase
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
            'name' => 'Corso ' . uniqid(),
            'slug' => 'corso-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function makeExamQuiz(Course $course, ?int $timeLimit = null): Quiz
    {
        return Quiz::create([
            'course_id'      => $course->id,
            'title'          => 'Esame finale',
            'passing_score'  => 60,
            'time_limit_minutes' => $timeLimit,
            'is_active'      => true,
        ]);
    }

    private function makeModuleQuiz(Course $course): Quiz
    {
        $module = Module::create([
            'course_id' => $course->id, 'title' => 'M1',
            'sort_order' => 1, 'is_active' => true,
        ]);
        return Quiz::create([
            'course_id'     => $course->id,
            'module_id'     => $module->id,
            'title'         => 'Quiz modulo',
            'passing_score' => 60,
            'is_active'     => true,
        ]);
    }

    private function actingAsStudent(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name,
        ]);
    }

    public function test_start_with_open_attempt_resumes_it(): void
    {
        // Nuova semantica unificata: start() su un tentativo già aperto lo RIPRENDE
        // (refresh tecnico), NON lo fallisce e non ne crea un altro. Il force-fail
        // avviene solo su abbandono vero (abandon beacon) o reaper.
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeExamQuiz($course);

        $old = QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now()->subMinutes(10), 'attempt_number' => 1,
        ]);

        $res = $this->actingAsStudent($student)
            ->post(route('student.quiz.start', $quiz))
            ->assertOk();

        $this->assertSame($old->id, $res->json('attempt_id'), 'start() deve riprendere il tentativo aperto.');

        $old->refresh();
        $this->assertNull($old->completed_at, 'Il tentativo aperto NON deve essere fallito da un refresh.');
        $this->assertFalse((bool) $old->abandoned);
        $this->assertSame(1, QuizAttempt::where('quiz_id', $quiz->id)->count(), 'Nessun nuovo tentativo.');
    }

    public function test_abandon_closes_active_attempt_then_idempotent(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeExamQuiz($course);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now()->subMinutes(3), 'attempt_number' => 1,
        ]);

        $this->actingAsStudent($student)
            ->post(route('student.quiz.abandon', $quiz), ['_token' => csrf_token()])
            ->assertNoContent();

        $attempt->refresh();
        $this->assertTrue((bool) $attempt->abandoned);
        $this->assertNotNull($attempt->completed_at);
        $this->assertSame(0, (int) $attempt->score);

        // Idempotent: second call no-op
        $this->actingAsStudent($student)
            ->post(route('student.quiz.abandon', $quiz), ['_token' => csrf_token()])
            ->assertNoContent();
        $this->assertSame(1, QuizAttempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_abandon_fails_module_quiz_attempt(): void
    {
        // Nuova semantica unificata: l'abbandono vero force-failla il tentativo per
        // TUTTI i quiz (formativi ed esami), non solo per gli esami.
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quiz = $this->makeModuleQuiz($course);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now(), 'attempt_number' => 1,
        ]);

        $this->actingAsStudent($student)
            ->post(route('student.quiz.abandon', $quiz), ['_token' => csrf_token()])
            ->assertNoContent();

        $attempt->refresh();
        $this->assertNotNull($attempt->completed_at, 'Abbandono vero: il tentativo formativo deve essere chiuso.');
        $this->assertTrue((bool) $attempt->abandoned);
    }

    public function test_reaper_fails_stale_exam_attempt(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $quizShort = $this->makeExamQuiz($course, 30);  // 30 min limit
        $quizLong  = $this->makeExamQuiz($course, 30);

        // Stale: started 60 min ago, limit 30 min → should be reaped
        $stale = QuizAttempt::create([
            'quiz_id' => $quizShort->id, 'student_id' => $student->id,
            'started_at' => now()->subMinutes(60), 'attempt_number' => 1,
        ]);
        // Fresh: started 5 min ago, limit 30 min → intact
        $fresh = QuizAttempt::create([
            'quiz_id' => $quizLong->id, 'student_id' => $student->id,
            'started_at' => now()->subMinutes(5), 'attempt_number' => 1,
        ]);

        $this->artisan('exams:fail-stale')->assertSuccessful();

        $stale->refresh();
        $this->assertTrue((bool) $stale->abandoned);
        $this->assertNotNull($stale->completed_at);

        $fresh->refresh();
        $this->assertNull($fresh->completed_at);
        $this->assertFalse((bool) $fresh->abandoned);
    }

    public function test_reaper_skips_module_quiz_attempts(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $quiz = $this->makeModuleQuiz($course);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now()->subHours(24), 'attempt_number' => 1,
        ]);

        $this->artisan('exams:fail-stale')->assertSuccessful();
        $attempt->refresh();
        $this->assertNull($attempt->completed_at, 'Reaper must NOT touch module quizzes');
    }

    public function test_demo_student_start_branch_unchanged(): void
    {
        $demo = $this->makeStudent(['is_demo' => true]);
        $course = $this->makeCourse();
        $quiz = $this->makeExamQuiz($course);

        // Demo start returns mock attempt_id without DB write
        $resp = $this->actingAsStudent($demo)
            ->post(route('student.quiz.start', $quiz))
            ->assertOk();
        $this->assertStringStartsWith('demo-', $resp->json('attempt_id'));
        $this->assertSame(0, QuizAttempt::count(), 'Demo must not create any QuizAttempt');
    }
}
