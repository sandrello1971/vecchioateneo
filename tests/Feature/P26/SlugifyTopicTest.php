<?php

namespace Tests\Feature\P26;

use App\Models\Admin;
use App\Models\TrustedSource;
use App\Services\SourceSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fix — i topic delle fonti sono SEMPRE slugificati (come i topic dei corsi), così lo Scout li
 * fa combaciare: "AI Act" (corso → ai-act) deve trovare le fonti "AI Act" → ai-act.
 */
class SlugifyTopicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.p26.enabled' => true]);
        Admin::create(['name' => 'Rev', 'email' => 'rev@ente.it', 'password' => 'pw', 'is_active' => true]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'rev@ente.it']);
    }

    public function test_store_slugifica_il_topic(): void
    {
        $this->actingAdmin()->post(route('admin.sources.store'), [
            'label' => 'EUR-Lex', 'url_or_domain' => 'eur-lex.europa.eu', 'mode' => 'search', 'topic' => 'AI Act',
        ])->assertRedirect();

        $this->assertSame('ai-act', TrustedSource::first()->topic);
    }

    public function test_suggester_slugifica_il_topic_delle_fonti_proposte(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'sources' => [['label' => 'arXiv', 'url_or_domain' => 'arxiv.org', 'mode' => 'search']],
        ])]]], 200)]);

        app(SourceSuggester::class)->suggest('AI Act');

        $this->assertSame('ai-act', TrustedSource::first()->topic);
    }
}
