<?php

namespace Tests\Feature\Schola;

use App\Jobs\IngestLessonRagJob;
use App\Jobs\PurgeWithdrawnLessonPublicationJob;
use App\Models\DocumentRag;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingAssignment;
use App\Models\TeachingDocument;
use App\Models\Topic;
use App\Services\RagService;
use App\Services\Schola\LessonRagIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Fase 3 (P20a) — pubblicazione lezione su classe + ingestion RAG scope='class'.
class LessonPublicationTest extends TestCase
{
    use RefreshDatabase;

    private const DIM = 768;
    private Subject $storia;

    protected function setUp(): void
    {
        parent::setUp();
        atheneum_setting_put('schola.rag_min_similarity', 0.3);
        $this->storia = Subject::firstOrCreate(['name' => 'Storia']);
    }

    private function fakeEmbeddings(): void
    {
        Http::fake(['*/api/embeddings' => function ($request) {
            $texts = $request->data()['texts'] ?? [];
            return Http::response([
                'embeddings' => array_map(fn () => array_fill(0, self::DIM, 0.01), $texts),
                'model' => 'm', 'dimensions' => self::DIM,
            ], 200);
        }]);
    }

    private function prof(?School $school = null): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $school?->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function school(): School
    {
        return School::create(['name' => 'Liceo', 'slug' => 'l-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
    }

    private function freeClass(Student $teacher, string $name = '3A'): SchoolClass
    {
        return SchoolClass::create(['school_id' => null, 'teacher_id' => $teacher->id, 'name' => $name,
            'subject_id' => $this->storia->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function schoolClass(School $school, string $name = '3B'): SchoolClass
    {
        return SchoolClass::create(['school_id' => $school->id, 'teacher_id' => null, 'name' => $name,
            'subject_id' => null, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => false,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function cattedra(Student $teacher, SchoolClass $class): TeachingAssignment
    {
        return TeachingAssignment::create(['school_id' => $class->school_id, 'teacher_id' => $teacher->id,
            'subject_id' => $this->storia->id, 'school_class_id' => $class->id, 'school_year' => $class->school_year]);
    }

    private function readyLesson(Student $p, string $content = 'La Rivoluzione francese inizia nel 1789 per cause economiche.'): Lesson
    {
        $topic = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'Rivoluzione', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $p->id, 'title' => 'Le cause',
            'position' => 0, 'generation_status' => 'ready', 'content' => $content]);
    }

    private function ingestor(): LessonRagIngestor
    {
        return app(LessonRagIngestor::class);
    }

    private function publication(Lesson $lesson, SchoolClass $class): LessonPublication
    {
        return LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'rag_status' => 'pending', 'published_at' => now()]);
    }

    // ===== Pubblicazione: cattedra (scuola) vs proprietà (libera) =====

    public function test_publish_school_class_requires_cattedra(): void
    {
        Bus::fake();
        $school = $this->school();
        $prof = $this->prof($school);
        $class = $this->schoolClass($school);
        $lesson = $this->readyLesson($prof);

        // Senza cattedra → 403, niente pubblicazione.
        $this->asProf($prof)->post(route('docente.lessons.publish', $lesson), ['class_ids' => [$class->id]])
            ->assertForbidden();
        $this->assertSame(0, LessonPublication::where('lesson_id', $lesson->id)->count());

        // Con cattedra → pubblicazione + ingestion dispatchata.
        $this->cattedra($prof, $class);
        $this->asProf($prof)->post(route('docente.lessons.publish', $lesson), ['class_ids' => [$class->id]])
            ->assertRedirect();

        $pub = LessonPublication::where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($pub);
        $this->assertSame('pending', $pub->rag_status);
        Bus::assertDispatchedAfterResponse(IngestLessonRagJob::class);
    }

    public function test_publish_free_class_uses_ownership(): void
    {
        Bus::fake();
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);

        $this->asProf($owner)->post(route('docente.lessons.publish', $lesson), ['class_ids' => [$class->id]])
            ->assertRedirect();
        $this->assertSame(1, LessonPublication::where('lesson_id', $lesson->id)->count());

        // Un altro docente non può pubblicare sulla classe libera altrui.
        $intruder = $this->prof();
        $lesson2 = $this->readyLesson($intruder);
        $this->asProf($intruder)->post(route('docente.lessons.publish', $lesson2), ['class_ids' => [$class->id]])
            ->assertForbidden();
    }

    public function test_publish_requires_ready_lesson(): void
    {
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $topic = Topic::create(['teacher_id' => $owner->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);
        $draft = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $owner->id, 'title' => 'L', 'position' => 0, 'generation_status' => 'draft']);

        $this->asProf($owner)->post(route('docente.lessons.publish', $draft), ['class_ids' => [$class->id]])
            ->assertStatus(422);
        $this->assertSame(0, LessonPublication::count());
    }

    // ===== Ingestion RAG =====

    public function test_ingestion_creates_class_chunks_with_lesson_metadata(): void
    {
        $this->fakeEmbeddings();
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);
        $pub = $this->publication($lesson, $class);

        (new IngestLessonRagJob($pub->id))->handle($this->ingestor());

        $chunks = DocumentRag::where('scope', 'class')->where('school_class_id', $class->id)->get();
        $this->assertGreaterThan(0, $chunks->count());
        foreach ($chunks as $c) {
            $this->assertSame($owner->id, $c->teacher_id);
            $this->assertSame($lesson->id, $c->metadata['lesson_id']);
            $this->assertSame($pub->id, $c->metadata['lesson_publication_id']);
            $this->assertFalse($c->is_instructor_only);
        }
        $this->assertTrue($chunks->contains(fn ($c) => ($c->metadata['type'] ?? null) === 'lesson'));
        $this->assertSame('ready', $pub->fresh()->rag_status);
    }

    public function test_ingestion_includes_segment_chunks_with_timing(): void
    {
        $this->fakeEmbeddings();
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);
        // Materiale audio/video con segments collegato alla lezione.
        TeachingDocument::create([
            'teacher_id' => $owner->id, 'lesson_id' => $lesson->id, 'title' => 'Video', 'source_type' => 'youtube',
            'source_url' => 'https://youtu.be/abc', 'status' => 'ready', 'extracted_text' => 'trascrizione',
            'extraction_meta' => ['segments' => [
                ['start_seconds' => 5.0, 'end_seconds' => 12.0, 'text' => 'Parliamo della crisi finanziaria del 1789.'],
                ['start_seconds' => 12.0, 'end_seconds' => 20.0, 'text' => 'Poi della società divisa in tre stati.'],
            ]],
        ]);
        $pub = $this->publication($lesson, $class);

        (new IngestLessonRagJob($pub->id))->handle($this->ingestor());

        $segChunks = DocumentRag::where('scope', 'class')->where('school_class_id', $class->id)->get()
            ->filter(fn ($c) => ($c->metadata['type'] ?? null) === 'transcript');
        $this->assertGreaterThan(0, $segChunks->count());
        $first = $segChunks->first();
        $this->assertArrayHasKey('start_seconds', $first->metadata);
        $this->assertArrayHasKey('end_seconds', $first->metadata);
        $this->assertSame('https://youtu.be/abc', $first->metadata['source_url']);
    }

    public function test_ingestion_includes_lesson_artifacts(): void
    {
        $this->fakeEmbeddings();
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);
        $artifact = TeachingArtifact::create([
            'lesson_id' => $lesson->id, 'teacher_id' => $owner->id, 'type' => 'summary',
            'title' => 'Riassunto', 'content' => 'Sintesi della rivoluzione francese.', 'status' => 'ready',
        ]);
        $pub = $this->publication($lesson, $class);

        (new IngestLessonRagJob($pub->id))->handle($this->ingestor());

        $artChunks = DocumentRag::where('scope', 'class')->where('school_class_id', $class->id)->get()
            ->filter(fn ($c) => ($c->metadata['artifact_id'] ?? null) === $artifact->id);
        $this->assertGreaterThan(0, $artChunks->count());
        $this->assertSame('summary', $artChunks->first()->metadata['type']);
    }

    public function test_reingestion_is_idempotent(): void
    {
        $this->fakeEmbeddings();
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);
        $pub = $this->publication($lesson, $class);

        (new IngestLessonRagJob($pub->id))->handle($this->ingestor());
        $first = DocumentRag::where('metadata->lesson_publication_id', $pub->id)->count();
        (new IngestLessonRagJob($pub->id))->handle($this->ingestor());
        $second = DocumentRag::where('metadata->lesson_publication_id', $pub->id)->count();

        $this->assertSame($first, $second);
        $this->assertGreaterThan(0, $first);
    }

    public function test_withdraw_purges_class_chunks(): void
    {
        $this->fakeEmbeddings();
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);
        $pub = $this->publication($lesson, $class);

        (new IngestLessonRagJob($pub->id))->handle($this->ingestor());
        $this->assertGreaterThan(0, DocumentRag::where('metadata->lesson_publication_id', $pub->id)->count());

        $this->asProf($owner)->delete(route('docente.lesson-publications.destroy', $pub))->assertRedirect();
        (new PurgeWithdrawnLessonPublicationJob($pub->id))->handle($this->ingestor());

        $this->assertSame(0, DocumentRag::where('metadata->lesson_publication_id', $pub->id)->count());
        // Idempotente: ri-purgare non genera errori.
        (new PurgeWithdrawnLessonPublicationJob($pub->id))->handle($this->ingestor());
        $this->assertSame(0, DocumentRag::where('metadata->lesson_publication_id', $pub->id)->count());
    }

    // ===== Isolamento scope (vincolo §5) =====

    public function test_scope_isolation_other_class_has_no_chunks(): void
    {
        $this->fakeEmbeddings();
        $owner = $this->prof();
        $classA = $this->freeClass($owner, '3A');
        $classB = $this->freeClass($owner, '3B');
        $lesson = $this->readyLesson($owner);
        $pub = $this->publication($lesson, $classA);

        (new IngestLessonRagJob($pub->id))->handle($this->ingestor());

        // Tutti i chunk sono scope=class della SOLA classe A.
        $all = DocumentRag::where('metadata->lesson_id', $lesson->id)->get();
        $this->assertTrue($all->every(fn ($c) => $c->scope === 'class' && $c->school_class_id === $classA->id));
        $this->assertSame(0, DocumentRag::where('scope', 'class')->where('school_class_id', $classB->id)->count());

        // Retrieval: la classe B non vede nulla; la classe A sì.
        $rag = app(RagService::class);
        $this->assertTrue($rag->searchClassScoped('rivoluzione francese cause', [$classB->id])->isEmpty());
        $this->assertGreaterThan(0, $rag->searchClassScoped('rivoluzione francese cause', [$classA->id])->count());
    }

    public function test_purge_idempotent_on_unknown_id(): void
    {
        $this->assertSame(0, $this->ingestor()->purgePublication('00000000-0000-0000-0000-000000000000'));
    }

    // ===== Proprietà / Feedback UX =====

    public function test_status_endpoint_reports_rag_status(): void
    {
        $owner = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);
        $pub = $this->publication($lesson, $class);
        $pub->update(['rag_status' => 'indexing']);

        $this->asProf($owner)->getJson(route('docente.lessons.publications.status', $lesson))
            ->assertOk()
            ->assertJsonFragment(['rag_status' => 'indexing', 'school_class_id' => $class->id]);
    }

    public function test_ownership_on_publish_withdraw_status(): void
    {
        $owner = $this->prof();
        $intruder = $this->prof();
        $class = $this->freeClass($owner);
        $lesson = $this->readyLesson($owner);
        $pub = $this->publication($lesson, $class);

        $this->asProf($intruder)->post(route('docente.lessons.publish', $lesson), ['class_ids' => [$class->id]])->assertForbidden();
        $this->asProf($intruder)->delete(route('docente.lesson-publications.destroy', $pub))->assertForbidden();
        $this->asProf($intruder)->getJson(route('docente.lessons.publications.status', $lesson))->assertForbidden();
    }
}
