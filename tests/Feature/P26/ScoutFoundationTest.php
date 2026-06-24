<?php

namespace Tests\Feature\P26;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CoverageGap;
use App\Models\TrustedSource;
use App\Services\CourseMapExtractor;
use App\Services\GapScout;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P26 Fase A — fondamenta dello Scout: estrazione mappa corso, schema coverage_gaps +
 * topic su config, e i guard del GapScout (no fonti → messaggio, mai web aperto; isolamento).
 * NIENTE persistenza/UI qui (fase successiva).
 */
class ScoutFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function course(?string $topic = null): Course
    {
        $c = Course::create(['name' => 'AGENTI', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        if ($topic !== null) {
            CourseFreshnessConfig::create([
                'course_id' => $c->id, 'web_search_enabled' => true, 'primary_sources' => [],
                'audience' => 'adult', 'topic' => $topic,
            ]);
        }
        return $c;
    }

    // ---- mappa corso ----

    public function test_estrae_heading_e_estratto(): void
    {
        $map = app(CourseMapExtractor::class)->fromBlocks([
            ['id' => 'p1', 'type' => 'PART', 'text' => 'Parte Prima'],
            ['id' => 'p1-cap1', 'type' => 'H1', 'text' => 'Introduzione agli agenti'],
            ['id' => 'p1-cap1-sec1', 'type' => 'H2', 'text' => 'Cos\'è un agente'],
            ['id' => 'p1-cap1-sec1-p1', 'type' => 'P', 'text' => 'Un agente AI percepisce e agisce.'],
            ['id' => 'p1-cap1-sec1-bul1', 'type' => 'BUL', 'items' => ['voce A', 'voce B']],
        ]);

        $this->assertCount(3, $map['headings']); // PART + H1 + H2 (non P/BUL)
        $this->assertSame('PART', $map['headings'][0]['level']);
        $this->assertStringContainsString('Introduzione agli agenti', $map['outline']);
        $this->assertStringContainsString('Un agente AI percepisce', $map['excerpt']);
        $this->assertFalse($map['empty']);
    }

    public function test_mappa_vuota_se_nessun_heading(): void
    {
        $map = app(CourseMapExtractor::class)->fromBlocks([
            ['id' => 'x', 'type' => 'P', 'text' => 'solo prosa'],
        ]);
        $this->assertTrue($map['empty']);
    }

    // ---- schema coverage_gaps + topic config ----

    public function test_coverage_gap_default_suggested_e_check(): void
    {
        $course = $this->course('agenti-ai');
        $this->assertSame('agenti-ai', $course->freshnessConfig->topic);

        $gap = CoverageGap::create([
            'course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'Protocollo A2A',
            'rationale' => 'Comunicazione agent-to-agent emergente.', 'source_url' => 'https://arxiv.org/abs/x',
            'source_label' => 'arxiv.org', 'confidence' => 0.4,
        ]);
        $this->assertSame('suggested', $gap->refresh()->status);

        $this->expectException(QueryException::class);
        CoverageGap::create(['course_id' => $course->id, 'topic' => 't', 'title' => 'x',
            'rationale' => 'y', 'status' => 'bogus']); // CHECK status
    }

    // ---- guard GapScout: niente fonti → messaggio, MAI web aperto ----

    public function test_scout_senza_topic_ritorna_no_sources_senza_web(): void
    {
        Http::fake();
        $res = app(GapScout::class)->scout($this->course(null)); // nessun topic
        $this->assertTrue($res['no_sources']);
        Http::assertNothingSent();
    }

    public function test_scout_topic_senza_fonti_approvate_ritorna_no_sources_senza_web(): void
    {
        Http::fake();
        $course = $this->course('agenti-ai');
        // Una fonte solo 'suggested' NON conta: lo Scout cerca solo nelle approved.
        TrustedSource::create(['label' => 'x', 'url_or_domain' => 'arxiv.org', 'mode' => 'search',
            'topic' => 'agenti-ai', 'status' => 'suggested', 'proposed_by' => 'agent']);

        $res = app(GapScout::class)->scout($course);

        $this->assertTrue($res['no_sources']);
        Http::assertNothingSent(); // nessun fallback al web aperto
    }

    // ---- isolamento: errore LLM → eccezione col messaggio reale ----

    public function test_scout_errore_llm_isolato_messaggio_reale(): void
    {
        $course = $this->course('agenti-ai');
        TrustedSource::create(['label' => 'arXiv', 'url_or_domain' => 'arxiv.org', 'mode' => 'search',
            'topic' => 'agenti-ai', 'status' => 'approved', 'proposed_by' => 'admin']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'error' => ['message' => 'Your credit balance is too low'],
        ], 400)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('credit balance is too low');
        app(GapScout::class)->scout($course);
    }
}
