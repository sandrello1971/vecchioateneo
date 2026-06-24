<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseSource;
use App\Models\FreshnessClaim;
use App\Models\UpdateProposal;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * P25.2 — orchestratore end-to-end (Fase 1 + Fase 2) e comando course:freshness-run.
 * Una sola Http::fake distingue Fase 1 (system "analista di obsolescenza") da Fase 2
 * (system "verificatore di attualità") e differenzia i verdetti per claim.
 */
class FreshnessAgentTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(array $attrs = []): Course
    {
        return Course::create(array_merge([
            'name' => 'INTERFERENZA',
            'slug' => 'consilium-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ], $attrs));
    }

    /** Sorgente con i 2 blocchi reali di v2.0 (heading-data + statistiche). */
    private function makeSource(Course $course): CourseSource
    {
        $blocks = json_decode(file_get_contents(base_path('tests/Fixtures/p25/consilium-v2-claim-blocks.json')), true);
        return CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => $blocks]);
    }

    /** Fase 1 → 2 claim; Fase 2 → obsoleto per "1,8 miliardi", attuale altrimenti. */
    private function fakeAgent(): void
    {
        Http::fake(function ($request) {
            $system = $request['system'] ?? '';

            if (str_contains($system, 'analista di obsolescenza')) {
                $claims = ['claims' => [
                    ['block_id' => 'p1-cap3-sec1-p2', 'quote' => 'Il mercato AI italiano vale 1,8 miliardi di euro nel 2025', 'category' => 'prezzo'],
                    ['block_id' => 'p1-cap3', 'quote' => 'AI e PMI italiane nel 2026', 'category' => 'data'],
                ]];
                return Http::response(['content' => [['type' => 'text', 'text' => json_encode($claims, JSON_UNESCAPED_UNICODE)]]], 200);
            }

            // Fase 3 (proposta): editor didattico → after aggiornato.
            if (str_contains($system, 'editor didattico')) {
                $proposal = ['after' => 'Il mercato AI italiano vale 2,3 miliardi di euro nel 2026', 'reason' => 'Dato di mercato aggiornato al 2026'];
                return Http::response(['content' => [['type' => 'text', 'text' => json_encode($proposal, JSON_UNESCAPED_UNICODE)]]], 200);
            }

            // Fase 2: distingui per il contenuto del messaggio utente (il claim).
            $userMsg = $request['messages'][0]['content'] ?? '';
            $verdict = str_contains($userMsg, '1,8 miliardi')
                ? ['verdict' => 'obsoleto', 'source_url' => 'https://istat.it', 'source_type' => 'web', 'source_date' => '2026-04', 'confidence' => 0.85]
                : ['verdict' => 'attuale', 'source_url' => null, 'source_type' => null, 'source_date' => null, 'confidence' => 0.6];

            return Http::response(['content' => [['type' => 'text', 'text' => json_encode($verdict, JSON_UNESCAPED_UNICODE)]]], 200);
        });
    }

    public function test_run_end_to_end_estrae_verifica_e_registra(): void
    {
        $course = $this->makeCourse();
        $this->makeSource($course);
        $this->fakeAgent();

        $run = app(FreshnessAgent::class)->run($course);

        // Run registrata correttamente — 1 proposta dal claim obsoleto (Fase 3).
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->claims_found);
        $this->assertSame(1, $run->proposals_created);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);

        // Claim persistiti con i verdetti attesi
        $obsoleto = FreshnessClaim::where('run_id', $run->id)->where('block_id', 'p1-cap3-sec1-p2')->first();
        $this->assertSame('obsoleto', $obsoleto->verdict);
        $this->assertSame('https://istat.it', $obsoleto->source_url);
        $this->assertSame('web', $obsoleto->source_type);
        $this->assertNotNull($obsoleto->verified_at);

        $attuale = FreshnessClaim::where('run_id', $run->id)->where('block_id', 'p1-cap3')->first();
        $this->assertSame('attuale', $attuale->verdict);

        // La proposta nasce SOLO dal claim obsoleto, status pending, before verbatim.
        $proposals = UpdateProposal::where('course_id', $course->id)->get();
        $this->assertCount(1, $proposals);
        $p = $proposals->first();
        $this->assertSame('pending', $p->status);
        $this->assertSame($obsoleto->id, $p->freshness_claim_id);
        $this->assertSame('Il mercato AI italiano vale 1,8 miliardi di euro nel 2025', $p->before); // = claim_text verbatim
        $this->assertStringContainsString('2026', $p->after);
        $this->assertSame('adult', $p->audience);
        $this->assertFalse($p->after_edited_by_human);
        $this->assertNull($p->reviewed_at);

        // Il sorgente non è stato toccato.
        $this->assertDatabaseCount('course_sources', 1);
    }

    public function test_proposals_disabilitate_non_generano_proposte(): void
    {
        $course = $this->makeCourse();
        $this->makeSource($course);
        \App\Models\CourseFreshnessConfig::create([
            'course_id' => $course->id,
            'web_search_enabled' => true,
            'primary_sources' => [],
            'audience' => 'adult',
            'proposals_enabled' => false, // modalità solo-claim
        ]);
        $this->fakeAgent();

        $run = app(FreshnessAgent::class)->run($course);

        // Claim estratti e verificati, ma NESSUNA proposta generata.
        $this->assertSame(2, $run->claims_found);
        $this->assertSame(0, $run->proposals_created);
        $this->assertDatabaseCount('update_proposals', 0);
        // L'obsoleto è comunque verificato come tale.
        $this->assertSame('obsoleto', FreshnessClaim::where('run_id', $run->id)->where('block_id', 'p1-cap3-sec1-p2')->first()->verdict);
    }

    public function test_run_fallisce_pulita_senza_sorgente(): void
    {
        $course = $this->makeCourse(); // nessun course_sources

        try {
            app(FreshnessAgent::class)->run($course);
            $this->fail('Attesa RuntimeException per sorgente assente.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Nessun course_sources', $e->getMessage());
        }

        $this->assertDatabaseHas('freshness_runs', [
            'course_id' => $course->id,
            'status' => 'failed',
        ]);
    }

    public function test_comando_rifiuta_non_uuid(): void
    {
        $course = $this->makeCourse(['slug' => 'consilium-cmd']);

        $this->artisan('course:freshness-run', ['course_id' => $course->slug])
            ->assertExitCode(1);

        $this->assertDatabaseCount('freshness_runs', 0);
    }

    public function test_comando_end_to_end(): void
    {
        $course = $this->makeCourse();
        $this->makeSource($course);
        $this->fakeAgent();

        $this->artisan('course:freshness-run', ['course_id' => $course->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('freshness_runs', [
            'course_id' => $course->id,
            'status' => 'completed',
            'claims_found' => 2,
            'proposals_created' => 1,
        ]);
        $this->assertDatabaseHas('update_proposals', [
            'course_id' => $course->id,
            'status' => 'pending',
        ]);
    }
}
