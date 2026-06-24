<?php

namespace Tests\Feature\P26;

use App\Jobs\RunGapScoutJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CoverageGap;
use App\Models\GapScoutRun;
use App\Models\TrustedSource;
use App\Services\GapScout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P26 Fase A — persistenza async (RunGapScoutJob → coverage_gaps + gap_scout_runs) e UI Scout
 * (analizza con guard, accetta/scarta con audit, gating). Cerca solo nelle fonti approvate.
 */
class CoverageScoutTest extends TestCase
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

    private function course(?string $topic = 'agenti-ai', bool $approvedSource = true): Course
    {
        $c = Course::create(['name' => 'AGENTI', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        if ($topic !== null) {
            CourseFreshnessConfig::create(['course_id' => $c->id, 'web_search_enabled' => true,
                'primary_sources' => [], 'audience' => 'adult', 'topic' => $topic]);
            if ($approvedSource) {
                TrustedSource::create(['label' => 'arXiv', 'url_or_domain' => 'arxiv.org', 'mode' => 'search',
                    'topic' => $topic, 'status' => 'approved', 'proposed_by' => 'admin']);
            }
        }
        return $c;
    }

    private function fakeGaps(array $gaps): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['gaps' => $gaps])]],
        ], 200)]);
    }

    // ---- job: salva i gap suggested con fonte+confidenza ----

    public function test_job_salva_gap_suggested_con_fonte_e_confidenza(): void
    {
        $course = $this->course();
        $this->fakeGaps([
            ['title' => 'Protocollo A2A', 'rationale' => 'agent-to-agent emergente', 'source_url' => 'https://arxiv.org/abs/2501.x', 'confidence' => 0.7],
            ['title' => 'Tema fuori taglio', 'rationale' => 'avanzato, fuori scope del corso', 'source_url' => 'https://arxiv.org/abs/2502.y', 'confidence' => 0.1],
        ]);

        (new RunGapScoutJob($course->id))->handle(app(GapScout::class));

        $this->assertSame(2, CoverageGap::where('course_id', $course->id)->where('status', 'suggested')->count());
        $a2a = CoverageGap::where('title', 'Protocollo A2A')->first();
        $this->assertSame('agenti-ai', $a2a->topic);
        $this->assertSame('https://arxiv.org/abs/2501.x', $a2a->source_url);
        $this->assertSame('arxiv.org', $a2a->source_label); // host derivato
        $this->assertEqualsWithDelta(0.7, $a2a->confidence, 0.001);
        // Gate "taglio": il gap fuori-scope conserva la confidenza BASSA fornita dal modello.
        $this->assertEqualsWithDelta(0.1, CoverageGap::where('title', 'Tema fuori taglio')->first()->confidence, 0.001);
        // run tracciata
        $run = GapScoutRun::where('course_id', $course->id)->first();
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->gaps_found);
    }

    public function test_job_no_sources_completa_senza_gap_e_senza_web(): void
    {
        Http::fake();
        $course = $this->course('agenti-ai', approvedSource: false); // topic ma nessuna approvata

        (new RunGapScoutJob($course->id))->handle(app(GapScout::class));

        $this->assertSame(0, CoverageGap::count());
        Http::assertNothingSent();
        $run = GapScoutRun::first();
        $this->assertSame('completed', $run->status);
        $this->assertSame(0, $run->gaps_found);
    }

    public function test_job_fallimento_llm_registra_motivo_reale(): void
    {
        $course = $this->course();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);

        (new RunGapScoutJob($course->id))->handle(app(GapScout::class));

        $this->assertSame(0, CoverageGap::count());
        $run = GapScoutRun::first();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('credit balance is too low', $run->failure_reason);
    }

    // ---- accetta / scarta con audit ----

    public function test_accetta_e_scarta_aggiornano_status_con_audit(): void
    {
        $course = $this->course();
        $g = CoverageGap::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'X', 'rationale' => 'y', 'confidence' => 0.5]);

        $this->actingAdmin()->patch(route('admin.coverage.accept', $g))->assertRedirect();
        $g->refresh();
        $this->assertSame('accepted', $g->status);
        $this->assertNotNull($g->reviewed_by);
        $this->assertNotNull($g->reviewed_at);

        $g2 = CoverageGap::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'Z', 'rationale' => 'y']);
        $this->actingAdmin()->patch(route('admin.coverage.dismiss', $g2))->assertRedirect();
        $this->assertSame('dismissed', $g2->refresh()->status);
    }

    // ---- analyze: guard topic/fonti, niente dispatch ----

    public function test_analyze_senza_topic_o_fonti_non_dispatcha(): void
    {
        Queue::fake();
        $noTopic = $this->course(null);
        $this->actingAdmin()->post(route('admin.coverage.analyze', $noTopic))->assertRedirect()->assertSessionHas('error');

        $noSources = $this->course('agenti-ai', approvedSource: false);
        $this->actingAdmin()->post(route('admin.coverage.analyze', $noSources))->assertRedirect()->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_analyze_con_topic_e_fonti_dispatcha(): void
    {
        Queue::fake();
        $course = $this->course();
        $this->actingAdmin()->post(route('admin.coverage.analyze', $course))->assertRedirect()->assertSessionHas('success');
        Queue::assertPushed(RunGapScoutJob::class, fn ($j) => $j->courseId === $course->id);
    }

    // ---- set topic solo da select esistente (anti-drift) ----

    public function test_set_topic_normalizza_a_slug_e_accetta_anche_nuovi(): void
    {
        // P26.1: il topic è uno slug normalizzato e può essere NUOVO (anti-drift è nel suggeritore).
        $course = $this->course(null);
        $this->actingAdmin()->post(route('admin.coverage.topic', $course), ['topic' => 'Elettronica Industriale'])->assertRedirect();
        $this->assertSame('elettronica-industriale', $course->fresh()->freshnessConfig->topic);
    }

    // ---- prompt: considera il taglio/pubblico ----

    public function test_prompt_scout_considera_taglio_e_pubblico(): void
    {
        $prompt = (new \ReflectionClass(GapScout::class))->getConstant('SYSTEM_PROMPT');
        $this->assertStringContainsString('TAGLIO E PUBBLICO', $prompt);
        $this->assertStringContainsString('FUORI dal taglio', $prompt);
    }

    // ---- rendering UI (smoke) ----

    public function test_show_page_rende_gap_con_fonte_e_confidenza(): void
    {
        $course = $this->course();
        CoverageGap::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'Protocollo A2A',
            'rationale' => 'agent-to-agent', 'source_url' => 'https://arxiv.org/abs/x', 'source_label' => 'arxiv.org', 'confidence' => 0.42]);

        $this->actingAdmin()->get(route('admin.coverage.show', $course))
            ->assertOk()
            ->assertSee('Protocollo A2A')
            ->assertSee('conf 0.42')
            ->assertSee('arxiv.org')
            ->assertSee('Analizza copertura');
    }

    public function test_index_lista_corsi(): void
    {
        $this->course();
        $this->actingAdmin()->get(route('admin.coverage.index'))->assertOk()->assertSee('Copertura corsi');
    }

    // ---- gating ----

    public function test_gating_off_coverage_404(): void
    {
        config(['services.p26.enabled' => false]);
        $this->actingAdmin()->get(route('admin.coverage.index'))->assertNotFound();
    }
}
