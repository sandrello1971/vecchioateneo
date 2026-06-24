<?php

namespace App\Services\Freshness;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * P25.B-a — Fase 1 sul MATERIALE STUDENTE (modules.content, HTML). Per ogni modulo:
 * strip HTML → testo → estrazione affermazioni databili (stesso compito di Fase 1) →
 * ri-localizzazione VERBATIM nel content_html GREZZO con regola "unico-nel-modulo o
 * scarto". Ancora = module_id + claim_text verbatim (il `block_id` è del lato formatore).
 *
 * Riusa VerbatimReplacer (normalizzazione length-preserving). Le entità HTML sono
 * assenti su questo contenuto pulito; il claim cade dentro un singolo <p> come testo
 * contiguo. NOTA: approccio verbatim-su-stringa che assume HTML pulito; se in futuro
 * comparisse markup inline/entità, passare a parsing DOM (text-node).
 *
 * Servizio PURO (nessuna scrittura DB): la persistenza è dell'agente. Modello:
 * freshness_extract_model (Sonnet). Testabile con Http::fake.
 */
class StudentClaimExtractor
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MAX_TOKENS = 4000;

    private const ALLOWED_CATEGORIES = ['model', 'norma', 'data', 'prezzo', 'prodotto'];

    // Identico, nel compito, alla Fase 1 di FreshnessClaimExtractor (prompt riusato):
    // estrai SOLO le affermazioni databili, cita VERBATIM, JSON puro.
    private const SYSTEM_PROMPT = <<<SYS
    Sei un analista di obsolescenza didattica. Ricevi i moduli di un corso (materiale per
    studenti), ciascuno introdotto dal suo identificatore tra parentesi quadre [module_id].

    Estrai SOLO le affermazioni che possono INVECCHIARE, di queste categorie:
    - model: modelli o versioni di AI (es. "Claude 3.5", "GPT-4o")
    - norma: riferimenti normativi (AI Act, GDPR, ISO/IEC, UNI, regolamenti UE)
    - data: date, anni, edizioni ("edizione 2025", "nel 2026")
    - prezzo: prezzi, costi, statistiche, dati di mercato ("1,8 miliardi", "16,4%")
    - prodotto: nomi di prodotti, servizi o protocolli soggetti a cambiamento

    Per ogni affermazione restituisci: il module_id di provenienza, la citazione VERBATIM
    (copiata ESATTAMENTE dal testo del modulo, carattere per carattere) e la categoria.

    Rispondi ESCLUSIVAMENTE con JSON valido, senza preamboli e senza markdown. Formato:
    {"claims":[{"module_id":"...","quote":"...","category":"model|norma|data|prezzo|prodotto"}]}

    Regole: la "quote" DEVE essere copiata letteralmente dal modulo indicato; se un modulo
    non contiene affermazioni databili, non includerlo; non inventare nulla.
    SYS;

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  iterable<\App\Models\Module>  $modules  moduli del corso (id + content HTML)
     * @return array{claims: list<array{module_id:string, sentence_ref:int|null, claim_text:string, category:string}>, rejected: list<array>}
     */
    public function extract(iterable $modules): array
    {
        $this->warnings = [];
        $rawHtml = [];  // module_id → content_html grezzo
        $plain = [];    // module_id → testo (per il prompt e sentence_ref)
        foreach ($modules as $m) {
            $rawHtml[$m->id] = (string) $m->content;
            $plain[$m->id] = $this->htmlToText((string) $m->content);
        }

        $raw = $this->callClaude($this->serialize($plain));

        $claims = [];
        $rejected = [];
        foreach ($raw['claims'] ?? [] as $item) {
            $moduleId = is_array($item) ? ($item['module_id'] ?? null) : null;
            $quote = is_array($item) ? ($item['quote'] ?? null) : null;
            $category = is_array($item) ? ($item['category'] ?? null) : null;

            if (!is_string($moduleId) || !is_string($quote) || trim($quote) === '') {
                $rejected[] = ['module_id' => is_string($moduleId) ? $moduleId : null, 'quote' => null, 'reason' => 'campi mancanti/non validi'];
                continue;
            }
            if (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
                $rejected[] = ['module_id' => $moduleId, 'quote' => $quote, 'reason' => 'categoria non ammessa'];
                continue;
            }
            if (!array_key_exists($moduleId, $rawHtml)) {
                $rejected[] = ['module_id' => $moduleId, 'quote' => $quote, 'reason' => 'module_id inesistente'];
                continue;
            }

            // Ri-localizzazione VERBATIM nel content_html grezzo, UNICA nel modulo.
            $count = VerbatimReplacer::countOccurrences($rawHtml[$moduleId], $quote);
            if ($count === 0) {
                $rejected[] = ['module_id' => $moduleId, 'quote' => $quote, 'reason' => 'citazione non ritrovata nel modulo'];
                continue;
            }
            if ($count > 1) {
                $rejected[] = ['module_id' => $moduleId, 'quote' => $quote, 'reason' => "citazione non unica nel modulo ({$count} occorrenze)"];
                continue;
            }

            $claims[] = [
                'module_id' => $moduleId,
                'sentence_ref' => $this->sentenceIndex($plain[$moduleId], $quote),
                'claim_text' => $this->originalSubstring($rawHtml[$moduleId], $quote), // verbatim dall'HTML
                'category' => $category,
            ];
        }

        if ($rejected) {
            Log::info('[StudentClaimExtractor] claim scartati: ' . count($rejected), ['rejected' => $rejected]);
        }

        return ['claims' => $claims, 'rejected' => $rejected, 'warnings' => $this->warnings];
    }

    /** Sottostringa ORIGINALE alla posizione del match (normalizzazione 1:1 → offset coincidenti). */
    private function originalSubstring(string $haystack, string $needle): string
    {
        $normHaystack = VerbatimReplacer::normalize($haystack);
        $normNeedle = trim(VerbatimReplacer::normalize($needle));
        $pos = mb_strpos($normHaystack, $normNeedle);
        return $pos === false ? $needle : mb_substr($haystack, $pos, mb_strlen($normNeedle));
    }

    /** HTML → testo: i tag diventano spazio (niente parole incollate), whitespace collassato. */
    private function htmlToText(string $html): string
    {
        $noTags = preg_replace('/<[^>]+>/u', ' ', $html);
        return trim(preg_replace('/\s+/u', ' ', $noTags));
    }

    private function serialize(array $plainByModule): string
    {
        $lines = [];
        foreach ($plainByModule as $id => $text) {
            if (trim($text) === '') {
                continue;
            }
            $lines[] = "[{$id}] {$text}";
        }
        return implode("\n\n", $lines);
    }

    /** Indice (0-based, best-effort) della frase del modulo che contiene la citazione. */
    private function sentenceIndex(string $text, string $quote): ?int
    {
        $normQuote = trim(VerbatimReplacer::normalize($quote));
        $sentences = preg_split('/(?<=[.!?])\s+(?=\p{Lu}|\d)/u', VerbatimReplacer::normalize($text)) ?: [];
        foreach ($sentences as $i => $s) {
            if (mb_strpos($s, $normQuote) !== false) {
                return $i;
            }
        }
        return null;
    }

    /** @return array{claims?: array} */
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
                ['role' => 'user', 'content' => "Moduli del corso (materiale studente):\n\n" . $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(AnthropicError::message($response, 'Fase 1 (studente)'));
        }

        $text = $response->json('content.0.text');
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Risposta Fase 1 (studente) vuota o malformata.');
        }

        return $this->decodeJson($text);
    }

    private function decodeJson(string $text): array
    {
        $clean = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Output Fase 1 (studente) non è JSON valido.');
        }
        return $decoded;
    }
}
