<?php

namespace Tests\Feature\Freshness;

use App\Jobs\RunFreshnessAgentJob;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\UpdateProposal;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P25.3d — Tasto manuale (job async) + scheduler (selezione corsi scaduti, cap).
 * Vincolo: né tasto né scheduler APPLICANO — solo generano proposte pending.
 */
class FreshnessSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $name = 'Corso'): Course
    {
        return Course::create(['name' => $name, 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function config(Course $course, string $cadence, ?string $lastRunAt): void
    {
        CourseFreshnessConfig::create([
            'course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [],
            'audience' => 'adult', 'proposals_enabled' => true,
            'cadence' => $cadence, 'last_run_at' => $lastRunAt,
        ]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it']);
    }

    /** Fake delle 3 fasi dell'agente (Fase1 → 1 claim obsoleto → Fase2 obsoleto → Fase3 after). */
    private function fakeAgent(): void
    {
        Http::fake(function ($request) {
            $s = $request['system'] ?? '';
            if (str_contains($s, 'analista di obsolescenza')) {
                return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['claims' => [
                    ['block_id' => 'b1', 'quote' => 'dato 2025', 'category' => 'data'],
                ]], JSON_UNESCAPED_UNICODE)]]], 200);
            }
            if (str_contains($s, 'editor didattico')) {
                return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['after' => 'dato 2026', 'reason' => 'aggiornato'])]]], 200);
            }
            return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['verdict' => 'obsoleto', 'source_url' => 'https://x', 'source_type' => 'web', 'source_date' => '2026-01-01', 'confidence' => 0.8])]]], 200);
        });
    }

    private function sourceWithClaim(Course $course): void
    {
        CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [
            ['id' => 'b1', 'type' => 'P', 'text' => 'Il dato 2025 è questo.'],
        ]]);
    }

    // ---- Tasto manuale: dispatch async ----

    public function test_tasto_dispatcha_job_async_non_sincrono(): void
    {
        Queue::fake();
        $course = $this->course();

        $this->actingAdmin()
            ->post(route('admin.freshness.proposals.run'), ['course_id' => $course->id])
            ->assertRedirect();

        Queue::assertPushed(RunFreshnessAgentJob::class, fn ($job) => $job->courseId === $course->id);
        // Nessuna proposta creata sincronamente (il job è in coda, non eseguito).
        $this->assertDatabaseCount('update_proposals', 0);
    }

    // ---- Scheduler: selezione corsi scaduti ----

    public function test_scheduler_seleziona_solo_i_corsi_scaduti(): void
    {
        Queue::fake();

        $due1 = $this->course('Scaduto mensile');
        $this->config($due1, 'monthly', now()->subMonths(2)->toDateTimeString()); // scaduto

        $due2 = $this->course('Mai eseguito');
        $this->config($due2, 'weekly', null); // mai → scaduto

        $fresh = $this->course('Fresco');
        $this->config($fresh, 'monthly', now()->subDays(3)->toDateTimeString()); // non scaduto

        $off = $this->course('Disattivato');
        $this->config($off, 'off', null); // off → ignorato

        $this->artisan('freshness:run-due')->assertExitCode(0);

        Queue::assertPushed(RunFreshnessAgentJob::class, fn ($j) => $j->courseId === $due1->id);
        Queue::assertPushed(RunFreshnessAgentJob::class, fn ($j) => $j->courseId === $due2->id);
        Queue::assertNotPushed(RunFreshnessAgentJob::class, fn ($j) => $j->courseId === $fresh->id);
        Queue::assertNotPushed(RunFreshnessAgentJob::class, fn ($j) => $j->courseId === $off->id);
        Queue::assertPushed(RunFreshnessAgentJob::class, 2);
    }

    public function test_scheduler_rispetta_il_cap(): void
    {
        Queue::fake();
        // 6 corsi scaduti, cap 3 → solo 3 lanciati.
        for ($i = 0; $i < 6; $i++) {
            $c = $this->course("Scaduto {$i}");
            $this->config($c, 'weekly', null);
        }

        $this->artisan('freshness:run-due', ['--limit' => 3])->assertExitCode(0);

        Queue::assertPushed(RunFreshnessAgentJob::class, 3);
    }

    // ---- Cadenza rispettata: last_run_at aggiornato dal job ----

    public function test_job_aggiorna_last_run_at(): void
    {
        $this->fakeAgent();
        $course = $this->course();
        $this->config($course, 'monthly', now()->subMonths(2)->toDateTimeString());
        $this->sourceWithClaim($course);

        (new RunFreshnessAgentJob($course->id))->handle(app(FreshnessAgent::class));

        $cfg = CourseFreshnessConfig::where('course_id', $course->id)->first();
        $this->assertNotNull($cfg->last_run_at);
        $this->assertTrue($cfg->last_run_at->greaterThan(now()->subMinute()));
    }

    // ---- Vincolo: il job GENERA ma NON applica ----

    public function test_job_genera_proposte_ma_non_applica(): void
    {
        $this->fakeAgent();
        $course = $this->course();
        $this->sourceWithClaim($course);

        (new RunFreshnessAgentJob($course->id))->handle(app(FreshnessAgent::class));

        // Proposta generata e PENDING; nessuna applicata; nessuna nuova versione del sorgente.
        $proposals = UpdateProposal::where('course_id', $course->id)->get();
        $this->assertCount(1, $proposals);
        $this->assertSame('pending', $proposals->first()->status);
        $this->assertSame(0, UpdateProposal::where('status', 'applied')->count());
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count()); // niente v2.1
    }

    // ---- Cadenza: il default colonna è 'off' (opt-in) ----

    public function test_default_cadenza_e_off(): void
    {
        $course = $this->course();
        $cfg = CourseFreshnessConfig::create([
            'course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [],
        ]);

        $this->assertSame('off', $cfg->refresh()->cadence);
    }
}
