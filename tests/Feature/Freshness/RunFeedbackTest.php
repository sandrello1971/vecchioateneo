<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\FreshnessRun;
use App\Services\Freshness\FreshnessClaimExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feedback a schermo dell'esito dei controlli (run async):
 *  A) l'errore Anthropic porta il CORPO della risposta (es. "credit balance too low"), non solo "HTTP 400".
 *  B) l'admin mostra un pannello "Ultimi controlli" con stato + motivo dei run falliti.
 */
class RunFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        return ['admin_logged_in' => true, 'admin_email' => 'a@ente.it'];
    }

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    // ---- A) il motivo riporta il corpo dell'errore API ----

    public function test_errore_fase1_include_il_messaggio_anthropic(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'type' => 'error',
            'error' => ['type' => 'invalid_request_error',
                        'message' => 'Your credit balance is too low to access the Anthropic API.'],
        ], 400)]);

        try {
            app(FreshnessClaimExtractor::class)->extract([
                ['id' => 'b1', 'type' => 'P', 'text' => 'Nel 2024 il mercato vale 1 miliardo di euro.'],
            ]);
            $this->fail('Attesa RuntimeException per HTTP 400.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Fase 1', $e->getMessage());
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringContainsString('credit balance is too low', $e->getMessage());
        }
    }

    // ---- B) il pannello a schermo ----

    public function test_pannello_mostra_run_fallito_con_motivo(): void
    {
        $course = $this->makeCourse();
        FreshnessRun::create([
            'course_id' => $course->id, 'status' => 'failed', 'started_at' => now(), 'finished_at' => now(),
            'claims_found' => 0, 'proposals_created' => 0,
            'failure_reason' => 'Anthropic API errore Fase 1: HTTP 400 — Your credit balance is too low to access the Anthropic API.',
        ]);

        $this->withSession($this->admin())
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertSee('Storico analisi')
            ->assertSee('Fallito')
            ->assertSee('credit balance is too low'); // il motivo è visibile a schermo
    }

    public function test_pannello_mostra_completato_con_conteggi(): void
    {
        $course = $this->makeCourse();
        FreshnessRun::create([
            'course_id' => $course->id, 'status' => 'completed', 'started_at' => now(), 'finished_at' => now(),
            'claims_found' => 3, 'proposals_created' => 2, 'failure_reason' => null,
        ]);

        $this->withSession($this->admin())
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertSee('Completato')
            ->assertSee('3 claim, 2 proposte');
    }

    public function test_storico_mostra_empty_state_senza_run(): void
    {
        // Lo storico è uno spazio DEDICATO sempre presente: senza run mostra l'empty-state.
        $this->withSession($this->admin())
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertSee('Storico analisi')
            ->assertSee('Nessuna analisi ancora');
    }

    // ---- C) notifiche dismissibili + indicatore live ----

    public function test_notifiche_sono_dismissibili_e_banner_live_presente(): void
    {
        $this->withSession($this->admin() + ['success' => 'Controllo avviato.'])
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertSee('data-dismiss-flash', false)   // X di chiusura sulle notifiche
            ->assertSee('id="run-live-banner"', false) // banner "analisi in corso"
            ->assertSee('stato-run', false);            // endpoint di polling cablato
    }

    public function test_endpoint_stato_run_segnala_analisi_in_corso(): void
    {
        $course = $this->makeCourse();
        FreshnessRun::create([
            'course_id' => $course->id, 'status' => 'running', 'started_at' => now(),
            'claims_found' => 0, 'proposals_created' => 0,
        ]);

        $res = $this->withSession($this->admin())
            ->getJson(route('admin.freshness.proposals.runs-status'))
            ->assertOk()
            ->assertJson(['running' => true]);

        $this->assertStringContainsString('INTERFERENZA', $res->json('banner'));
        $this->assertStringContainsString('In corso', $res->json('html'));
    }

    public function test_endpoint_stato_run_nessuna_analisi_in_corso(): void
    {
        $course = $this->makeCourse();
        FreshnessRun::create([
            'course_id' => $course->id, 'status' => 'completed', 'started_at' => now(), 'finished_at' => now(),
            'claims_found' => 1, 'proposals_created' => 0,
        ]);

        $this->withSession($this->admin())
            ->getJson(route('admin.freshness.proposals.runs-status'))
            ->assertOk()
            ->assertJson(['running' => false, 'banner' => null]);
    }

    // ---- D) storico archiviabile (per non riempire lo schermo) ----

    public function test_archivia_run_lo_rimuove_dallo_storico(): void
    {
        $run = FreshnessRun::create([
            'course_id' => $this->makeCourse()->id, 'status' => 'failed', 'started_at' => now(), 'finished_at' => now(),
            'failure_reason' => 'MARKER_DISMISS_TEST', 'claims_found' => 0, 'proposals_created' => 0,
        ]);

        $before = $this->withSession($this->admin())->getJson(route('admin.freshness.proposals.runs-status'));
        $this->assertStringContainsString('MARKER_DISMISS_TEST', $before->json('html'));

        $this->withSession($this->admin())->patch(route('admin.freshness.proposals.run-dismiss', $run))->assertRedirect();
        $this->assertNotNull($run->refresh()->dismissed_at);

        $after = $this->withSession($this->admin())->getJson(route('admin.freshness.proposals.runs-status'));
        $this->assertStringNotContainsString('MARKER_DISMISS_TEST', $after->json('html'));
    }

    public function test_pagina_si_autoaggiorna_a_fine_analisi(): void
    {
        // A fine analisi la pagina si ricarica da sola → le proposte compaiono senza refresh manuale.
        $this->withSession($this->admin())
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertSee('window.location.reload', false);
    }

    public function test_run_in_corso_non_archiviabile(): void
    {
        $run = FreshnessRun::create(['course_id' => $this->makeCourse()->id, 'status' => 'running', 'started_at' => now()]);

        $this->withSession($this->admin())->patch(route('admin.freshness.proposals.run-dismiss', $run))->assertRedirect();
        $this->assertNull($run->refresh()->dismissed_at); // un run in corso non si archivia
    }

    public function test_pulisci_storico_archivia_tutti_i_non_in_corso(): void
    {
        $cid = $this->makeCourse()->id;
        FreshnessRun::create(['course_id' => $cid, 'status' => 'completed', 'started_at' => now(), 'finished_at' => now()]);
        FreshnessRun::create(['course_id' => $cid, 'status' => 'failed', 'started_at' => now(), 'finished_at' => now()]);
        $running = FreshnessRun::create(['course_id' => $cid, 'status' => 'running', 'started_at' => now()]);

        $this->withSession($this->admin())->post(route('admin.freshness.proposals.runs-clear'))->assertRedirect();

        $this->assertSame(2, FreshnessRun::whereNotNull('dismissed_at')->count());
        $this->assertNull($running->refresh()->dismissed_at); // l'in-corso resta
    }
}
