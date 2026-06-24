<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MindMapGenerationService
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    // Allineato a QuizGeneratorService + CourseIngestionService (formato corto)
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';
    private const MAX_TOKENS = 2000;
    private const TEMPERATURE = 0.3; // basso per output deterministico
    private const MAX_CONTENT_CHARS = 30000;
    private const PROMPT_VERSION = 'mindmap-2026-06';

    /**
     * Genera la mappa mentale di un modulo via Claude API.
     * Ritorna markdown gerarchico compatibile con markmap.js.
     *
     * Percorso storico (mondo corsi): comportamento invariato, delega al core
     * generateFromText e restituisce solo il markdown.
     */
    public function generate(Module $module): string
    {
        if (empty($module->content)) {
            throw new RuntimeException('Il modulo non ha contenuto da analizzare');
        }

        // Strip HTML per dare a Claude testo pulito (pandoc-generated HTML e' verbose)
        $contentText = trim(strip_tags($module->content));

        $result = $this->generateFromText($contentText, $module->title, [
            'log_context' => ['module_id' => $module->id, 'module_title' => $module->title],
        ]);

        return $result['content'];
    }

    /**
     * Core parametrizzato: genera una mappa mentale a partire da testo sorgente
     * arbitrario (modulo corso, documento didattico Schola, ecc.).
     *
     * @param  string  $sourceText    testo già ripulito (no HTML) da analizzare
     * @param  string  $contextLabel  titolo/etichetta del contesto (es. titolo modulo o documento)
     * @param  array   $options       ['log_context' => array]
     * @return array{content: string, meta: array}
     */
    public function generateFromText(string $sourceText, string $contextLabel, array $options = []): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata (config/services.php o env ANTHROPIC_API_KEY)');
        }

        $contentText = trim($sourceText);
        if ($contentText === '') {
            throw new RuntimeException('Nessun testo sorgente da analizzare');
        }

        // Truncate protettivo (raro ma evita explosione token sui contenuti enormi)
        if (mb_strlen($contentText) > self::MAX_CONTENT_CHARS) {
            $contentText = mb_substr($contentText, 0, self::MAX_CONTENT_CHARS)
                . "\n[...contenuto troncato per limiti di token]";
        }

        $logContext = $options['log_context'] ?? [];

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($contextLabel, $contentText);

        Log::info('MindMap generation request', array_merge($logContext, [
            'content_chars' => mb_strlen($contentText),
        ]));

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::CLAUDE_API_URL, [
            'model' => self::CLAUDE_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('MindMap Claude API failed', array_merge($logContext, [
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

        // Cleanup: rimuovi eventuali fence ```markdown ... ``` che Claude potrebbe aggiungere
        $markdown = preg_replace('/^```(?:markdown)?\s*\n/', '', trim($markdown));
        $markdown = preg_replace('/\n```\s*$/', '', $markdown);
        $markdown = trim($markdown);

        Log::info('MindMap generated', array_merge($logContext, [
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
            ],
        ];
    }

    private function buildSystemPrompt(): string
    {
        $domainContext = atheneum_setting('assistant_domain_context', '');
        $contextHint = !empty($domainContext)
            ? "Il pubblico target e': {$domainContext}."
            : '';

        return <<<TXT
Sei un esperto pedagogo specializzato nella creazione di mappe mentali per la formazione.

Il tuo compito e' analizzare il contenuto di un modulo didattico e creare una mappa mentale in formato markdown gerarchico, ottimizzata per il rendering con la libreria markmap.js.

{$contextHint}

REGOLE OBBLIGATORIE:
1. Inizia SEMPRE con un singolo # (titolo del modulo)
2. Usa ## per 3-7 macro-concetti (nodi di primo livello)
3. Sotto ogni ## usa - per i sotto-concetti (max 4-6 per macro)
4. Annida con indentazione di 2 spazi per ulteriori dettagli (max 2 livelli aggiuntivi)
5. Nodi BREVI: max 8 parole per nodo, preferibilmente meno
6. Estrai SOLO concetti effettivamente presenti nel contenuto, NON inventare
7. Mantieni un ordine logico (sequenziale o gerarchico)
8. Linguaggio coerente con il livello del corso (universitario, professionale, ecc.)
9. Risposta SOLO il markdown, NIENTE preamble, NIENTE commenti, NIENTE code fence

ESEMPIO di output corretto:
# Titolo del modulo

## Concetti fondamentali
- Definizione di X
  - Origine storica
  - Evoluzione contemporanea
- Applicazioni principali

## Tecniche operative
- Tecnica A
- Tecnica B
  - Variante B.1
  - Variante B.2

## Casi pratici
- Caso aziendale
- Caso accademico
TXT;
    }

    private function buildUserMessage(string $moduleTitle, string $content): string
    {
        return <<<TXT
Titolo del modulo: **{$moduleTitle}**

Contenuto del modulo:
---
{$content}
---

Genera la mappa mentale.
TXT;
    }
}
