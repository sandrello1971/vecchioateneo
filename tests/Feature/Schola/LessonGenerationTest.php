<?php

namespace Tests\Feature\Schola;

use App\Jobs\GenerateArtifactJob;
use App\Jobs\GenerateLessonJob;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Models\Topic;
use App\Services\ConceptMapGenerationService;
use App\Services\LessonGenerationService;
use App\Services\MindMapGenerationService;
use App\Services\QuizGeneratorService;
use App\Services\SummaryGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Fase 3 (P19) — composizione del corpo lezione da più materiali, editing,
// rigenerazione, artefatti di lezione, conservazione segments, Feedback UX, proprietà.
class LessonGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Subject $storia;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key']);
        $this->storia = Subject::firstOrCreate(['name' => 'Storia']);
    }

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function topic(Student $p): Topic
    {
        return Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'Rivoluzione francese', 'position' => 0]);
    }

    private function lesson(Topic $topic, array $attrs = []): Lesson
    {
        return Lesson::create(array_merge([
            'topic_id' => $topic->id, 'teacher_id' => $topic->teacher_id, 'title' => 'Le cause', 'position' => 0,
            'generation_status' => 'draft',
        ], $attrs));
    }

    private function material(Student $p, ?Lesson $lesson, string $text, array $attrs = []): TeachingDocument
    {
        return TeachingDocument::create(array_merge([
            'teacher_id' => $p->id, 'lesson_id' => $lesson?->id, 'title' => 'Materiale', 'source_type' => 'text',
            'status' => 'ready', 'extracted_text' => $text,
        ], $attrs));
    }

    private function fakeText(string $text): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 200, 'output_tokens' => 90],
        ], 200)]);
    }

    private function runComposition(Lesson $lesson): void
    {
        (new GenerateLessonJob($lesson->id))->handle(app(LessonGenerationService::class));
    }

    private function runArtifact(TeachingArtifact $a, array $options = []): void
    {
        (new GenerateArtifactJob($a->id, $options))->handle(
            app(MindMapGenerationService::class),
            app(ConceptMapGenerationService::class),
            app(QuizGeneratorService::class),
            app(SummaryGenerationService::class),
        );
    }

    // ===== Composizione del corpo =====

    public function test_compose_lesson_from_multiple_materials(): void
    {
        $this->fakeText("# Le cause\n\n## Crisi economica\nLo Stato è in bancarotta.\n\n## Sintesi\nMolti fattori.");
        $p = $this->prof();
        $topic = $this->topic($p);
        $lesson = $this->lesson($topic, ['generation_status' => 'generating']);
        $this->material($p, $lesson, 'La Francia era in crisi finanziaria nel 1789.', ['title' => 'Appunti economia']);
        $this->material($p, $lesson, 'La società era divisa in tre stati.', ['title' => 'Appunti società']);

        $this->runComposition($lesson);
        $lesson->refresh();

        $this->assertSame('ready', $lesson->generation_status);
        $this->assertStringContainsString('Crisi economica', $lesson->content);
        $this->assertSame('claude-sonnet-4-5', $lesson->generation_meta['model']);
        $this->assertSame(2, $lesson->generation_meta['sources_count']);
        $this->assertCount(2, $lesson->generation_meta['sources_used']);
        $this->assertArrayHasKey('tokens_in', $lesson->generation_meta);
        $this->assertArrayHasKey('prompt_version', $lesson->generation_meta);

        // Componiamo da N fonti eterogenee: entrambe finiscono nel prompt.
        Http::assertSent(function ($request) {
            $body = $request->data()['messages'][0]['content'];
            return str_contains($body, 'Appunti economia') && str_contains($body, 'Appunti società');
        });
    }

    public function test_compose_fails_without_ready_materials(): void
    {
        $this->fakeText('non dovrebbe essere chiamato');
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'generating']);
        // materiale non pronto → escluso
        $this->material($p, $lesson, 'testo', ['status' => 'processing']);

        $this->runComposition($lesson);
        $lesson->refresh();

        $this->assertSame('failed', $lesson->generation_status);
        $this->assertNotEmpty($lesson->generation_meta['failure_reason']);
        Http::assertNothingSent();
    }

    public function test_compose_fails_on_api_error(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response('upstream boom', 500)]);
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'generating']);
        $this->material($p, $lesson, 'La Francia era in crisi.');

        $this->runComposition($lesson);
        $lesson->refresh();

        $this->assertSame('failed', $lesson->generation_status);
        $this->assertStringContainsString('500', $lesson->generation_meta['failure_reason']);
    }

    public function test_segments_preserved_in_body_and_meta(): void
    {
        $this->fakeText("## Lezione\nIl discorso inizia [00:05] con la crisi.");
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'generating']);
        $this->material($p, $lesson, 'Trascrizione del video.', [
            'source_type' => 'youtube',
            'extraction_meta' => ['segments' => [
                ['start_seconds' => 5.0, 'end_seconds' => 12.0, 'text' => 'Parliamo della crisi finanziaria.'],
                ['start_seconds' => 12.0, 'end_seconds' => 20.0, 'text' => 'Poi della società divisa.'],
            ]],
        ]);

        $this->runComposition($lesson);
        $lesson->refresh();

        $this->assertSame('ready', $lesson->generation_status);
        $this->assertTrue($lesson->generation_meta['segments_preserved']);
        $this->assertTrue($lesson->generation_meta['sources_used'][0]['has_segments']);

        // I marcatori [mm:ss] arrivano nel prompt (per conservare i riferimenti).
        Http::assertSent(function ($request) {
            return str_contains($request->data()['messages'][0]['content'], '[00:05]');
        });
    }

    // ===== Editing =====

    public function test_editing_persists(): void
    {
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'ready', 'content' => 'Originale']);

        $this->asProf($p)->patch(route('docente.lessons.content', $lesson), [
            'content' => '# Modificato a mano',
        ])->assertRedirect(route('docente.lessons.show', $lesson));

        $this->assertSame('# Modificato a mano', $lesson->fresh()->content);
    }

    // ===== Generazione via controller + Feedback UX =====

    public function test_generate_sets_generating_and_dispatches(): void
    {
        Bus::fake();
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p));
        $this->material($p, $lesson, 'La Francia era in crisi.');

        $this->asProf($p)->post(route('docente.lessons.generate', $lesson))
            ->assertRedirect(route('docente.lessons.show', $lesson));

        $this->assertSame('generating', $lesson->fresh()->generation_status);
        Bus::assertDispatchedAfterResponse(GenerateLessonJob::class);
    }

    public function test_generate_requires_ready_materials(): void
    {
        Bus::fake();
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p)); // nessun materiale

        $this->asProf($p)->post(route('docente.lessons.generate', $lesson))->assertRedirect();

        $this->assertSame('draft', $lesson->fresh()->generation_status);
        Bus::assertNotDispatchedAfterResponse(GenerateLessonJob::class);
    }

    public function test_anti_double_submit_when_already_generating(): void
    {
        Bus::fake();
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'generating']);
        $this->material($p, $lesson, 'La Francia era in crisi.');

        $this->asProf($p)->post(route('docente.lessons.generate', $lesson))->assertRedirect();

        Bus::assertNotDispatchedAfterResponse(GenerateLessonJob::class);
    }

    public function test_regenerate_overwrites_and_dispatches(): void
    {
        Bus::fake();
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'ready', 'content' => 'Vecchio']);
        $this->material($p, $lesson, 'La Francia era in crisi.');

        $this->asProf($p)->post(route('docente.lessons.regenerate', $lesson))->assertRedirect();

        $this->assertSame('generating', $lesson->fresh()->generation_status);
        $this->assertTrue($lesson->fresh()->generation_meta['regenerated']);
        Bus::assertDispatchedAfterResponse(GenerateLessonJob::class);
    }

    public function test_status_endpoint_reports_state(): void
    {
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'failed', 'generation_meta' => ['failure_reason' => 'boom']]);

        $this->asProf($p)->getJson(route('docente.lessons.status', $lesson))
            ->assertOk()
            ->assertJson(['status' => 'failed', 'failure_reason' => 'boom']);
    }

    // ===== Artefatti a livello di lezione =====

    public function test_lesson_artifact_generated_from_lesson_body(): void
    {
        Bus::fake();
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), [
            'generation_status' => 'ready',
            'content' => 'La rivoluzione francese inizia nel 1789 per cause economiche e sociali.',
        ]);

        $this->asProf($p)->post(route('docente.lessons.artifacts.generate', $lesson), ['type' => 'summary'])
            ->assertRedirect();

        $artifact = TeachingArtifact::where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($artifact);
        $this->assertNull($artifact->teaching_document_id);
        $this->assertSame($this->storia->id, $artifact->subject_id);
        $this->assertSame('generating', $artifact->status);
        Bus::assertDispatchedAfterResponse(GenerateArtifactJob::class);

        // Il job riusa GenerateArtifactJob e compone dalla lezione (niente doc grezzo).
        $this->fakeText('## Riassunto\nLa rivoluzione del 1789.');
        $this->runArtifact($artifact, ['level' => 'medio']);
        $artifact->refresh();

        $this->assertSame('ready', $artifact->status);
        $this->assertStringContainsString('Riassunto', $artifact->content);
    }

    public function test_lesson_artifact_requires_ready_lesson(): void
    {
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p)); // draft, niente content

        $this->asProf($p)->post(route('docente.lessons.artifacts.generate', $lesson), ['type' => 'summary'])
            ->assertStatus(422);

        $this->assertSame(0, TeachingArtifact::where('lesson_id', $lesson->id)->count());
    }

    public function test_lesson_artifact_anti_double_submit(): void
    {
        Bus::fake();
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'ready', 'content' => 'Corpo lezione.']);
        $existing = TeachingArtifact::create([
            'lesson_id' => $lesson->id, 'teacher_id' => $p->id, 'type' => 'summary',
            'title' => 'Riassunto', 'status' => 'generating',
        ]);

        $this->asProf($p)->post(route('docente.lessons.artifacts.generate', $lesson), ['type' => 'summary'])
            ->assertRedirect(route('docente.artifacts.show', $existing));

        $this->assertSame(1, TeachingArtifact::where('lesson_id', $lesson->id)->where('type', 'summary')->count());
        Bus::assertNotDispatchedAfterResponse(GenerateArtifactJob::class);
    }

    // ===== Proprietà / tenancy =====

    public function test_cannot_generate_other_teachers_lesson(): void
    {
        Bus::fake();
        $owner = $this->prof();
        $intruder = $this->prof();
        $lesson = $this->lesson($this->topic($owner));
        $this->material($owner, $lesson, 'testo');

        $this->asProf($intruder)->post(route('docente.lessons.generate', $lesson))->assertForbidden();
        $this->asProf($intruder)->post(route('docente.lessons.regenerate', $lesson))->assertForbidden();
        $this->asProf($intruder)->get(route('docente.lessons.show', $lesson))->assertForbidden();
        $this->asProf($intruder)->patch(route('docente.lessons.content', $lesson), ['content' => 'x'])->assertForbidden();
        $this->asProf($intruder)->getJson(route('docente.lessons.status', $lesson))->assertForbidden();
        $this->asProf($intruder)->post(route('docente.lessons.artifacts.generate', $lesson), ['type' => 'summary'])->assertForbidden();

        Bus::assertNotDispatchedAfterResponse(GenerateLessonJob::class);
    }

    public function test_show_page_renders_for_owner(): void
    {
        $p = $this->prof();
        $lesson = $this->lesson($this->topic($p), ['generation_status' => 'ready', 'content' => '# Corpo']);
        $this->material($p, $lesson, 'testo', ['title' => 'Materiale uno']);

        $this->asProf($p)->get(route('docente.lessons.show', $lesson))
            ->assertOk()
            ->assertSee('Le cause')
            ->assertSee('Materiale uno');
    }
}
