<?php

namespace Tests\Feature\P26;

use App\Models\Admin;
use App\Models\TrustedSource;
use App\Services\SourceSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P26 Fase 0 — SourceSuggester: propone candidate `suggested`+`proposed_by='agent'`, dedup
 * robusto (salta esistenti in QUALSIASI stato, incluse rejected), e isolamento totale
 * (errore LLM → eccezione col messaggio reale, registro intatto).
 */
class SourceSuggesterTest extends TestCase
{
    use RefreshDatabase;

    private function fakeLlm(array $sources): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['sources' => $sources])]],
        ], 200)]);
    }

    public function test_suggest_crea_candidate_suggested_agent_normalizzate(): void
    {
        $this->fakeLlm([
            ['label' => 'arXiv', 'url_or_domain' => 'https://www.arxiv.org/', 'mode' => 'search', 'notes' => 'preprint'],
            ['label' => 'AI Act', 'url_or_domain' => 'https://eur-lex.europa.eu/ai', 'mode' => 'fetch'],
        ]);

        $res = app(SourceSuggester::class)->suggest('agenti-ai');

        $this->assertSame(2, $res['created']);
        $this->assertSame(2, TrustedSource::where('status', 'suggested')->where('proposed_by', 'agent')->count());
        $this->assertSame('arxiv.org', TrustedSource::where('mode', 'search')->first()->url_or_domain); // normalizzato
    }

    public function test_dedup_salta_esistenti_incluse_rejected(): void
    {
        // Una fonte già RIFIUTATA non deve essere ri-proposta.
        TrustedSource::create([
            'label' => 'arXiv', 'url_or_domain' => 'arxiv.org', 'mode' => 'search',
            'topic' => 'agenti-ai', 'status' => 'rejected', 'proposed_by' => 'admin',
        ]);
        $this->fakeLlm([
            ['label' => 'arXiv', 'url_or_domain' => 'arxiv.org', 'mode' => 'search'],     // duplicato (rejected) → skip
            ['label' => 'OpenReview', 'url_or_domain' => 'openreview.net', 'mode' => 'search'], // nuova
        ]);

        $res = app(SourceSuggester::class)->suggest('agenti-ai');

        $this->assertSame(1, $res['created']);
        $this->assertSame(1, $res['skipped']);
        // La rifiutata resta rifiutata (non ri-proposta come suggested).
        $this->assertSame('rejected', TrustedSource::where('url_or_domain', 'arxiv.org')->first()->status);
        $this->assertSame(2, TrustedSource::count());
    }

    public function test_errore_llm_isolato_non_scrive_e_porta_messaggio_reale(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low'],
        ], 400)]);

        try {
            app(SourceSuggester::class)->suggest('agenti-ai');
            $this->fail('Attesa RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('proposta fonti', $e->getMessage());
            $this->assertStringContainsString('credit balance is too low', $e->getMessage());
        }
        $this->assertSame(0, TrustedSource::count()); // registro intatto
    }

    public function test_endpoint_proponi_errore_non_rompe_il_registro(): void
    {
        config(['services.p26.enabled' => true]);
        Admin::create(['name' => 'Rev', 'email' => 'rev@ente.it', 'password' => 'pw', 'is_active' => true]);
        $admin = $this->withSession(['admin_logged_in' => true, 'admin_email' => 'rev@ente.it']);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'boom']], 400)]);

        $admin->post(route('admin.sources.suggest'), ['topic' => 'agenti-ai'])
            ->assertRedirect()->assertSessionHas('error');

        // Il CRUD continua a funzionare nonostante il fallimento del suggester.
        $admin->get(route('admin.sources.index'))->assertOk();
    }
}
