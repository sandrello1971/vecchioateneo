<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Compone il CORPO di una lezione Schola a partire da N materiali eterogenei
 * (teaching_documents con il loro extracted_text, più eventuali artefatti già
 * generati). Produce un testo didattico coerente in markdown: introduzione,
 * sviluppo per sezioni, sintesi — registro da scuola superiore, LaTeX dove serve.
 *
 * Stesso pattern degli altri servizi AI (SummaryGenerationService, ecc.):
 * Http::post su /v1/messages, modello claude-sonnet-4-5, RuntimeException + Log,
 * chiave da config/services.php. Ritorna content + meta (modello, token, fonti).
 *
 * Riferimenti temporali: se una fonte ha segments (audio/video), il testo viene
 * passato al modello già marcato con i timestamp [mm:ss] così che il corpo li
 * conservi (citazioni/ricerca video di P20). Le fonti con segments restano
 * tracciate in meta.sources_used[*].has_segments.
 */
class LessonGenerationService
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';
    private const TEMPERATURE = 0.4;
    private const MAX_TOKENS = 8000;
    private const MAX_TOTAL_CHARS = 45000; // budget complessivo su tutte le fonti
    private const PROMPT_VERSION = 'lesson-2026-06';

    /**
     * @param  array  $sources  lista di fonti, ciascuna:
     *   [
     *     'id' => string, 'title' => string, 'source_type' => string,
     *     'text' => string,                              // extracted_text
     *     'segments' => array|null,                      // [{start_seconds,end_seconds,text}]
     *     'artifacts' => array,                          // [{type,title,content}] opzionale
     *   ]
     * @param  string  $lessonTitle
     * @param  array   $options  ['topic' => string, 'subject' => string, 'log_context' => array]
     * @return array{content: string, meta: array}
     */
    public function generateFromSources(array $sources, string $lessonTitle, array $options = []): array
    {
        $sources = array_values(array_filter($sources, fn ($s) => trim((string) ($s['text'] ?? '')) !== ''));
        if (empty($sources)) {
            throw new RuntimeException('Nessun materiale con testo da comporre nella lezione.');
        }

        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata (config/services.php o env ANTHROPIC_API_KEY)');
        }

        [$userMessage, $sourcesMeta] = $this->buildUserMessage($sources, $lessonTitle, $options);
        $systemPrompt = $this->buildSystemPrompt($options);

        $log = array_merge($options['log_context'] ?? [], [
            'kind' => 'lesson',
            'sources' => count($sources),
        ]);
        Log::info('Lesson composition request', $log);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(180)->post(self::CLAUDE_API_URL, [
            'model' => self::CLAUDE_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Lesson Claude API failed', array_merge($log, [
                'status' => $response->status(),
                'body' => $response->body(),
            ]));
            throw new RuntimeException('Errore Claude API: ' . $response->status());
        }

        $data = $response->json();
        $markdown = $data['content'][0]['text'] ?? '';

        if (empty(trim($markdown))) {
            throw new RuntimeException('Risposta Claude vuota');
        }

        // Cleanup di eventuali fence ```markdown ... ```
        $markdown = preg_replace('/^```(?:markdown)?\s*\n/', '', trim($markdown));
        $markdown = preg_replace('/\n```\s*$/', '', $markdown);
        $markdown = trim($markdown);

        $segmentsPreserved = collect($sourcesMeta)->contains(fn ($s) => $s['has_segments']);

        Log::info('Lesson composed', array_merge($log, [
            'output_chars' => mb_strlen($markdown),
            'usage' => $data['usage'] ?? null,
        ]));

        return [
            'content' => $markdown,
            'meta' => [
                'model' => self::CLAUDE_MODEL,
                'tokens_in' => (int) ($data['usage']['input_tokens'] ?? 0),
                'tokens_out' => (int) ($data['usage']['output_tokens'] ?? 0),
                'prompt_version' => self::PROMPT_VERSION,
                'sources_used' => $sourcesMeta,                 // [{id,title,source_type,has_segments}]
                'sources_count' => count($sourcesMeta),
                'segments_preserved' => $segmentsPreserved,     // P20: ricerca video / citazioni
            ],
        ];
    }

    private function buildSystemPrompt(array $options): string
    {
        $subject = trim((string) ($options['subject'] ?? ''));
        $subjectLine = $subject !== '' ? "Materia: {$subject}." : '';

        return <<<TXT
Sei un docente di scuola superiore esperto nel comporre lezioni a partire da
materiali eterogenei (trascrizioni di audio/video, PDF, appunti, immagini). {$subjectLine}

Il tuo compito: FONDERE le fonti fornite in UN'UNICA lezione coerente e scorrevole,
NON una lista di riassunti separati. Integra i contenuti, elimina le ripetizioni,
ricomponi l'ordine logico degli argomenti anche se le fonti lo presentano diversamente.

STRUTTURA OBBLIGATORIA (markdown):
1. Una breve **introduzione** che inquadra l'argomento e gli obiettivi della lezione.
2. Lo **sviluppo** suddiviso in sezioni con titoli `##` (e `###` per i sotto-temi),
   in progressione didattica.
3. Una **sintesi** finale con i punti chiave da ricordare.

REGOLE OBBLIGATORIE:
1. Registro adatto a studenti di scuola superiore: chiaro, preciso, motivante.
2. Usa SOLO il contenuto delle fonti fornite. NON inventare fatti o dati assenti.
3. Markdown: `##`/`###` per i titoli, elenchi dove utile, **grassetto** per i termini chiave.
4. Formule ed espressioni matematiche/scientifiche in LaTeX: in linea con \$...\$, in blocco con \$\$...\$\$.
5. Quando una fonte è una trascrizione con marcatori temporali `[mm:ss]`, CONSERVA i
   riferimenti `[mm:ss]` accanto ai passaggi che ne derivano, così da poterli ritrovare nel video.
6. Risposta: SOLO il markdown della lezione. NIENTE preamble, NIENTE commenti, NIENTE code fence.
TXT;
    }

    /**
     * Assembla il messaggio utente concatenando le fonti, con budget di caratteri
     * distribuito. Le trascrizioni con segments vengono rese come righe `[mm:ss] testo`.
     *
     * @return array{0: string, 1: array}  [messaggio, meta delle fonti usate]
     */
    private function buildUserMessage(array $sources, string $lessonTitle, array $options): array
    {
        $topic = trim((string) ($options['topic'] ?? ''));
        $perSourceBudget = (int) floor(self::MAX_TOTAL_CHARS / max(1, count($sources)));

        $blocks = [];
        $meta = [];
        foreach ($sources as $i => $s) {
            $n = $i + 1;
            $title = $s['title'] ?? "Fonte {$n}";
            $type = $s['source_type'] ?? 'text';
            $segments = $s['segments'] ?? null;
            $hasSegments = is_array($segments) && !empty($segments);

            $body = $hasSegments
                ? $this->renderSegments($segments, $perSourceBudget)
                : $this->truncate(trim((string) $s['text']), $perSourceBudget);

            // Artefatti già lavorati: passati come supporto (es. scaletta, riassunto).
            $artifactsText = '';
            foreach (($s['artifacts'] ?? []) as $a) {
                $ac = trim((string) ($a['content'] ?? ''));
                if ($ac === '') {
                    continue;
                }
                $aLabel = $a['title'] ?? ucfirst((string) ($a['type'] ?? 'artefatto'));
                $artifactsText .= "\n\n[{$aLabel} — già elaborato dal docente]\n" . $this->truncate($ac, 4000);
            }

            $kind = $hasSegments ? "{$type}, trascrizione con timestamp" : $type;
            $blocks[] = "### FONTE {$n}: {$title} ({$kind})\n{$body}{$artifactsText}";

            $meta[] = [
                'id' => $s['id'] ?? null,
                'title' => $title,
                'source_type' => $type,
                'has_segments' => $hasSegments,
            ];
        }

        $header = "Componi la lezione **{$lessonTitle}**";
        if ($topic !== '') {
            $header .= " (argomento: {$topic})";
        }
        $header .= " fondendo le fonti seguenti in un unico testo coerente.";

        $message = $header . "\n\n" . implode("\n\n---\n\n", $blocks)
            . "\n\nScrivi la lezione in italiano, in formato Markdown, seguendo la struttura richiesta.";

        return [$message, $meta];
    }

    /** Rende i segments come righe `[mm:ss] testo`, entro il budget di caratteri. */
    private function renderSegments(array $segments, int $budget): string
    {
        $lines = [];
        $used = 0;
        foreach ($segments as $seg) {
            $text = trim((string) ($seg['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $line = '[' . $this->mmss((float) ($seg['start_seconds'] ?? 0)) . '] ' . $text;
            $used += mb_strlen($line) + 1;
            if ($used > $budget) {
                $lines[] = '[...trascrizione troncata per limiti di token]';
                break;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function mmss(float $seconds): string
    {
        $s = (int) round($seconds);
        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }

    private function truncate(string $text, int $budget): string
    {
        if (mb_strlen($text) <= $budget) {
            return $text;
        }

        return mb_substr($text, 0, $budget) . "\n[...contenuto troncato per limiti di token]";
    }
}
