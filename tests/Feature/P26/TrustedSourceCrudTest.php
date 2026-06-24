<?php

namespace Tests\Feature\P26;

use App\Models\Admin;
use App\Models\TrustedSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P26 Fase 0 — CRUD admin del registro fonti: store manuale (approved+audit), approva/rifiuta
 * con audit, rimozione, filtri topic/status, validazione/normalizzazione input, e gating.
 */
class TrustedSourceCrudTest extends TestCase
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

    private function source(array $attrs = []): TrustedSource
    {
        return TrustedSource::create(array_merge([
            'label' => 'arXiv', 'url_or_domain' => 'arxiv.org', 'mode' => 'search',
            'topic' => 'agenti-ai', 'status' => 'suggested', 'proposed_by' => 'agent',
        ], $attrs));
    }

    // ---- store manuale ----

    public function test_store_manuale_crea_approved_con_audit_e_normalizza_dominio(): void
    {
        $this->actingAdmin()->post(route('admin.sources.store'), [
            'label' => 'arXiv', 'url_or_domain' => 'https://www.arxiv.org/list/cs.AI', 'mode' => 'search', 'topic' => 'agenti-ai',
        ])->assertRedirect()->assertSessionHas('success');

        $s = TrustedSource::first();
        $this->assertSame('approved', $s->status);       // admin a mano = atto di fiducia
        $this->assertSame('admin', $s->proposed_by);
        $this->assertSame('arxiv.org', $s->url_or_domain); // schema/path/www tolti
        $this->assertNotNull($s->reviewed_by);
        $this->assertNotNull($s->reviewed_at);
    }

    // ---- validazione/normalizzazione ----

    public function test_fetch_richiede_url_valido(): void
    {
        $this->actingAdmin()->post(route('admin.sources.store'), [
            'label' => 'AI Act', 'url_or_domain' => 'non-un-url', 'mode' => 'fetch', 'topic' => 'compliance',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, TrustedSource::count());
    }

    public function test_fetch_url_valido_entra_intatto(): void
    {
        $this->actingAdmin()->post(route('admin.sources.store'), [
            'label' => 'AI Act', 'url_or_domain' => 'https://eur-lex.europa.eu/ai-act', 'mode' => 'fetch', 'topic' => 'compliance',
        ])->assertRedirect();
        $this->assertSame('https://eur-lex.europa.eu/ai-act', TrustedSource::first()->url_or_domain);
    }

    public function test_search_dominio_malformato_respinto(): void
    {
        $this->actingAdmin()->post(route('admin.sources.store'), [
            'label' => 'x', 'url_or_domain' => 'non valido con spazi', 'mode' => 'search', 'topic' => 't',
        ])->assertSessionHas('error');
        $this->assertSame(0, TrustedSource::count());
    }

    // ---- approva / rifiuta / rimuovi ----

    public function test_approva_setta_status_e_audit(): void
    {
        $s = $this->source();
        $this->actingAdmin()->patch(route('admin.sources.approve', $s))->assertRedirect();
        $s->refresh();
        $this->assertSame('approved', $s->status);
        $this->assertNotNull($s->reviewed_by);
        $this->assertNotNull($s->reviewed_at);
    }

    public function test_rifiuta_setta_rejected(): void
    {
        $s = $this->source();
        $this->actingAdmin()->patch(route('admin.sources.reject', $s))->assertRedirect();
        $this->assertSame('rejected', $s->refresh()->status);
    }

    public function test_rimuovi_cancella(): void
    {
        $s = $this->source();
        $this->actingAdmin()->delete(route('admin.sources.destroy', $s))->assertRedirect();
        $this->assertSame(0, TrustedSource::count());
    }

    // ---- filtri ----

    public function test_filtri_topic_e_status(): void
    {
        $this->source(['url_or_domain' => 'arxiv.org', 'topic' => 'agenti-ai', 'status' => 'approved']);
        $this->source(['url_or_domain' => 'openreview.net', 'topic' => 'agenti-ai', 'status' => 'suggested']);
        $this->source(['url_or_domain' => 'gdpr.eu', 'topic' => 'compliance', 'status' => 'approved']);

        $this->actingAdmin()
            ->get(route('admin.sources.index', ['topic' => 'agenti-ai', 'status' => 'approved']))
            ->assertOk()
            ->assertSee('arxiv.org')        // agenti-ai + approved
            ->assertDontSee('openreview.net') // stesso topic, stato diverso
            ->assertDontSee('gdpr.eu');       // topic diverso
    }

    // ---- gating ----

    public function test_gating_off_rende_endpoint_irraggiungibili(): void
    {
        config(['services.p26.enabled' => false]);
        $this->actingAdmin()->get(route('admin.sources.index'))->assertNotFound();
        $this->actingAdmin()->post(route('admin.sources.store'), [
            'label' => 'x', 'url_or_domain' => 'arxiv.org', 'mode' => 'search', 'topic' => 't',
        ])->assertNotFound();
        $this->assertSame(0, TrustedSource::count());
    }
}
