<?php

namespace App\Services;

use App\Models\TrustedSource;
use App\Services\Freshness\AnthropicError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * P26 Fase 0 — Curatela assistita: dato un `topic`, propone N fonti AUTOREVOLI (LLM) per quel
 * dominio e le salva come `suggested` + `proposed_by='agent'`. NON approva nulla (HITL).
 * Isolato: chi lo invoca lo fa in try/catch; un errore (es. credito esaurito) non deve toccare
 * il registro/CRUD. Riusa AnthropicError per il messaggio reale. Mai su corsi/moduli/studenti.
 */
class SourceSuggester
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MAX_TOKENS = 1500;

    private const SYSTEM_PROMPT = <<<SYS
    Sei un bibliotecario tecnico. Dato un DOMINIO TEMATICO, proponi le fonti più AUTOREVOLI
    e durevoli da cui restare aggiornati su quel tema. Preferisci fonti PRIMARIE e ufficiali:
    enti di standard, documentazione ufficiale di prodotti/protocolli, repository scientifici
    (es. arxiv.org), siti istituzionali (UE, autorità). Evita blog, news generaliste, social.

    Per ogni fonte indica la modalità:
    - "search": un DOMINIO da cui cercare nel tempo (es. "arxiv.org", "iso.org").
    - "fetch": una PAGINA specifica e stabile da rileggere (es. la pagina ufficiale di una norma).

    Rispondi ESCLUSIVAMENTE con JSON valido, senza preamboli né markdown. Formato esatto:
    {"sources":[{"label":"...","url_or_domain":"...","mode":"search|fetch","notes":"..."}]}

    Regole: per "search" metti un dominio nudo (niente https://, niente path); per "fetch" un URL
    completo. "notes" breve (perché è autorevole). Non inventare URL: se non sei certo, ometti.
    SYS;

    /**
     * @return array{created:int, skipped:int}
     */
    public function suggest(string $topic, int $n = 5): array
    {
        // Topic SEMPRE slugificato (coerente coi topic dei corsi → lo Scout li fa combaciare).
        $topic = Str::slug($topic);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::CLAUDE_API_URL, [
            'model' => config('services.anthropic.freshness_extract_model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [
                ['role' => 'user', 'content' => "Dominio tematico: «{$topic}». Proponi fino a {$n} fonti."],
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(AnthropicError::message($response, 'proposta fonti'));
        }

        $candidates = $this->parseSources($response->json('content.0.text'));

        $created = 0;
        $skipped = 0;
        foreach ($candidates as $c) {
            $mode = (($c['mode'] ?? '') === 'fetch') ? 'fetch' : 'search';
            $norm = self::normalizeTarget($mode, (string) ($c['url_or_domain'] ?? ''));
            if (!$norm['ok']) {
                $skipped++;
                continue;
            }

            // Dedup robusto: salta se esiste per (topic, target, mode) in QUALSIASI stato,
            // incluse le 'rejected' — una fonte scartata non va ri-proposta.
            $exists = TrustedSource::where('topic', $topic)
                ->where('url_or_domain', $norm['value'])
                ->where('mode', $mode)
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            TrustedSource::create([
                'label' => mb_substr(trim((string) ($c['label'] ?? '')) ?: $norm['value'], 0, 255),
                'url_or_domain' => $norm['value'],
                'mode' => $mode,
                'topic' => $topic,
                'status' => 'suggested',
                'proposed_by' => 'agent',
                'notes' => isset($c['notes']) ? mb_substr((string) $c['notes'], 0, 500) : null,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Normalizza e valida un target. search → dominio nudo (togli schema/path); fetch → URL
     * http(s) completo. Condivisa col CRUD: input malformato non entra (romperebbe lo Scout).
     *
     * @return array{ok:bool, value?:string, error?:string}
     */
    public static function normalizeTarget(string $mode, string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => false, 'error' => 'Indirizzo mancante.'];
        }

        if ($mode === 'search') {
            $host = preg_match('#^https?://#i', $raw)
                ? (string) parse_url($raw, PHP_URL_HOST)
                : preg_split('~[/?#]~', $raw)[0]; // tronca path/query/fragment
            $host = preg_replace('#^www\.#i', '', strtolower(trim($host)));
            if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', (string) $host)) {
                return ['ok' => false, 'error' => 'Per la modalità "search" serve un dominio valido (es. arxiv.org).'];
            }
            return ['ok' => true, 'value' => $host];
        }

        // fetch
        if (!preg_match('#^https?://#i', $raw) || filter_var($raw, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'error' => 'Per la modalità "fetch" serve un URL completo valido (https://…).'];
        }
        return ['ok' => true, 'value' => $raw];
    }

    /** Parsing JSON tollerante ai fence ```json. @return list<array<string,mixed>> */
    private function parseSources(?string $text): array
    {
        if (!is_string($text) || trim($text) === '') {
            return [];
        }
        $clean = preg_replace('/^```(?:json)?|```$/m', '', trim($text));
        $data = json_decode((string) $clean, true);

        return is_array($data['sources'] ?? null) ? $data['sources'] : [];
    }
}
