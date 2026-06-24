<?php

namespace Tests\Feature\Schola;

use App\Jobs\StudentGenerateArtifactJob;
use App\Models\ArtifactPublication;
use App\Models\ClassStudent;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGeneratedArtifact;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\Topic;
use App\Services\MindMapGenerationService;
use App\Services\QuizGeneratorService;
use App\Services\Schola\ClassSignalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Fase 3 (P20c) — auto-generazione studente DALLA lezione: binding, rate limit,
// gate flag, privacy (artefatto privato, escluso da cruscotto), regressione fetta 1.
class LessonStudentGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key']);
    }

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolClass(Student $teacher): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => '3A',
            'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $class, Student $s, string $status = 'active'): void
    {
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null]);
    }

    private function lesson(Student $prof): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'Rivoluzione', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause',
            'position' => 0, 'generation_status' => 'ready', 'content' => 'La rivoluzione francese del 1789 e le sue cause economiche e sociali.']);
    }

    private function publishLesson(Lesson $lesson, SchoolClass $class, bool $canGenerate = true): LessonPublication
    {
        return LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => $canGenerate, 'rag_status' => 'ready', 'published_at' => now()]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    private function fakeQuizResponse(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['questions' => [
                ['question' => 'Quando inizia?', 'options' => ['1789', '1815', '1848', '1914'],
                 'correct_answer' => '1789', 'explanation' => 'Nel 1789.'],
            ]])]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200)]);
    }

    // ===== Gate flag + tenancy =====

    public function test_generation_blocked_when_flag_off(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $this->publishLesson($lesson, $class, canGenerate: false);

        $this->asUser($student)->post(route('student.classes.lesson.generate', [$class, $lesson]), ['type' => 'quiz'])
            ->assertForbidden();
        $this->assertSame(0, StudentGeneratedArtifact::count());
        Bus::assertNotDispatchedAfterResponse(StudentGenerateArtifactJob::class);
    }

    public function test_generation_requires_published_and_active_enrollment(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $lesson = $this->lesson($prof);

        // Non pubblicata su questa classe → 403.
        $member = $this->student();
        $this->enroll($class, $member);
        $this->asUser($member)->post(route('student.classes.lesson.generate', [$class, $lesson]), ['type' => 'quiz'])
            ->assertForbidden();

        // Pubblicata, ma iscrizione non attiva → 403.
        $this->publishLesson($lesson, $class);
        $pending = $this->student();
        $this->enroll($class, $pending, 'pending');
        $this->asUser($pending)->post(route('student.classes.lesson.generate', [$class, $lesson]), ['type' => 'quiz'])
            ->assertForbidden();

        Bus::assertNotDispatchedAfterResponse(StudentGenerateArtifactJob::class);
    }

    public function test_generation_dispatches_with_lesson_binding(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $pub = $this->publishLesson($lesson, $class);

        $this->asUser($student)->post(route('student.classes.lesson.generate', [$class, $lesson]), ['type' => 'quiz', 'num_questions' => 5])
            ->assertRedirect(route('student.classes.lesson.show', [$class, $lesson]));

        $gen = StudentGeneratedArtifact::first();
        $this->assertNotNull($gen);
        $this->assertSame($pub->id, $gen->lesson_publication_id);
        $this->assertNull($gen->artifact_publication_id);     // binding di lezione, non di artefatto
        $this->assertSame($student->id, $gen->student_id);
        $this->assertSame('generating', $gen->status);
        Bus::assertDispatchedAfterResponse(StudentGenerateArtifactJob::class);
    }

    public function test_rate_limit_blocks_further_generation(): void
    {
        Bus::fake();
        atheneum_setting_put('schola.student_daily_generations', 1);
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $pub = $this->publishLesson($lesson, $class);

        // 1ª generazione consentita.
        $this->asUser($student)->post(route('student.classes.lesson.generate', [$class, $lesson]), ['type' => 'quiz'])
            ->assertRedirect();
        // La 1ª resta "generating" (Bus::fake non esegue il job) → conta nel limite.
        $this->asUser($student)->post(route('student.classes.lesson.generate', [$class, $lesson]), ['type' => 'quiz'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, StudentGeneratedArtifact::where('student_id', $student->id)->count());
    }

    // ===== Job: quiz dal corpo lezione =====

    public function test_job_generates_quiz_from_lesson_content(): void
    {
        $this->fakeQuizResponse();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $pub = $this->publishLesson($lesson, $class);

        $gen = StudentGeneratedArtifact::create([
            'student_id' => $student->id, 'lesson_publication_id' => $pub->id, 'type' => 'quiz', 'status' => 'generating',
        ]);

        (new StudentGenerateArtifactJob($gen->id, ['num_questions' => 5]))
            ->handle(app(MindMapGenerationService::class), app(QuizGeneratorService::class));

        $gen->refresh();
        $this->assertSame('ready', $gen->status);
        $this->assertNotNull($gen->quiz_id);
        // Quiz Schola: fuori dal mondo corsi.
        $this->assertDatabaseHas('quizzes', ['id' => $gen->quiz_id, 'module_id' => null, 'course_id' => null]);
    }

    public function test_lesson_status_endpoint_ownership(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $owner = $this->student();
        $other = $this->student();
        $this->enroll($class, $owner);
        $this->enroll($class, $other);
        $lesson = $this->lesson($prof);
        $pub = $this->publishLesson($lesson, $class);
        $gen = StudentGeneratedArtifact::create([
            'student_id' => $owner->id, 'lesson_publication_id' => $pub->id, 'type' => 'quiz', 'status' => 'generating',
        ]);

        $this->asUser($owner)->getJson(route('student.classes.lesson.generated.status', [$class, $lesson, $gen]))
            ->assertOk()->assertJson(['status' => 'generating']);
        $this->asUser($other)->getJson(route('student.classes.lesson.generated.status', [$class, $lesson, $gen]))
            ->assertForbidden();
    }

    // ===== Privacy: l'artefatto auto-generato è privato =====

    public function test_lesson_generated_artifact_excluded_from_cruscotto(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        // Generazione di LEZIONE (privata).
        $lesson = $this->lesson($prof);
        $lessonPub = $this->publishLesson($lesson, $class);
        StudentGeneratedArtifact::create(['student_id' => $student->id, 'lesson_publication_id' => $lessonPub->id,
            'type' => 'quiz', 'status' => 'ready']);

        // Generazione di ARTEFATTO (fetta 1: tracciata nel cruscotto).
        $artifact = TeachingArtifact::create(['teacher_id' => $prof->id, 'type' => 'summary', 'title' => 'R',
            'content' => 'x', 'status' => 'ready']);
        $artPub = ArtifactPublication::create(['teaching_artifact_id' => $artifact->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'published_at' => now(), 'rag_status' => 'ready']);
        StudentGeneratedArtifact::create(['student_id' => $student->id, 'artifact_publication_id' => $artPub->id,
            'type' => 'mindmap', 'status' => 'ready']);

        // Il cruscotto conta SOLO la generazione da artefatto, MAI quella di lezione.
        $activity = app(ClassSignalsService::class)->studentActivity($class);
        $row = collect($activity)->firstWhere('student_id', $student->id);
        $this->assertSame(1, $row['generations']);
    }

    // ===== Regressione fetta 1: generazione da artefatto invariata =====

    public function test_artifact_generation_still_works(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $artifact = TeachingArtifact::create(['teacher_id' => $prof->id, 'type' => 'summary', 'title' => 'R',
            'content' => 'Contenuto', 'status' => 'ready']);
        $pub = ArtifactPublication::create(['teaching_artifact_id' => $artifact->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'published_at' => now(), 'rag_status' => 'ready']);

        $this->asUser($student)->post(route('student.classes.artifact.generate', [$class, $pub]), ['type' => 'mindmap'])
            ->assertRedirect();

        $gen = StudentGeneratedArtifact::first();
        $this->assertSame($pub->id, $gen->artifact_publication_id);
        $this->assertNull($gen->lesson_publication_id);
        Bus::assertDispatchedAfterResponse(StudentGenerateArtifactJob::class);
    }
}
