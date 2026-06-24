<?php

namespace Tests\Feature\P26;

use App\Jobs\RunGapScoutJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\CourseTopic;
use App\Models\CoverageGap;
use App\Models\TrustedSource;
use App\Services\GapScout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P26.2 — Scout multi-topic: unione fonti, gap etichettati per provenienza (topic + peso),
 * topic senza fonti segnalati, ordine primary-first, gestione multi-topic, retrocompat, gating.
 */
class ScoutMultiTopicTest extends TestCase
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

    private function course(): Course
    {
        $c = Course::create(['name' => 'CIRCUITO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        CourseSource::create(['course_id' => $c->id, 'version' => '1.0', 'blocks' => [
            ['id' => 'p1', 'type' => 'H1', 'text' => 'Introduzione'],
        ]]);
        return $c;
    }

    private function source(string $topic, string $domain): void
    {
        TrustedSource::create(['label' => $domain, 'url_or_domain' => $domain, 'mode' => 'search',
            'topic' => $topic, 'status' => 'approved', 'proposed_by' => 'admin']);
    }

    private function fakeGaps(array $gaps): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['gaps' => $gaps])]],
        ], 200)]);
    }

    // ---- unione fonti + etichettatura per provenienza ----

    public function test_scout_unisce_fonti_e_etichetta_gap_per_topic_e_peso(): void
    {
        $course = $this->course();
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'weight' => 'primary']);
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'knowledge-mgmt', 'weight' => 'secondary']);
        $this->source('agenti-ai', 'arxiv.org');
        $this->source('knowledge-mgmt', 'obsidian.md');

        $this->fakeGaps([
            ['title' => 'Gap A2A', 'rationale' => 'x', 'source_url' => 'https://arxiv.org/abs/1', 'confidence' => 0.8],
            ['title' => 'Gap PKM', 'rationale' => 'y', 'source_url' => 'https://obsidian.md/page', 'confidence' => 0.4],
        ]);

        (new RunGapScoutJob($course->id))->handle(app(GapScout::class));

        $a = CoverageGap::where('title', 'Gap A2A')->first();
        $this->assertSame('agenti-ai', $a->source_topic);
        $this->assertSame('primary', $a->source_weight);

        $b = CoverageGap::where('title', 'Gap PKM')->first();
        $this->assertSame('knowledge-mgmt', $b->source_topic);
        $this->assertSame('secondary', $b->source_weight);
    }

    // ---- topic senza fonti: segnalato, non rompe (cerca negli altri) ----

    public function test_topic_senza_fonti_segnalato_scout_cerca_negli_altri(): void
    {
        $course = $this->course();
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'weight' => 'primary']);
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'senza-fonti', 'weight' => 'secondary']);
        $this->source('agenti-ai', 'arxiv.org'); // solo questo ha fonti
        $this->fakeGaps([['title' => 'Gap', 'rationale' => 'x', 'source_url' => 'https://arxiv.org/1', 'confidence' => 0.6]]);

        $res = app(GapScout::class)->scout($course);

        $this->assertArrayNotHasKey('no_sources', $res);
        $this->assertContains('senza-fonti', $res['topics_without_sources']);
        $this->assertNotEmpty($res['gaps']);
    }

    // ---- gestione multi-topic (setTopics): pivot pesata, dedup, un solo primary ----

    public function test_set_topics_pivot_dedup_e_un_solo_primary(): void
    {
        $course = $this->course();

        $this->actingAdmin()->post(route('admin.coverage.topics', $course), [
            'topics' => ['Agenti AI', 'agenti-ai', 'Knowledge Mgmt'], // dup → 1
            'weights' => ['primary', 'primary', 'secondary'],          // 2 primary → 1
        ])->assertRedirect();

        $topics = CourseTopic::where('course_id', $course->id)->get();
        $this->assertCount(2, $topics);
        $this->assertSame('agenti-ai', $topics->where('weight', 'primary')->pluck('topic')->first());
        $this->assertCount(1, $topics->where('weight', 'primary'));
        $this->assertSame('knowledge-mgmt', $topics->where('weight', 'secondary')->pluck('topic')->first());
    }

    // ---- UI: badge provenienza + ordine primary-first + filtro ----

    public function test_show_ordina_primary_prima_e_mostra_badge(): void
    {
        $course = $this->course();
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'weight' => 'primary']);
        CoverageGap::create(['course_id' => $course->id, 'topic' => 'knowledge-mgmt', 'title' => 'SECONDARY_GAP',
            'rationale' => 'y', 'source_topic' => 'knowledge-mgmt', 'source_weight' => 'secondary', 'confidence' => 0.9, 'status' => 'suggested']);
        CoverageGap::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'PRIMARY_GAP',
            'rationale' => 'x', 'source_topic' => 'agenti-ai', 'source_weight' => 'primary', 'confidence' => 0.3, 'status' => 'suggested']);

        $html = $this->actingAdmin()->get(route('admin.coverage.show', $course))->assertOk()
            ->assertSee('★ principale')->assertSee('agenti-ai')->getContent();

        // primary-first nonostante confidenza più bassa
        $this->assertTrue(mb_strpos($html, 'PRIMARY_GAP') < mb_strpos($html, 'SECONDARY_GAP'));
    }

    public function test_filtro_gap_per_topic(): void
    {
        $course = $this->course();
        CoverageGap::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'GAP_AI',
            'rationale' => 'x', 'source_topic' => 'agenti-ai', 'source_weight' => 'primary', 'status' => 'suggested']);
        CoverageGap::create(['course_id' => $course->id, 'topic' => 'pkm', 'title' => 'GAP_PKM',
            'rationale' => 'y', 'source_topic' => 'pkm', 'source_weight' => 'secondary', 'status' => 'suggested']);

        $this->actingAdmin()->get(route('admin.coverage.show', ['course' => $course->id, 'gap_topic' => 'agenti-ai']))
            ->assertOk()->assertSee('GAP_AI')->assertDontSee('GAP_PKM');
    }

    // ---- retrocompat: topic singolo (legacy) si vede come 1 primary ----

    public function test_retrocompat_legacy_single_topic_come_primary(): void
    {
        $course = $this->course();
        CourseFreshnessConfig::create(['course_id' => $course->id, 'web_search_enabled' => true,
            'primary_sources' => [], 'audience' => 'adult', 'topic' => 'agenti-ai']); // legacy, nessuna pivot

        $this->assertEquals([['topic' => 'agenti-ai', 'weight' => 'primary']], $course->effectiveTopics()->all());
        $this->actingAdmin()->get(route('admin.coverage.show', $course))->assertOk()
            ->assertSee('value="agenti-ai"', false);
    }

    // ---- suggester multi via UI + gating ----

    public function test_suggest_topics_action_flasha_proposta(): void
    {
        $course = $this->course();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'topics' => [['topic' => 'agenti-ai', 'weight' => 'primary'], ['topic' => 'pkm', 'weight' => 'secondary']],
        ])]]], 200)]);

        $this->actingAdmin()->post(route('admin.coverage.topics.suggest', $course))
            ->assertRedirect()->assertSessionHas('topics_suggestion');
        $this->assertSame(0, CourseTopic::where('course_id', $course->id)->count()); // niente salvato
    }

    public function test_gating_off_404(): void
    {
        $course = $this->course();
        config(['services.p26.enabled' => false]);
        $this->actingAdmin()->post(route('admin.coverage.topics', $course), ['topics' => ['x']])->assertNotFound();
    }
}
