<?php

namespace App\Services\Freshness;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * P25.2 — Fase 1: estrazione delle affermazioni databili dal sorgente strutturato.
 *
 * L'LLM riceve i blocchi (con block_id) e ritorna SOLO le affermazioni che possono
 * invecchiare, citando VERBATIM dal testo. La posizione (block_id, sentence_ref) NON
 * viene dedotta dall'LLM: applichiamo "verify, don't trust" — ri-localizziamo la
 * citazione nel blocco con codice deterministico e ricalcoliamo sentence_ref,
 * SCARTANDO le citazioni non ritrovate (blocco inesistente o quote non presente).
 *
 * Output JSON validato: tollera i fence ```json ma fa reject su contenuto non-JSON.
 * Servizio PURO (nessuna scrittura DB): la persistenza in freshness_claims è
 * dell'orchestratore. Testabile con Http::fake.
 */
class FreshnessClaimExtractor
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MAX_TOKENS = 4000;

    /** Categorie ammesse (allineate al CHECK di freshness_claims.category). */
    private const ALLOWED_CATEGORIES = ['model', 'norma', 'data', 'prezzo', 'prodotto'];

    private const SYSTEM_PROMPT = <<<SYS
    Sei un analista di obsolescenza didattica. Ricevi i blocchi di un corso, ciascuno
    introdotto dal suo identificatore tra parentesi quadre [block_id].

    Estrai SOLO le affermazioni che possono INVECCHIARE, di queste categorie:
    - model: modelli o versioni di AI (es. "Claude 3.5", "GPT-4o")
    - norma: riferimenti normativi (AI Act, GDPR, ISO/IEC, UNI, regolamenti UE)
    - data: date, anni, edizioni ("edizione 2025", "nel 2026")
    - prezzo: prezzi, costi, statistiche, dati di mercato ("1,8 miliardi", "16,4%")
    - prodotto: nomi di prodotti, servizi o protocolli soggetti a cambiamento

    Per ogni affermazione restituisci: il block_id di provenienza, la citazione VERBATIM
    (copiata ESATTAMENTE dal testo del blocco, carattere per carattere, senza modifiche
    né parafrasi) e la categoria.

    Rispondi ESCLUSIVAMENTE con JSON valido, senza alcun preambolo e senza markdown.
    Formato esatto:
    {"claims":[{"block_id":"...","quote":"...","category":"model|norma|data|prezzo|prodotto"}]}

    Regole:
    - La "quote" DEVE essere copiata letteralmente dal blocco indicato: non riscrivere,
      non riassumere, non correggere.
    - Se un blocco non contiene affermazioni databili, non includerlo.
    - Non inventare nulla. Se non sei sicuro che una frase invecchi, NON estrarla.
    SYS;

    /**
     * Estrae e valida le affermazioni databili dai blocchi del sorgente.
     *
     * @param  list<array>  $blocks  blocchi di course_sources (id, type, text|items)
     * @return array{claims: list<array{block_id:string, sentence_ref:int|null, claim_text:string, category:string}>, rejected: list<array{block_id:?string, quote:?string, reason:string}>}
     */
    public function extract(array $blocks): array
    {
        // Mappa block_id → testo ricercabile (paragrafi/heading: text; liste: items).
        $blockText = [];
        foreach ($blocks as $b) {
            $id = $b['id'] ?? null;
            if ($id === null) {
                continue;
            }
            $blockText[$id] = $this->searchableText($b);
        }

        $raw = $this->callClaude($this->serializeBlocks($blocks));

        $claims = [];
        $rejected = [];
        foreach ($raw['claims'] ?? [] as $item) {
            $blockId = is_array($item) ? ($item['block_id'] ?? null) : null;
            $quote = is_array($item) ? ($item['quote'] ?? null) : null;
            $category = is_array($item) ? ($item['category'] ?? null) : null;

            if (!is_string($blockId) || !is_string($quote) || trim($quote) === '') {
                $rejected[] = ['block_id' => is_string($blockId) ? $blockId : null, 'quote' => is_string($quote) ? $quote : null, 'reason' => 'campi mancanti/non validi'];
                continue;
            }
            if (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
                $rejected[] = ['block_id' => $blockId, 'quote' => $quote, 'reason' => "categoria non ammessa: " . (is_string($category) ? $category : gettype($category))];
                continue;
            }
            if (!array_key_exists($blockId, $blockText)) {
                $rejected[] = ['block_id' => $blockId, 'quote' => $quote, 'reason' => 'block_id inesistente'];
                continue;
            }

            // Ri-localizzazione deterministica: la quote DEVE esistere nel blocco.
            $located = $this->locate($blockText[$blockId], $quote);
            if ($located === null) {
                $rejected[] = ['block_id' => $blockId, 'quote' => $quote, 'reason' => 'citazione non ritrovata nel blocco'];
                continue;
            }

            $claims[] = [
                'block_id' => $blockId,
                'sentence_ref' => $located['sentence_ref'],
                'claim_text' => $located['text'], // sottostringa ORIGINALE del blocco
                'category' => $category,
            ];
        }

        if ($rejected) {
            Log::info('[FreshnessClaimExtractor] claim scartati: ' . count($rejected), ['rejected' => $rejected]);
        }

        return ['claims' => $claims, 'rejected' => $rejected];
    }

    /**
     * Ri-localizza la citazione nel testo del blocco e calcola sentence_ref (0-based).
     * Normalizzazione 1:1 (apostrofi/virgolette tipografiche, trattini, NBSP) per
     * tollerare varianti di copia mantenendo gli offset in caratteri allineati.
     *
     * @return array{text:string, sentence_ref:int|null}|null  null se non ritrovata
     */
    private function locate(string $blockText, string $quote): ?array
    {
        $normBlock = $this->normalize($blockText);
        $normQuote = trim($this->normalize($quote));
        if ($normQuote === '') {
            return null;
        }

        $pos = mb_strpos($normBlock, $normQuote);
        if ($pos === false) {
            return null;
        }

        // Sottostringa ORIGINALE (normalizzazione 1:1 in caratteri → offset coincidenti).
        $originalText = mb_substr($blockText, $pos, mb_strlen($normQuote));

        return [
            'text' => $originalText,
            'sentence_ref' => $this->sentenceIndexAt($normBlock, $normQuote, $pos),
        ];
    }

    /**
     * Indice (0-based) della frase che contiene la citazione. Prima cerca la frase che
     * contiene la quote per intero; in fallback usa l'offset di inizio. Best-effort:
     * l'ancora autorevole resta block_id + claim_text verbatim.
     */
    private function sentenceIndexAt(string $normBlock, string $normQuote, int $pos): ?int
    {
        $sentences = $this->splitSentences($normBlock);
        if (count($sentences) <= 1) {
            return 0;
        }

        // 1) frase che contiene per intero la quote.
        foreach ($sentences as $i => $sentence) {
            if (mb_strpos($sentence, $normQuote) !== false) {
                return $i;
            }
        }

        // 2) fallback per offset: testo a spazio singolo → +1 per il separatore rimosso.
        $cum = 0;
        foreach ($sentences as $i => $sentence) {
            $cum += mb_strlen($sentence) + 1;
            if ($pos < $cum) {
                return $i;
            }
        }

        return count($sentences) - 1;
    }

    /** @return list<string> */
    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=\p{Lu}|\d)/u', trim($text));
        return array_values(array_filter($parts ?: [], fn ($s) => trim($s) !== ''));
    }

    /** Normalizzazione 1:1 (length-preserving in caratteri) per il matching robusto. */
    private function normalize(string $s): string
    {
        return strtr($s, [
            '’' => "'", '‘' => "'", '‛' => "'", '‚' => "'",
            '“' => '"', '”' => '"', '„' => '"', '‟' => '"',
            '—' => '-', '–' => '-', '‒' => '-', '―' => '-',
            "\u{00A0}" => ' ',
        ]);
    }

    private function searchableText(array $block): string
    {
        if (isset($block['text']) && is_string($block['text'])) {
            return $block['text'];
        }
        if (isset($block['items']) && is_array($block['items'])) {
            return implode('. ', array_map('strval', $block['items']));
        }
        return '';
    }

    private function serializeBlocks(array $blocks): string
    {
        $lines = [];
        foreach ($blocks as $b) {
            $id = $b['id'] ?? null;
            $text = $this->searchableText($b);
            if ($id === null || trim($text) === '') {
                continue;
            }
            $lines[] = "[{$id}] {$text}";
        }
        return implode("\n\n", $lines);
    }

    /**
     * Chiamata all'API Anthropic + parsing JSON tollerante ai fence ```json.
     * Reject (RuntimeException) su HTTP non ok o contenuto non-JSON.
     *
     * @return array{claims?: array}
     */
    private function callClaude(string $userPrompt): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::CLAUDE_API_URL, [
            'model' => config('services.anthropic.freshness_extract_model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [
                ['role' => 'user', 'content' => "Blocchi del corso:\n\n" . $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(AnthropicError::message($response, 'Fase 1'));
        }

        $text = $response->json('content.0.text');
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Risposta Fase 1 vuota o malformata.');
        }

        return $this->decodeJson($text);
    }

    /** Decodifica JSON tollerando i fence ```json … ```; reject su non-JSON. */
    private function decodeJson(string $text): array
    {
        $clean = trim($text);
        // Rimuove un eventuale blocco markdown ```json … ``` o ``` … ```.
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $clean, $m)) {
            $clean = trim($m[1]);
        }

        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Output Fase 1 non è JSON valido (atteso JSON puro, niente preamboli/markdown).');
        }

        return $decoded;
    }
}
