<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Support\ExamState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinervaExamLockTest extends TestCase
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

    private function makeExamQuiz(Course $course): Quiz
    {
        return Quiz::create([
            'course_id' => $course->id, 'title' => 'Esame',
            'passing_score' => 60, 'is_active' => true,
        ]);
    }

    private function actingAsStudent(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name,
        ]);
    }

    private function startExam(Student $s, Course $c): QuizAttempt
    {
        $quiz = $this->makeExamQuiz($c);
        return QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $s->id,
            'started_at' => now(), 'attempt_number' => 1,
        ]);
    }

    public function test_minerva_ask_returns_423_during_active_exam(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $this->startExam($student, $course);

        $this->actingAsStudent($student)
            ->postJson(route('student.minerva.ask'), ['question' => 'Cosa è X?'])
            ->assertStatus(423)
            ->assertJson(['exam_lock' => true]);
    }

    public function test_chat_send_message_returns_423_during_active_exam(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);

        $conv = ChatConversation::create([
            'student_id' => $student->id, 'course_id' => $course->id,
            'is_active' => true, 'title' => 'Chat',
        ]);

        $this->startExam($student, $course);

        $this->actingAsStudent($student)
            ->postJson(route('student.chat.message'), [
                'conversation_id' => $conv->id,
                'message' => 'Domanda',
            ])
            ->assertStatus(423);
    }

    public function test_minerva_ask_works_when_no_active_exam(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);

        $resp = $this->actingAsStudent($student)
            ->postJson(route('student.minerva.ask'), ['question' => 'Test']);

        $this->assertNotSame(423, $resp->status(),
            'Without active exam, minerva.ask must not return 423');
    }

    public function test_exam_state_service_detects_active_exam(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $svc = app(ExamState::class);

        $this->assertFalse($svc->hasActiveExam($student->id));
        $this->startExam($student, $course);
        $this->assertTrue($svc->hasActiveExam($student->id));
    }

    public function test_completed_exam_attempt_does_not_lock(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $quiz = $this->makeExamQuiz($course);
        QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now()->subHour(), 'completed_at' => now(),
            'score' => 80, 'passed' => true, 'attempt_number' => 1,
        ]);

        $this->assertFalse(app(ExamState::class)->hasActiveExam($student->id));
    }

    public function test_module_quiz_attempt_does_not_count_as_exam_lock(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        $module = \App\Models\Module::create([
            'course_id' => $course->id, 'title' => 'M1', 'sort_order' => 1, 'is_active' => true,
        ]);
        $quiz = Quiz::create([
            'course_id' => $course->id, 'module_id' => $module->id,
            'title' => 'Q modulo', 'passing_score' => 60, 'is_active' => true,
        ]);
        QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => now(), 'attempt_number' => 1,
        ]);

        $this->assertFalse(app(ExamState::class)->hasActiveExam($student->id),
            'A module-quiz attempt must NOT trigger exam lock');
    }
}
