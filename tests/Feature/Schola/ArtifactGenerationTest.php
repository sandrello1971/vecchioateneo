<?php

namespace Tests\Feature\Schola;

use App\Jobs\ExtractTeachingDocumentJob;
use App\Jobs\GenerateArtifactJob;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Services\ConceptMapGenerationService;
use App\Services\MindMapGenerationService;
use App\Services\QuizGeneratorService;
use App\Services\Schola\TeachingDocumentExtractor;
use App\Services\SummaryGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtifactGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key']);
    }

    // ===== Helpers =====

    private function prof(): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function readyDoc(Student $p, string $text = 'La fotosintesi clorofilliana trasforma luce in energia chimica. Avviene nei cloroplasti.'): TeachingDocument
    {
        return TeachingDocument::create([
            'teacher_id' => $p->id, 'title' => 'Lezione di biologia', 'source_type' => 'text',
            'status' => 'ready', 'extracted_text' => $text,
        ]);
    }

    private function artifact(TeachingDocument $doc, string $type, array $attrs = []): TeachingArtifact
    {
        return TeachingArtifact::create(array_merge([
            'teaching_document_id' => $doc->id, 'teacher_id' => $doc->teacher_id,
            'type' => $type, 'title' => ucfirst($type) . ' — ' . $doc->title, 'status' => 'generating',
        ], $attrs));
    }

    private function runJob(TeachingArtifact $artifact, array $options = []): void
    {
        (new GenerateArtifactJob($artifact->id, $options))->handle(
            app(MindMapGenerationService::class),
            app(ConceptMapGenerationService::class),
            app(QuizGeneratorService::class),
            app(SummaryGenerationService::class),
        );
    }

    private function fakeText(string $text): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 60],
        ], 200)]);
    }

    // ===== Generazione per ogni tipo =====

    public function test_generate_summary_ready_with_meta(): void
    {
        $this->fakeText("## Fotosintesi\n\nProcesso che converte la **luce** in energia chimica.");
        $art = $this->artifact($this->readyDoc($this->prof()), 'summary');

        $this->runJob($art, ['level' => 'medio']);
        $art->refresh();

        $this->assertSame('ready', $art->status);
        $this->assertStringContainsString('Fotosintesi', $art->content);
        $this->assertSame('claude-sonnet-4-5', $art->generation_meta['model']);
        $this->assertSame('medio', $art->generation_meta['level']);
        $this->assertArrayHasKey('tokens_in', $art->generation_meta);
        $this->assertArrayHasKey('tokens_out', $art->generation_meta);
        $this->assertArrayHasKey('prompt_version', $art->generation_meta);
    }

    public function test_generate_outline_ready(): void
    {
        $this->fakeText("## Fotosintesi\n- Cloroplasti\n- Clorofilla");
        $art = $this->artifact($this->readyDoc($this->prof()), 'outline');

        $this->runJob($art);
        $art->refresh();

        $this->assertSame('ready', $art->status);
        $this->assertStringContainsString('Cloroplasti', $art->content);
        $this->assertArrayHasKey('prompt_version', $art->generation_meta);
    }

    public function test_generate_mindmap_ready(): void
    {
        $this->fakeText("# Fotosintesi\n\n## Fasi\n- Luminosa\n- Oscura");
        $art = $this->artifact($this->readyDoc($this->prof()), 'mindmap');

        $this->runJob($art);
        $art->refresh();

        $this->assertSame('ready', $art->status);
        $this->assertStringContainsString('# Fotosintesi', $art->content);
    }

    public function test_generate_conceptmap_ready_stores_json(): void
    {
        $graph = json_encode([
            'nodes' => [
                ['id' => 'n1', 'label' => 'Fotosintesi', 'description' => 'Processo biochimico'],
                ['id' => 'n2', 'label' => 'Cloroplasti', 'description' => 'Organuli'],
            ],
            'edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'avviene in']],
        ]);
        $this->fakeText($graph);
        $art = $this->artifact($this->readyDoc($this->prof()), 'conceptmap');

        $this->runJob($art);
        $art->refresh();

        $this->assertSame('ready', $art->status);
        $decoded = json_decode($art->content, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded['nodes']);
        $this->assertCount(1, $decoded['edges']);
    }

    public function test_generate_quiz_creates_quiz_with_null_module(): void
    {
        $this->fakeText(json_encode(['questions' => [
            [
                'question' => 'Dove avviene la fotosintesi?',
                'options' => ['Mitocondri', 'Cloroplasti', 'Nucleo', 'Ribosomi'],
                'correct_answer' => 'Cloroplasti',
                'explanation' => 'I cloroplasti contengono la clorofilla.',
            ],
        ]]));
        $art = $this->artifact($this->readyDoc($this->prof()), 'quiz');

        $this->runJob($art, ['num_questions' => 5]);
        $art->refresh();

        $this->assertSame('ready', $art->status);
        $this->assertNotNull($art->quiz_id);

        $quiz = Quiz::find($art->quiz_id);
        $this->assertNull($quiz->module_id, 'Il quiz Schola deve avere module_id NULL');
        $this->assertNull($quiz->course_id, 'Il quiz Schola deve avere course_id NULL');
        $this->assertSame(1, $quiz->questions()->count());
    }

    public function test_generation_failed_sets_status_and_reason(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response('upstream boom', 500)]);
        $art = $this->artifact($this->readyDoc($this->prof()), 'summary');

        $this->runJob($art, ['level' => 'breve']);
        $art->refresh();

        $this->assertSame('failed', $art->status);
        $this->assertNotEmpty($art->generation_meta['failure_reason']);
    }

    public function test_quiz_generation_failed_on_invalid_json(): void
    {
        $this->fakeText('non sono json valido');
        $art = $this->artifact($this->readyDoc($this->prof()), 'quiz');

        $this->runJob($art);
        $art->refresh();

        $this->assertSame('failed', $art->status);
        $this->assertNull($art->quiz_id);
    }

    // ===== Transcript automatico post-estrazione =====

    public function test_transcript_artifact_auto_created_after_extraction(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $doc = TeachingDocument::create([
            'teacher_id' => $prof->id, 'title' => 'Appunti', 'source_type' => 'text', 'status' => 'pending',
        ]);
        Storage::disk('local')->put('td/source.md', "# Titolo\n\nContenuto della lezione.");
        $doc->update(['source_files' => ['td/source.md']]);

        (new ExtractTeachingDocumentJob($doc->id))->handle(app(TeachingDocumentExtractor::class));

        $transcript = TeachingArtifact::where('teaching_document_id', $doc->id)->where('type', 'transcript')->first();
        $this->assertNotNull($transcript);
        $this->assertSame('ready', $transcript->status);
        $this->assertStringContainsString('Contenuto della lezione', $transcript->content);
        $this->assertSame('extraction', $transcript->generation_meta['source']);
    }

    public function test_transcript_not_duplicated_on_reextraction(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $doc = TeachingDocument::create([
            'teacher_id' => $prof->id, 'title' => 'Appunti', 'source_type' => 'text', 'status' => 'pending',
        ]);
        Storage::disk('local')->put('td/source.md', 'Prima estrazione.');
        $doc->update(['source_files' => ['td/source.md']]);

        (new ExtractTeachingDocumentJob($doc->id))->handle(app(TeachingDocumentExtractor::class));
        Storage::disk('local')->put('td/source.md', 'Seconda estrazione corretta.');
        (new ExtractTeachingDocumentJob($doc->id))->handle(app(TeachingDocumentExtractor::class));

        $transcripts = TeachingArtifact::where('teaching_document_id', $doc->id)->where('type', 'transcript')->get();
        $this->assertCount(1, $transcripts);
        $this->assertStringContainsString('Seconda estrazione', $transcripts->first()->content);
    }

    // ===== Quiz Schola invisibile nel mondo corsi =====

    public function test_schola_quiz_not_listed_in_admin_quizzes(): void
    {
        // Quiz Schola: module_id/course_id NULL, agganciato a un artefatto.
        $art = $this->artifact($this->readyDoc($this->prof()), 'quiz');
        $scholaQuiz = Quiz::create(['module_id' => null, 'course_id' => null, 'title' => 'Quiz Schola Segreto']);
        $art->update(['quiz_id' => $scholaQuiz->id, 'status' => 'ready']);

        // Quiz del mondo corsi (nessun artefatto): deve restare visibile.
        $courseQuiz = Quiz::create(['module_id' => null, 'course_id' => null, 'title' => 'Quiz Corso Visibile']);

        $res = $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@b.it'])
            ->get(route('admin.quizzes.index'));

        $res->assertOk();
        $res->assertSee('Quiz Corso Visibile');
        $res->assertDontSee('Quiz Schola Segreto');
    }

    // ===== Editing manuale =====

    public function test_manual_edit_persists(): void
    {
        $prof = $this->prof();
        $art = $this->artifact($this->readyDoc($prof), 'summary', ['status' => 'ready', 'content' => 'vecchio']);

        $this->asProf($prof)->patch(route('docente.artifacts.update', $art), [
            'title' => 'Riassunto corretto', 'content' => 'Nuovo contenuto rivisto a mano.',
        ])->assertRedirect();

        $art->refresh();
        $this->assertSame('Riassunto corretto', $art->title);
        $this->assertSame('Nuovo contenuto rivisto a mano.', $art->content);
    }

    public function test_manual_edit_conceptmap_rejects_invalid_json(): void
    {
        $prof = $this->prof();
        $art = $this->artifact($this->readyDoc($prof), 'conceptmap', ['status' => 'ready', 'content' => '{"nodes":[],"edges":[]}']);

        $this->asProf($prof)->patch(route('docente.artifacts.update', $art), [
            'title' => 'Mappa', 'content' => 'non è json',
        ])->assertSessionHasErrors('content');

        $this->assertSame('{"nodes":[],"edges":[]}', $art->fresh()->content);
    }

    // ===== Rigenerazione =====

    public function test_regenerate_dispatches_job_and_sets_generating(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $art = $this->artifact($this->readyDoc($prof), 'summary', ['status' => 'ready', 'content' => 'vecchio']);

        $this->asProf($prof)->post(route('docente.artifacts.regenerate', $art), ['level' => 'dispensa'])
            ->assertRedirect();

        $this->assertSame('generating', $art->fresh()->status);
        Bus::assertDispatchedAfterResponse(GenerateArtifactJob::class);
    }

    public function test_regenerate_quiz_reuses_same_quiz_record(): void
    {
        // Sequenza: prima risposta = 1 domanda, seconda (rigenerazione) = 2 domande.
        // (Http::fake non rimpiazza uno stub già registrato sullo stesso URL: serve una sequence.)
        Http::fake(['https://api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['questions' => [
                ['question' => 'Q1?', 'options' => ['a', 'b', 'c', 'd'], 'correct_answer' => 'a', 'explanation' => 'x'],
            ]])]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]], 200)
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['questions' => [
                ['question' => 'N1?', 'options' => ['a', 'b', 'c', 'd'], 'correct_answer' => 'b', 'explanation' => 'y'],
                ['question' => 'N2?', 'options' => ['a', 'b', 'c', 'd'], 'correct_answer' => 'c', 'explanation' => 'z'],
            ]])]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]], 200),
        ]);

        $art = $this->artifact($this->readyDoc($this->prof()), 'quiz');
        $this->runJob($art);
        $art->refresh();
        $firstQuizId = $art->quiz_id;
        $this->assertNotNull($firstQuizId);

        // Rigenerazione: stesso quiz_id, niente quiz orfani
        $art->update(['status' => 'generating']);
        $this->runJob($art);
        $art->refresh();

        $this->assertSame($firstQuizId, $art->quiz_id);
        $this->assertSame(1, Quiz::count());
        $this->assertSame(2, Quiz::find($firstQuizId)->questions()->count());
    }

    public function test_store_generation_creates_artifact_and_dispatches(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $doc = $this->readyDoc($prof);

        $this->asProf($prof)->post(route('docente.artifacts.generate', $doc), ['type' => 'mindmap'])
            ->assertRedirect();

        $this->assertDatabaseHas('teaching_artifacts', [
            'teaching_document_id' => $doc->id, 'type' => 'mindmap', 'status' => 'generating',
        ]);
        // Dispatch afterResponse: la risposta torna subito anche con QUEUE=sync.
        Bus::assertDispatchedAfterResponse(GenerateArtifactJob::class);
    }

    public function test_store_generation_guards_against_duplicate_in_progress(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $doc = $this->readyDoc($prof);
        $existing = $this->artifact($doc, 'summary', ['status' => 'generating']);

        // Secondo POST dello stesso tipo mentre il primo è ancora "generating":
        // niente duplicato, redirect all'artefatto già in corso.
        $this->asProf($prof)->post(route('docente.artifacts.generate', $doc), ['type' => 'summary'])
            ->assertRedirect(route('docente.artifacts.show', $existing));

        $this->assertSame(1, TeachingArtifact::where('teaching_document_id', $doc->id)
            ->where('type', 'summary')->count());
        Bus::assertNotDispatchedAfterResponse(GenerateArtifactJob::class);
    }

    public function test_store_generation_blocked_when_document_not_ready(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $doc = TeachingDocument::create([
            'teacher_id' => $prof->id, 'title' => 'In coda', 'source_type' => 'text', 'status' => 'pending',
        ]);

        $this->asProf($prof)->post(route('docente.artifacts.generate', $doc), ['type' => 'summary'])
            ->assertStatus(422);
        Bus::assertNothingDispatched();
    }

    public function test_soft_destroy(): void
    {
        $prof = $this->prof();
        $art = $this->artifact($this->readyDoc($prof), 'summary', ['status' => 'ready', 'content' => 'x']);

        $this->asProf($prof)->delete(route('docente.artifacts.destroy', $art))->assertRedirect();

        $this->assertSoftDeleted('teaching_artifacts', ['id' => $art->id]);
    }

    // ===== Policy: owner-only ovunque =====

    public function test_owner_only_policy(): void
    {
        $a = $this->prof();
        $b = $this->prof();
        $doc = $this->readyDoc($a);
        $art = $this->artifact($doc, 'summary', ['status' => 'ready', 'content' => 'x']);

        $this->asProf($b)->get(route('docente.artifacts.show', $art))->assertForbidden();
        $this->asProf($b)->get(route('docente.artifacts.status', $art))->assertForbidden();
        $this->asProf($b)->patch(route('docente.artifacts.update', $art), ['title' => 'Hack'])->assertForbidden();
        $this->asProf($b)->delete(route('docente.artifacts.destroy', $art))->assertForbidden();
        $this->asProf($b)->post(route('docente.artifacts.regenerate', $art))->assertForbidden();
        $this->asProf($b)->post(route('docente.artifacts.generate', $doc), ['type' => 'summary'])->assertForbidden();
    }

    public function test_show_renders_for_owner(): void
    {
        $prof = $this->prof();
        $art = $this->artifact($this->readyDoc($prof), 'summary', ['status' => 'ready', 'content' => '## Ok']);

        $this->asProf($prof)->get(route('docente.artifacts.show', $art))->assertOk()->assertSee('Riassunto');
    }
}
