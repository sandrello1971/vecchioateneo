<?php

namespace Tests\Feature\Freshness;

use App\Services\Freshness\FreshnessClaimExtractor;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * P25.2 Fase 1 — estrazione affermazioni databili su blocchi REALI di CONSILIUM v2.0.
 * L'LLM è simulato con Http::fake; il valore testato è la logica di ri-localizzazione
 * deterministica della citazione (block_id/sentence_ref) e lo scarto delle citazioni
 * non ritrovate. Nessun DB, nessuna chiamata reale.
 */
class FreshnessClaimExtractorTest extends TestCase
{
    /** @return list<array> i 2 blocchi reali di v2.0 (heading-data + statistiche). */
    private function blocks(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/p25/consilium-v2-claim-blocks.json')), true);
    }

    private function fakeLlmClaims(array $claims): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['claims' => $claims], JSON_UNESCAPED_UNICODE)]],
        ], 200)]);
    }

    public function test_estrae_valida_e_scarta_su_v2_reale(): void
    {
        $this->fakeLlmClaims([
            // --- validi ---
            ['block_id' => 'p1-cap3-sec1-p2', 'quote' => 'Il mercato AI italiano vale 1,8 miliardi di euro nel 2025', 'category' => 'prezzo'],
            // apostrofi DRITTI nella quote → la normalizzazione 1:1 deve ritrovarla (frase 1)
            ['block_id' => 'p1-cap3-sec1-p2', 'quote' => "L'adozione dell'AI nelle imprese italiane con almeno 10 addetti", 'category' => 'prezzo'],
            ['block_id' => 'p1-cap3-sec1-p2', 'quote' => 'le grandi imprese (oltre 250 addetti) sono al 53,1% di adozione', 'category' => 'prezzo'],
            ['block_id' => 'p1-cap3', 'quote' => 'AI e PMI italiane nel 2026', 'category' => 'data'],
            // --- da scartare ---
            ['block_id' => 'p1-cap3-sec1-p2', 'quote' => 'il mercato AI vale 5 miliardi di dollari nel 2030', 'category' => 'prezzo'], // citazione inventata
            ['block_id' => 'p9-cap9-sec9-p9', 'quote' => 'qualcosa', 'category' => 'data'], // block_id inesistente
            ['block_id' => 'p1-cap3', 'quote' => '2026', 'category' => 'foo'], // categoria non ammessa
        ]);

        $res = app(FreshnessClaimExtractor::class)->extract($this->blocks());

        // 4 validi, 3 scartati
        $this->assertCount(4, $res['claims']);
        $this->assertCount(3, $res['rejected']);

        $c = $res['claims'];

        // Claim 0 — prezzo, frase 0, claim_text = sottostringa originale
        $this->assertSame('p1-cap3-sec1-p2', $c[0]['block_id']);
        $this->assertSame(0, $c[0]['sentence_ref']);
        $this->assertSame('prezzo', $c[0]['category']);
        $this->assertStringContainsString('1,8 miliardi di euro nel 2025', $c[0]['claim_text']);

        // Claim 1 — normalizzazione: quote con apostrofi dritti, claim_text torna con i
        // tipografici ORIGINALI (’), frase 1
        $this->assertSame(1, $c[1]['sentence_ref']);
        $this->assertStringContainsString('’', $c[1]['claim_text']);
        $this->assertStringNotContainsString("'", $c[1]['claim_text']);

        // Claim 2 — frase 2
        $this->assertSame(2, $c[2]['sentence_ref']);
        $this->assertStringContainsString('53,1%', $c[2]['claim_text']);

        // Claim 3 — data, heading p1-cap3 (frase unica → 0)
        $this->assertSame('p1-cap3', $c[3]['block_id']);
        $this->assertSame(0, $c[3]['sentence_ref']);
        $this->assertSame('data', $c[3]['category']);

        // Motivi di scarto coerenti
        $reasons = implode(' | ', array_column($res['rejected'], 'reason'));
        $this->assertStringContainsString('non ritrovata', $reasons);
        $this->assertStringContainsString('inesistente', $reasons);
        $this->assertStringContainsString('categoria', $reasons);
    }

    public function test_reject_su_output_non_json(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Ecco le affermazioni databili che ho trovato: la prima è...']],
        ], 200)]);

        $this->expectException(RuntimeException::class);
        app(FreshnessClaimExtractor::class)->extract($this->blocks());
    }

    public function test_tollera_fence_markdown_json(): void
    {
        $json = "```json\n" . json_encode(['claims' => [
            ['block_id' => 'p1-cap3', 'quote' => 'AI e PMI italiane nel 2026', 'category' => 'data'],
        ]], JSON_UNESCAPED_UNICODE) . "\n```";

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $json]],
        ], 200)]);

        $res = app(FreshnessClaimExtractor::class)->extract($this->blocks());
        $this->assertCount(1, $res['claims']);
        $this->assertSame('data', $res['claims'][0]['category']);
    }

    public function test_usa_il_model_di_estrazione_da_config(): void
    {
        $this->fakeLlmClaims([]);
        app(FreshnessClaimExtractor::class)->extract($this->blocks());

        Http::assertSent(fn ($request) => $request['model'] === config('services.anthropic.freshness_extract_model'));
        $this->assertSame('claude-sonnet-4-6', config('services.anthropic.freshness_extract_model'));
    }
}
