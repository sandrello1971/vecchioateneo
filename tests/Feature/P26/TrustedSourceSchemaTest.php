<?php

namespace Tests\Feature\P26;

use App\Models\TrustedSource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P26 Fase 0 — validazione schema/modello del registro fonti (solo fondamenta:
 * default, scope, UNIQUE anti-doppione, CHECK su mode/status/proposed_by).
 */
class TrustedSourceSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attrs = []): TrustedSource
    {
        return TrustedSource::create(array_merge([
            'label' => 'arXiv — cs.AI',
            'url_or_domain' => 'arxiv.org',
            'mode' => 'search',
            'topic' => 'agenti-ai',
        ], $attrs));
    }

    public function test_default_status_suggested_e_origine_admin(): void
    {
        $s = $this->make();
        $this->assertSame('suggested', $s->refresh()->status);
        $this->assertSame('admin', $s->proposed_by);
        $this->assertNull($s->reviewed_at);
    }

    public function test_scope_topic_e_status(): void
    {
        $this->make(['url_or_domain' => 'arxiv.org', 'mode' => 'search', 'status' => 'approved']);
        $this->make(['url_or_domain' => 'ec.europa.eu/ai-act', 'mode' => 'fetch', 'status' => 'suggested']);
        $this->make(['topic' => 'compliance', 'url_or_domain' => 'gdpr.eu', 'mode' => 'fetch', 'status' => 'approved']);

        $this->assertSame(2, TrustedSource::topic('agenti-ai')->count());
        $this->assertSame(1, TrustedSource::topic('agenti-ai')->approved()->count());
        $this->assertSame(2, TrustedSource::approved()->count());
        $this->assertSame(1, TrustedSource::suggested()->count());
    }

    public function test_unique_su_topic_url_mode_impedisce_doppioni(): void
    {
        $this->make();
        $this->expectException(QueryException::class);
        $this->make(); // stesso (topic, url_or_domain, mode) → respinto
    }

    public function test_check_constraint_rifiuta_mode_invalido(): void
    {
        $this->expectException(QueryException::class);
        $this->make(['mode' => 'crawl']); // non in (search, fetch)
    }
}
