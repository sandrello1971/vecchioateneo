<?php

namespace Tests\Feature\P26;

use App\Jobs\RunGapComposeJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseSource;
use App\Models\CoverageGap;
use App\Models\GapDraft;
use App\Models\InstructorManualSection;
use App\Models\Module;
use App\Services\GapComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P26 Fase B — Compose: il job genera la doppia bozza e la persiste; le bozze sono editabili e
 * approvabili. VINCOLO DI SICUREZZA: nulla viene mai scritto in course_sources / modules /
 * instructor_manual_sections (l'inserimento è la Fase D). Isolato, gated.
 */
class ComposeDraftTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'rev@ente.it';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.p26.enabled' => true]);
        Admin::create(['name' => 'Rev', 'email' => $this->adminEmail, 'password' => 'pw', 'is_active' => true]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => $this->adminEmail]);
    }

    private function acceptedGap(): CoverageGap
    {
        $course = Course::create(['name' => 'FREQUENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        CourseSource::create(['course_id' => $course->id, 'version' => '1.0', 'blocks' => [
            ['id' => 'p1', 'type' => 'PART', 'text' => 'Parte Prima'],
            ['id' => 'p1-cap1-sec1-p1', 'type' => 'P', 'text' => 'Gli agenti AI percepiscono e agiscono in autonomia.'],
            ['id' => 'p1-cap1-sec1-box1', 'type' => 'BOX', 'text' => 'Nota tattica: anticipa l\'obiezione "ma è sicuro?" con un esempio di compliance.'],
        ]]);
        Module::create(['course_id' => $course->id, 'title' => 'M1', 'content' => '<h2>Intro</h2><p>Testo studente espositivo.</p>', 'sort_order' => 0]);

        return CoverageGap::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'Protocollo A2A',
            'rationale' => 'Comunicazione agent-to-agent.', 'source_url' => 'https://arxiv.org/abs/2501.x', 'status' => 'accepted']);
    }

    private function fakeCompose(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'formatore_html' => '<h3>A2A in aula</h3><p>Spiega il protocollo, anticipa l\'obiezione…</p>',
            'studente_html' => '<h3>Il protocollo A2A</h3><p>Gli agenti comunicano tra loro…</p><div class="nota-normativa">Nota normativa.</div>',
            'note' => 'Verificare la profondità tecnica.',
        ])]]], 200)]);
    }

    // ---- job genera la doppia bozza ----

    public function test_job_genera_bozza_formatore_e_studente(): void
    {
        $gap = $this->acceptedGap();
        $this->fakeCompose();

        (new RunGapComposeJob($gap->id))->handle(app(GapComposer::class));

        $draft = GapDraft::where('coverage_gap_id', $gap->id)->first();
        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft->status);
        $this->assertStringContainsString('A2A in aula', $draft->formatore_html);
        $this->assertStringContainsString('nota-normativa', $draft->studente_html);
        $this->assertNotEmpty($draft->note);
    }

    // ---- VINCOLO DI SICUREZZA: il corso resta INVARIATO ----

    public function test_corso_invariato_dopo_generazione_e_approvazione(): void
    {
        $gap = $this->acceptedGap();
        $courseId = $gap->course_id;

        $sourcesCountBefore = CourseSource::where('course_id', $courseId)->count();
        $blocksBefore = CourseSource::where('course_id', $courseId)->orderByDesc('created_at')->first()->blocks;
        $modulesBefore = Module::where('course_id', $courseId)->orderBy('id')->pluck('content', 'id')->toArray();

        $this->fakeCompose();
        (new RunGapComposeJob($gap->id))->handle(app(GapComposer::class));
        $draft = GapDraft::where('coverage_gap_id', $gap->id)->first();
        $this->actingAdmin()->patch(route('admin.coverage.draft.approve', $draft))->assertRedirect();
        $this->assertSame('approved', $draft->refresh()->status);

        // Nessuna nuova versione del sorgente, blocchi identici.
        $this->assertSame($sourcesCountBefore, CourseSource::where('course_id', $courseId)->count());
        $this->assertEquals($blocksBefore, CourseSource::where('course_id', $courseId)->orderByDesc('created_at')->first()->blocks);
        // modules.content identico.
        $this->assertSame($modulesBefore, Module::where('course_id', $courseId)->orderBy('id')->pluck('content', 'id')->toArray());
        // nessuna sezione formatore live creata.
        $this->assertSame(0, InstructorManualSection::where('course_id', $courseId)->count());
    }

    // ---- editabile + approvabile (senza scrivere nel corso) ----

    public function test_bozza_editabile_e_approvabile(): void
    {
        $gap = $this->acceptedGap();
        $draft = GapDraft::create(['coverage_gap_id' => $gap->id, 'status' => 'draft',
            'formatore_html' => '<p>vecchio</p>', 'studente_html' => '<p>vecchio</p>']);

        $this->actingAdmin()->put(route('admin.coverage.draft.update', $draft), [
            'formatore_html' => '<p>corretto a mano</p>', 'studente_html' => '<p>studente corretto</p>',
        ])->assertRedirect();
        $this->assertStringContainsString('corretto a mano', $draft->refresh()->formatore_html);

        $this->actingAdmin()->patch(route('admin.coverage.draft.approve', $draft))->assertRedirect();
        $draft->refresh();
        $this->assertSame('approved', $draft->status);
        $this->assertNotNull($draft->reviewed_by);
        $this->assertNotNull($draft->reviewed_at);
        // safety: nessuna scrittura nel corso.
        $this->assertSame(0, InstructorManualSection::where('course_id', $gap->course_id)->count());
    }

    // ---- isolamento errore LLM ----

    public function test_errore_llm_isolato_marca_failed(): void
    {
        $gap = $this->acceptedGap();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);

        (new RunGapComposeJob($gap->id))->handle(app(GapComposer::class)); // non lancia

        $draft = GapDraft::where('coverage_gap_id', $gap->id)->first();
        $this->assertSame('failed', $draft->status);
        $this->assertStringContainsString('credit balance is too low', $draft->error);
        $this->assertNull($draft->formatore_html);
    }

    // ---- genera solo su gap accepted ----

    public function test_genera_solo_su_gap_accepted(): void
    {
        $gap = $this->acceptedGap();
        $gap->update(['status' => 'suggested']);
        $this->actingAdmin()->post(route('admin.coverage.generate', $gap))->assertStatus(422);
        $this->assertSame(0, GapDraft::count());
    }

    // ---- gating ----

    public function test_gating_off_404(): void
    {
        $gap = $this->acceptedGap();
        config(['services.p26.enabled' => false]);
        $this->actingAdmin()->post(route('admin.coverage.generate', $gap))->assertNotFound();
    }
}
