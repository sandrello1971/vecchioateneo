<?php

namespace Tests\Feature\Schola;

use App\Jobs\IngestArtifactTeacherPrivateJob;
use App\Jobs\IngestPublicationRagJob;
use App\Jobs\PurgeWithdrawnPublicationJob;
use App\Models\ArtifactPublication;
use App\Models\DocumentRag;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Services\EmbeddingService;
use App\Services\RagService;
use App\Services\Schola\ArtifactRagIngestor;
use App\Support\PgVector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ArtifactPublicationTest extends TestCase
{
    use RefreshDatabase;

    private const DIM = 768;

    protected function setUp(): void
    {
        parent::setUp();
        atheneum_setting_put('schola.rag_min_similarity', 0.5);
    }

    // embeddings finte (dim 768) per far andare a buon fine embedBestEffort
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

    private function prof(?string $email = null): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => $email ?: 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function schoolClass(Student $teacher, string $name = '3ªB'): SchoolClass
    {
        $subject = \App\Models\Subject::firstOrCreate(['name' => 'Fisica']);

        return SchoolClass::create([
            'teacher_id' => $teacher->id, 'name' => $name, 'subject_id' => $subject->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false,
        ]);
    }

    private function artifact(Student $prof, array $attrs = [], ?TeachingDocument $doc = null): TeachingArtifact
    {
        return TeachingArtifact::create(array_merge([
            'teaching_document_id' => $doc?->id,
            'teacher_id' => $prof->id, 'type' => 'summary', 'title' => 'Riassunto',
            'content' => 'Contenuto del materiale didattico.', 'status' => 'ready',
        ], $attrs));
    }

    private function unit(int $i): array
    {
        $v = array_fill(0, self::DIM, 0.0);
        $v[$i] = 1.0;

        return $v;
    }

    private function ragChunk(array $attrs, array $vec): DocumentRag
    {
        $row = DocumentRag::create(array_merge(['title' => 't', 'content' => 'c', 'chunk_index' => 0], $attrs));
        DB::update('UPDATE documents_rag SET embedding = ?::vector WHERE id = ?', [PgVector::toLiteral($vec), $row->id]);

        return $row;
    }

    private function ingestor(): ArtifactRagIngestor
    {
        return app(ArtifactRagIngestor::class);
    }

    // ===== teacher_private =====

    public function test_teacher_private_ingested_for_ready_artifact(): void
    {
        $this->fakeEmbeddings();
        $prof = $this->prof();
        $art = $this->artifact($prof, ['content' => 'La fotosintesi avviene nei cloroplasti delle piante.']);

        (new IngestArtifactTeacherPrivateJob($art->id))->handle($this->ingestor());

        $chunks = DocumentRag::where('scope', 'teacher_private')->get();
        $this->assertGreaterThan(0, $chunks->count());
        $this->assertEqualsCanonicalizing([$prof->id], $chunks->pluck('teacher_id')->unique()->all());
        $this->assertSame($art->id, $chunks->first()->metadata['artifact_id']);
        $this->assertNotNull($chunks->first()->embedding ?? DB::table('documents_rag')->where('id', $chunks->first()->id)->value('embedding'));
    }

    public function test_teacher_private_ingestion_is_idempotent(): void
    {
        $this->fakeEmbeddings();
        $prof = $this->prof();
        $art = $this->artifact($prof);

        $this->ingestor()->ingestTeacherPrivate($art);
        $first = DocumentRag::where('scope', 'teacher_private')->count();
        $this->ingestor()->ingestTeacherPrivate($art);
        $second = DocumentRag::where('scope', 'teacher_private')->count();

        $this->assertSame($first, $second, 'La re-ingestion non deve duplicare i chunk');
    }

    public function test_generate_artifact_job_hook_dispatches_teacher_private_ingestion(): void
    {
        Bus::fake();
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '## Riassunto']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200)]);

        $prof = $this->prof();
        $doc = TeachingDocument::create([
            'teacher_id' => $prof->id, 'title' => 'Doc', 'source_type' => 'text',
            'status' => 'ready', 'extracted_text' => 'Testo da riassumere a sufficienza.',
        ]);
        $art = $this->artifact($prof, ['status' => 'generating', 'content' => null], $doc);

        (new \App\Jobs\GenerateArtifactJob($art->id, ['level' => 'medio']))->handle(
            app(\App\Services\MindMapGenerationService::class),
            app(\App\Services\ConceptMapGenerationService::class),
            app(\App\Services\QuizGeneratorService::class),
            app(\App\Services\SummaryGenerationService::class),
        );

        Bus::assertDispatched(IngestArtifactTeacherPrivateJob::class);
    }

    // ===== Pubblicazione (class) =====

    public function test_publication_ingestion_creates_class_chunks(): void
    {
        $this->fakeEmbeddings();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $art = $this->artifact($prof);
        $pub = ArtifactPublication::create([
            'teaching_artifact_id' => $art->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'downloadable' => false, 'published_at' => now(),
        ]);

        (new IngestPublicationRagJob($pub->id))->handle($this->ingestor());

        $chunks = DocumentRag::where('scope', 'class')->get();
        $this->assertGreaterThan(0, $chunks->count());
        $this->assertSame($class->id, $chunks->first()->school_class_id);
        $this->assertSame($prof->id, $chunks->first()->teacher_id);
        $this->assertSame($pub->id, $chunks->first()->metadata['publication_id']);
        $this->assertSame($art->id, $chunks->first()->metadata['artifact_id']);
        $this->assertSame('ready', $pub->fresh()->rag_status);
    }

    public function test_republish_is_idempotent(): void
    {
        $this->fakeEmbeddings();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $art = $this->artifact($prof, ['content' => str_repeat('Frase di prova lunga. ', 60)]);
        $pub = ArtifactPublication::create([
            'teaching_artifact_id' => $art->id, 'school_class_id' => $class->id, 'published_at' => now(),
        ]);

        $this->ingestor()->ingestPublication($pub);
        $first = DocumentRag::where('scope', 'class')->count();
        $this->ingestor()->ingestPublication($pub);
        $second = DocumentRag::where('scope', 'class')->count();

        $this->assertSame($first, $second);
    }

    public function test_withdrawal_removes_class_chunks_idempotent(): void
    {
        $this->fakeEmbeddings();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $art = $this->artifact($prof);
        $pub = ArtifactPublication::create([
            'teaching_artifact_id' => $art->id, 'school_class_id' => $class->id, 'published_at' => now(),
        ]);
        $this->ingestor()->ingestPublication($pub);
        $this->assertGreaterThan(0, DocumentRag::where('scope', 'class')->count());

        (new PurgeWithdrawnPublicationJob($pub->id))->handle($this->ingestor());
        $this->assertSame(0, DocumentRag::where('scope', 'class')->count());

        // idempotente: secondo purge non esplode e resta 0
        (new PurgeWithdrawnPublicationJob($pub->id))->handle($this->ingestor());
        $this->assertSame(0, DocumentRag::where('scope', 'class')->count());
    }

    // ===== Chunking entro la finestra =====

    public function test_chunks_stay_within_window(): void
    {
        $this->fakeEmbeddings();
        $prof = $this->prof();
        $art = $this->artifact($prof, ['content' => str_repeat('Lorem ipsum dolor sit amet consectetur. ', 200)]);

        $this->ingestor()->ingestTeacherPrivate($art);

        $lengths = DocumentRag::where('scope', 'teacher_private')->pluck('content')->map(fn ($c) => mb_strlen($c));
        $this->assertGreaterThan(1, $lengths->count(), 'Contenuto lungo → più chunk');
        $this->assertLessThanOrEqual(500, $lengths->max(), 'Nessun chunk Schola oltre ~500 caratteri');
    }

    // ===== Segments → citazione con minutaggio =====

    public function test_transcript_segments_carry_timestamp_metadata(): void
    {
        $this->fakeEmbeddings();
        $prof = $this->prof();
        $doc = TeachingDocument::create([
            'teacher_id' => $prof->id, 'title' => 'Lezione', 'source_type' => 'youtube',
            'source_url' => 'https://youtu.be/abc', 'status' => 'ready',
            'extracted_text' => 'Introduzione. Sviluppo. Conclusione.',
            'extraction_meta' => ['method' => 'whisper', 'segments' => [
                ['start_seconds' => 0.0, 'end_seconds' => 12.0, 'text' => 'Introduzione alla lezione di oggi'],
                ['start_seconds' => 12.0, 'end_seconds' => 30.0, 'text' => 'Sviluppo del tema principale'],
            ]],
        ]);
        $art = $this->artifact($prof, ['type' => 'transcript', 'title' => 'Trascrizione', 'content' => 'Introduzione. Sviluppo.'], $doc);

        $this->ingestor()->ingestTeacherPrivate($art);

        $chunk = DocumentRag::where('scope', 'teacher_private')->first();
        $this->assertNotNull($chunk);
        $this->assertArrayHasKey('start_seconds', $chunk->metadata);
        $this->assertArrayHasKey('end_seconds', $chunk->metadata);
        $this->assertSame('https://youtu.be/abc', $chunk->metadata['source_url']);
        $this->assertSame(0.0, (float) $chunk->metadata['start_seconds']);
    }

    // ===== §5: isolamento di scope per lo studente di classe =====

    public function test_class_student_never_receives_other_scopes_or_classes(): void
    {
        $teacherA = $this->prof('a@s.it');
        $teacherB = $this->prof('b@s.it');
        $classA1 = $this->schoolClass($teacherA, 'A1');
        $classA2 = $this->schoolClass($teacherA, 'A2');
        $classB1 = $this->schoolClass($teacherB, 'B1');

        $v = $this->unit(0); // tutti i chunk identici alla query → solo lo scope filtra
        $this->ragChunk(['scope' => 'platform', 'content' => 'plat'], $v);
        $this->ragChunk(['scope' => 'instructor_only', 'is_instructor_only' => true, 'content' => 'instr'], $v);
        $this->ragChunk(['scope' => 'teacher_private', 'teacher_id' => $teacherA->id, 'content' => 'priv'], $v);
        $this->ragChunk(['scope' => 'class', 'school_class_id' => $classA2->id, 'teacher_id' => $teacherA->id, 'content' => 'altraclasse'], $v);
        $this->ragChunk(['scope' => 'class', 'school_class_id' => $classB1->id, 'teacher_id' => $teacherB->id, 'content' => 'classealtrodocente'], $v);
        $wanted = $this->ragChunk(['scope' => 'class', 'school_class_id' => $classA1->id, 'teacher_id' => $teacherA->id, 'content' => 'miaclasse'], $v);

        // EmbeddingService mockato: query → unit(0) (tutti sopra soglia)
        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embedOne')->andReturn($this->unit(0));
        $svc->shouldReceive('dimensions')->andReturn(self::DIM);
        $this->instance(EmbeddingService::class, $svc);

        $results = app(RagService::class)->searchClassScoped('qualsiasi domanda', [$classA1->id], null, 10);

        $ids = $results->pluck('id')->all();
        $this->assertSame([$wanted->id], $ids, 'Lo studente di classe vede SOLO i chunk class della propria classe');
    }

    public function test_corsi_student_never_receives_schola_chunks(): void
    {
        // Chunk Schola con course_id NULL: senza il filtro di scope finirebbero
        // nel bucket "platform" (orWhereNull('course_id')) del mondo corsi.
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        DocumentRag::create(['title' => 'plat', 'content' => 'argomento comune piattaforma', 'scope' => 'platform']);
        DocumentRag::create(['title' => 'cls', 'content' => 'argomento comune classe', 'scope' => 'class', 'school_class_id' => $class->id, 'teacher_id' => $prof->id]);
        DocumentRag::create(['title' => 'priv', 'content' => 'argomento comune privato', 'scope' => 'teacher_private', 'teacher_id' => $prof->id]);

        // Studente corsi (flag corsi off → ILIKE): deve vedere SOLO platform.
        $results = app(RagService::class)->searchForUser('argomento comune', [], false, 10);

        $scopes = $results->pluck('scope')->unique()->all();
        $this->assertEqualsCanonicalizing(['platform'], $scopes);
    }

    // ===== Controller pubblicazione: owner + dispatch =====

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    public function test_publish_creates_publication_and_dispatches_job(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $art = $this->artifact($prof);

        $this->asProf($prof)->post(route('docente.artifacts.publish', $art), [
            'class_ids' => [$class->id], 'students_can_generate' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('artifact_publications', [
            'teaching_artifact_id' => $art->id, 'school_class_id' => $class->id, 'rag_status' => 'pending',
        ]);
        Bus::assertDispatchedAfterResponse(IngestPublicationRagJob::class);
    }

    public function test_publish_rejects_class_of_another_teacher(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $other = $this->prof('other@s.it');
        $otherClass = $this->schoolClass($other);
        $art = $this->artifact($prof);

        $this->asProf($prof)->post(route('docente.artifacts.publish', $art), [
            'class_ids' => [$otherClass->id],
        ])->assertForbidden();
        Bus::assertNothingDispatched();
    }

    public function test_publish_owner_only(): void
    {
        Bus::fake();
        $a = $this->prof();
        $b = $this->prof('b2@s.it');
        $art = $this->artifact($a);
        $class = $this->schoolClass($b);

        $this->asProf($b)->post(route('docente.artifacts.publish', $art), ['class_ids' => [$class->id]])
            ->assertForbidden();
    }

    public function test_withdraw_dispatches_purge_and_deletes_row(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $art = $this->artifact($prof);
        $pub = ArtifactPublication::create([
            'teaching_artifact_id' => $art->id, 'school_class_id' => $class->id, 'published_at' => now(),
        ]);

        $this->asProf($prof)->delete(route('docente.publications.destroy', $pub))->assertRedirect();

        $this->assertDatabaseMissing('artifact_publications', ['id' => $pub->id]);
        Bus::assertDispatchedAfterResponse(PurgeWithdrawnPublicationJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
