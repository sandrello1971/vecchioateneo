<?php

namespace Tests\Feature\Schola;

use App\Jobs\StudentGenerateArtifactJob;
use App\Models\ArtifactPublication;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentArtifactView;
use App\Models\StudentGeneratedArtifact;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Services\MindMapGenerationService;
use App\Services\QuizGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentFruitionTest extends TestCase
{
    use RefreshDatabase;

    // ===== helpers =====

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'prof+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 'stu+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function klass(Student $teacher): SchoolClass
    {
        $subject = Subject::firstOrCreate(['name' => 'Fisica']);
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => '3B', 'subject_id' => $subject->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $c, Student $s, string $status = 'active'): void
    {
        ClassStudent::create(['school_class_id' => $c->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null]);
    }

    private function artifact(Student $prof, array $attrs = [], ?TeachingDocument $doc = null): TeachingArtifact
    {
        return TeachingArtifact::create(array_merge([
            'teaching_document_id' => $doc?->id, 'teacher_id' => $prof->id,
            'type' => 'summary', 'title' => 'Riassunto fotosintesi',
            'content' => "## Fotosintesi\n\nAvviene nei **cloroplasti**.", 'status' => 'ready',
        ], $attrs));
    }

    private function publish(TeachingArtifact $a, SchoolClass $c, array $attrs = []): ArtifactPublication
    {
        return ArtifactPublication::create(array_merge([
            'teaching_artifact_id' => $a->id, 'school_class_id' => $c->id,
            'students_can_generate' => true, 'downloadable' => false,
            'published_at' => now(), 'rag_status' => 'ready',
        ], $attrs));
    }

    private function asStudent(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    // ===== Visibilità: solo enrollment ACTIVE =====

    public function test_feed_visible_only_to_active_enrollment(): void
    {
        $prof = $this->prof();
        $class = $this->klass($prof);
        $this->publish($this->artifact($prof), $class);

        $active = $this->student(); $this->enroll($class, $active, 'active');
        $pending = $this->student(); $this->enroll($class, $pending, 'pending');
        $removed = $this->student(); $this->enroll($class, $removed, 'removed');
        $stranger = $this->student();

        $this->asStudent($active)->get(route('student.classes.show', $class))->assertOk()->assertSee('Riassunto fotosintesi');
        $this->asStudent($pending)->get(route('student.classes.show', $class))->assertForbidden();
        $this->asStudent($removed)->get(route('student.classes.show', $class))->assertForbidden();
        $this->asStudent($stranger)->get(route('student.classes.show', $class))->assertForbidden();
    }

    public function test_artifact_show_and_source_require_active_enrollment(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $class = $this->klass($prof);
        $doc = TeachingDocument::create(['teacher_id' => $prof->id, 'title' => 'Lezione', 'source_type' => 'audio',
            'status' => 'ready', 'extracted_text' => 'testo', 'source_files' => ['td/a.mp3']]);
        Storage::disk('local')->put('td/a.mp3', 'AUDIO');
        $art = $this->artifact($prof, ['type' => 'transcript', 'title' => 'Trascrizione'], $doc);
        $pub = $this->publish($art, $class);

        $active = $this->student(); $this->enroll($class, $active, 'active');
        $pending = $this->student(); $this->enroll($class, $pending, 'pending');

        $this->asStudent($active)->get(route('student.classes.artifact.show', [$class, $pub]))->assertOk();
        $this->asStudent($active)->get(route('student.classes.artifact.source', [$class, $pub]))->assertOk();
        $this->asStudent($pending)->get(route('student.classes.artifact.show', [$class, $pub]))->assertForbidden();
        $this->asStudent($pending)->get(route('student.classes.artifact.source', [$class, $pub]))->assertForbidden();
    }

    public function test_publication_must_belong_to_class_in_path(): void
    {
        $prof = $this->prof();
        $classA = $this->klass($prof); $classB = $this->klass($prof);
        $pubB = $this->publish($this->artifact($prof), $classB);
        $s = $this->student(); $this->enroll($classA, $s, 'active');

        // pubblicazione di B vista dal path di A → 404
        $this->asStudent($s)->get(route('student.classes.artifact.show', [$classA, $pubB]))->assertNotFound();
    }

    // ===== Tracking viste idempotente =====

    public function test_view_tracking_is_idempotent(): void
    {
        $prof = $this->prof(); $class = $this->klass($prof);
        $pub = $this->publish($this->artifact($prof), $class);
        $s = $this->student(); $this->enroll($class, $s, 'active');

        $this->asStudent($s)->get(route('student.classes.artifact.show', [$class, $pub]))->assertOk();
        $this->asStudent($s)->get(route('student.classes.artifact.show', [$class, $pub]))->assertOk();

        $views = StudentArtifactView::where('artifact_publication_id', $pub->id)->where('student_id', $s->id)->get();
        $this->assertCount(1, $views, 'Una sola riga (no duplicati)');
        $this->assertSame(2, (int) $views->first()->view_count);
        $this->assertNotNull($views->first()->first_viewed_at);
    }

    // ===== Auto-generazione + rate limit + permessi =====

    public function test_generation_blocked_when_students_cannot_generate(): void
    {
        Bus::fake();
        $prof = $this->prof(); $class = $this->klass($prof);
        $pub = $this->publish($this->artifact($prof), $class, ['students_can_generate' => false]);
        $s = $this->student(); $this->enroll($class, $s, 'active');

        $this->asStudent($s)->post(route('student.classes.artifact.generate', [$class, $pub]), ['type' => 'mindmap'])
            ->assertForbidden();
        Bus::assertNothingDispatched();
    }

    public function test_generation_dispatches_and_respects_rate_limit(): void
    {
        Bus::fake();
        atheneum_setting_put('schola.student_daily_generations', 1);
        $prof = $this->prof(); $class = $this->klass($prof);
        $pub = $this->publish($this->artifact($prof), $class);
        $s = $this->student(); $this->enroll($class, $s, 'active');

        // 1ª generazione: ok
        $this->asStudent($s)->post(route('student.classes.artifact.generate', [$class, $pub]), ['type' => 'mindmap'])
            ->assertRedirect();
        Bus::assertDispatchedAfterResponse(StudentGenerateArtifactJob::class);
        $this->assertDatabaseHas('student_generated_artifacts', ['student_id' => $s->id, 'type' => 'mindmap']);

        // 2ª oggi: oltre soglia → blocco gentile, nessuna nuova riga
        Bus::fake();
        $this->asStudent($s)->post(route('student.classes.artifact.generate', [$class, $pub]), ['type' => 'quiz'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, StudentGeneratedArtifact::where('student_id', $s->id)->count());
        Bus::assertNothingDispatched();
    }

    public function test_rate_limit_resets_next_day(): void
    {
        atheneum_setting_put('schola.student_daily_generations', 1);
        $prof = $this->prof(); $class = $this->klass($prof);
        $pub = $this->publish($this->artifact($prof), $class);
        $s = $this->student(); $this->enroll($class, $s, 'active');

        // generazione di IERI: non conta per oggi
        $old = StudentGeneratedArtifact::create(['student_id' => $s->id, 'artifact_publication_id' => $pub->id,
            'type' => 'mindmap', 'status' => 'ready']);
        $old->forceFill(['created_at' => Carbon::yesterday()])->save();

        Bus::fake();
        $this->asStudent($s)->post(route('student.classes.artifact.generate', [$class, $pub]), ['type' => 'mindmap'])
            ->assertRedirect()->assertSessionHas('success');
        Bus::assertDispatchedAfterResponse(StudentGenerateArtifactJob::class);
    }

    public function test_generation_job_produces_mindmap(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "# Fotosintesi\n## Fasi\n- Luminosa"]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200)]);
        $prof = $this->prof(); $class = $this->klass($prof);
        $pub = $this->publish($this->artifact($prof), $class);
        $s = $this->student(); $this->enroll($class, $s, 'active');
        $gen = StudentGeneratedArtifact::create(['student_id' => $s->id, 'artifact_publication_id' => $pub->id,
            'type' => 'mindmap', 'status' => 'generating']);

        (new StudentGenerateArtifactJob($gen->id))->handle(app(MindMapGenerationService::class), app(QuizGeneratorService::class));

        $gen->refresh();
        $this->assertSame('ready', $gen->status);
        $this->assertStringContainsString('# Fotosintesi', $gen->content);
    }

    // ===== Auto-generati: visibili solo al proprio studente =====

    public function test_self_generated_quiz_accessible_only_to_owner(): void
    {
        $prof = $this->prof(); $class = $this->klass($prof);
        $pub = $this->publish($this->artifact($prof), $class);
        $a = $this->student(); $this->enroll($class, $a, 'active');
        $b = $this->student(); $this->enroll($class, $b, 'active');

        // quiz auto-generato di A (module/course NULL → Schola)
        $quiz = Quiz::create(['module_id' => null, 'course_id' => null, 'title' => 'Autoverifica A']);
        StudentGeneratedArtifact::create(['student_id' => $a->id, 'artifact_publication_id' => $pub->id,
            'type' => 'quiz', 'quiz_id' => $quiz->id, 'status' => 'ready']);

        $this->asStudent($a)->get(route('student.quiz.show', $quiz))->assertOk();
        $this->asStudent($b)->get(route('student.quiz.show', $quiz))->assertForbidden();
    }

    public function test_published_quiz_accessible_to_class_members_not_outsiders(): void
    {
        $prof = $this->prof(); $class = $this->klass($prof);
        $quiz = Quiz::create(['module_id' => null, 'course_id' => null, 'title' => 'Quiz pubblicato']);
        $art = $this->artifact($prof, ['type' => 'quiz', 'title' => 'Quiz', 'content' => null, 'quiz_id' => $quiz->id]);
        $this->publish($art, $class);

        $member = $this->student(); $this->enroll($class, $member, 'active');
        $outsider = $this->student();

        $this->asStudent($member)->get(route('student.quiz.show', $quiz))->assertOk();
        $this->asStudent($outsider)->get(route('student.quiz.show', $quiz))->assertForbidden();
    }

    // ===== Rate limit chat Minerva di classe =====

    public function test_chat_rate_limit_blocks_without_calling_model(): void
    {
        Http::fake(); // attendiamo ZERO chiamate
        atheneum_setting_put('schola.student_daily_chat_messages', 1);
        $prof = $this->prof(); $class = $this->klass($prof);
        $s = $this->student(); $this->enroll($class, $s, 'active');

        // 1 messaggio utente OGGI in una conversazione di classe → soglia raggiunta
        $conv = ChatConversation::create(['student_id' => $s->id, 'school_class_id' => $class->id,
            'is_active' => true, 'title' => 'c', 'course_id' => null]);
        ChatMessage::create(['conversation_id' => $conv->id, 'role' => 'user', 'content' => 'q1']);

        $resp = $this->asStudent($s)->postJson(route('student.minerva.ask'), [
            'question' => 'q2', 'school_class_id' => $class->id,
        ]);

        $resp->assertOk()->assertJson(['gate' => 'rate_limited']);
        Http::assertNothingSent();
    }

    // ===== Regressione mondo corsi =====

    public function test_corsi_quiz_unaffected_by_schola_guard(): void
    {
        // Quiz del mondo corsi (course_id valorizzato) → il guard è no-op.
        $course = Course::create(['name' => 'Corso X', 'slug' => 'corso-x-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $quiz = Quiz::create(['course_id' => $course->id, 'module_id' => null, 'title' => 'Quiz corso']);
        $s = $this->student();

        // Nessuna iscrizione a classi Schola: deve comunque accedere (comportamento pre-esistente).
        $this->asStudent($s)->get(route('student.quiz.show', $quiz))->assertOk();
    }
}
