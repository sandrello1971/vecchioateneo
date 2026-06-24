<?php

namespace App\Services\Schola;

use App\Models\BrandProfile;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\ModulePresentation;
use App\Support\Branding\ResolvedTheme;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Genera la presentazione .pptx di una lezione (Schola P21).
 *
 * Due passi: (1) Claude trasforma lessons.content in una SPEC di slide (JSON,
 * una slide per sezione, bullet sintetici, registro scuola superiore); (2)
 * python-pptx renderizza la spec in un .pptx (non si reinventa il formato OOXML).
 * Il file vive in storage PRIVATO; il branding scuola, se presente, va sulla
 * slide di titolo. Stesso pattern AI degli altri servizi (Http::post Anthropic,
 * RuntimeException, Log).
 */
class LessonPresentationService
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const TEMPERATURE = 0.3;
    private const MAX_TOKENS = 4000;
    private const MAX_CONTENT_CHARS = 30000;
    private const PROMPT_VERSION = 'pptx-p27-2026-06';
    private const EDIT_PROMPT_VERSION = 'pptx-edit-v1';

    /** Layout di CONTENUTO che l'LLM può scegliere (la cover è generata da noi). */
    private const CONTENT_LAYOUTS = ['process_cards', 'columns', 'stat', 'bullets_clean'];

    /**
     * Costruisce il .pptx per una presentazione di lezione.
     *
     * @return array{file_path: string, meta: array, spec: array}
     */
    public function build(LessonPresentation $presentation): array
    {
        $lesson = $presentation->lesson()->with('topic.subject')->first();
        if (!$lesson) {
            throw new RuntimeException('Lezione della presentazione non trovata.');
        }
        $content = trim((string) $lesson->content);
        if ($content === '') {
            throw new RuntimeException('La lezione non ha un corpo da trasformare in presentazione.');
        }

        // Tema risolto (P27): profilo della scuola del docente, o default GLITCH
        // per i docenti "liberi" (school NULL). Branding white-label per il nome.
        $school = $lesson->teacher?->school;
        $theme = ($school ? BrandProfile::forSchool($school) : BrandProfile::forPlatform())->resolvedTheme();
        $branding = SchoolBranding::for($school);

        $subtitle = trim(implode(' · ', array_filter([
            $lesson->topic?->name, $lesson->topic?->subject?->name,
        ])));

        return $this->buildFrom(
            $content,
            $lesson->title,
            $subtitle,
            $branding->instanceName(),
            $theme,
            "lesson-presentations/{$lesson->id}/{$presentation->id}.pptx",
            [
                'subject' => $lesson->topic?->subject?->name,
                'log_context' => ['lesson_id' => $lesson->id, 'presentation_id' => $presentation->id],
            ],
        );
    }

    /**
     * Costruisce il .pptx per un MODULO di corso Officina (P28).
     * Sorgente = module.content; brand = piattaforma (GLITCH), i corsi non hanno scuola.
     *
     * @return array{file_path: string, meta: array, spec: array}
     */
    public function buildForModule(ModulePresentation $presentation): array
    {
        $module = $presentation->module()->with('course')->first();
        if (!$module) {
            throw new RuntimeException('Modulo della presentazione non trovato.');
        }
        $content = trim((string) $module->content);
        if ($content === '') {
            throw new RuntimeException('Il modulo non ha un corpo da trasformare in presentazione.');
        }

        // Corsi Officina: nessuna scuola → brand di piattaforma (GLITCH).
        $theme = BrandProfile::forPlatform()->resolvedTheme();
        $schoolName = SchoolBranding::for(null)->instanceName();
        $subtitle = trim((string) ($module->course?->name ?? ''));

        return $this->buildFrom(
            $content,
            (string) $module->title,
            $subtitle,
            $schoolName,
            $theme,
            "module-presentations/{$module->id}/{$presentation->id}.pptx",
            [
                'subject' => $module->course?->name,
                'log_context' => ['module_id' => $module->id, 'module_presentation_id' => $presentation->id],
            ],
        );
    }

    /**
     * Orchestratore CONDIVISO, agnostico alla sorgente: contenuto+meta → spec
     * brandizzata → .pptx in storagePath. Usato da build(lesson) e buildForModule().
     * Il cuore (generateSpec/buildSpec/normalizeSlides/renderPptx) non cambia.
     *
     * @param array<string, mixed> $specOptions opzioni per generateSpec (subject, log_context)
     * @return array{file_path: string, meta: array, spec: array}
     */
    public function buildFrom(string $content, string $title, ?string $subtitle, string $schoolName, ResolvedTheme $theme, string $storagePath, array $specOptions = []): array
    {
        $generated = $this->generateSpec($content, $title, $specOptions);

        $spec = $this->buildSpec($title, (string) $subtitle, $schoolName, $theme, $generated['slides']);
        $spec['meta'] = $generated['meta'];

        $absPath = Storage::disk('local')->path($storagePath);
        Storage::disk('local')->makeDirectory(dirname($storagePath));

        $this->renderPptx($spec, $absPath);

        if (!Storage::disk('local')->exists($storagePath)) {
            throw new RuntimeException('Il file della presentazione non è stato creato.');
        }

        return [
            'file_path' => $storagePath,
            'meta' => array_merge($spec['meta'] ?? [], [
                'slides' => count($spec['slides'] ?? []), // include la cover
                'prompt_version' => self::PROMPT_VERSION,
                'filename' => Str::slug($title) . '.pptx',
            ]),
            // S0: la spec COMPLETA usata per il render (cover + slides + theme),
            // persistita dal chiamante per abilitare la correzione via prompt (S2).
            'spec' => $spec,
        ];
    }

    /**
     * S2 — correzione mirata via prompt. Richiede la spec persistita (S0).
     * Claude riceve la spec corrente + l'istruzione e restituisce SOLO le slide
     * da sostituire (per numero); il merge avviene in PHP, così copertina, tema,
     * ordine e count restano invariati per costruzione e cambiano solo le slide
     * indicate. Sorgente-agnostico: lavora sulla spec, non sul contenuto → uguale
     * per lezioni e moduli.
     *
     * @return array{file_path: string, meta: array, spec: array}
     */
    public function editSpec(LessonPresentation|ModulePresentation $presentation, string $instruction): array
    {
        $spec = $presentation->spec;
        if (!is_array($spec) || empty($spec['slides']) || !is_array($spec['slides'])) {
            throw new RuntimeException('Presentazione non correggibile: rigenerala dal sistema per abilitare la correzione.');
        }
        $storagePath = (string) $presentation->file_path;
        if ($storagePath === '') {
            throw new RuntimeException('File della presentazione assente: rigenerala dal sistema.');
        }

        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata.');
        }

        $current = json_encode(['slides' => $spec['slides']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $userMessage = "SPEC corrente (slide numerate da 1; la slide 1 è la copertina):\n```json\n{$current}\n```\n\nIstruzione dell'utente:\n{$instruction}";

        Log::info('Lesson pptx edit request', ['presentation_id' => $presentation->id]);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::CLAUDE_API_URL, [
            'model' => config('services.pptx.model', 'claude-sonnet-4-6'),
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $this->buildEditSystemPrompt(),
            'messages' => [['role' => 'user', 'content' => $userMessage]],
        ]);

        if (!$response->successful()) {
            Log::error('Lesson pptx edit Claude API failed', ['status' => $response->status()]);
            throw new RuntimeException('Errore Claude API: ' . $response->status());
        }

        $text = (string) ($response->json('content.0.text') ?? '');
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/```\s*$/', '', $text);
        $data = json_decode(trim($text), true);

        if (!is_array($data) || !isset($data['edits']) || !is_array($data['edits']) || $data['edits'] === []) {
            throw new RuntimeException('Risposta di correzione non valida (edits mancanti).');
        }

        // Merge MIRATO: sostituisce solo le slide indicate. Indice 0 (copertina)
        // e theme intoccabili per costruzione → garanzia di non-modifica collaterale.
        $newSlides = $spec['slides'];
        $changed = [];
        foreach ($data['edits'] as $edit) {
            if (!is_array($edit) || !isset($edit['slide']) || !is_array($edit['slide'])) {
                continue;
            }
            $i = (int) ($edit['slide_number'] ?? 0) - 1;
            if ($i < 1 || $i >= count($newSlides)) {
                continue; // mai la copertina (0), mai fuori range
            }
            $newSlides[$i] = $this->normalizeSlide($edit['slide']);
            $changed[] = $i + 1;
        }
        if ($changed === []) {
            throw new RuntimeException('Nessuna modifica valida applicabile (verifica il numero di slide).');
        }

        $newSpec = $spec;
        $newSpec['slides'] = $newSlides;

        $absPath = Storage::disk('local')->path($storagePath);
        Storage::disk('local')->makeDirectory(dirname($storagePath));
        $this->renderPptx($newSpec, $absPath);

        // Invalidazione anteprima (S1): i PNG si rigenerano al prossimo accesso.
        app(SlidePreviewService::class)->forget($storagePath);
        // GANCIO feature video (non ancora esistente): qui andranno marcati "stale"
        // gli eventuali derivati video/audio di questa presentazione.

        $coverTitle = trim((string) ($newSpec['slides'][0]['title'] ?? ''));

        return [
            'file_path' => $storagePath,
            'meta' => array_merge($spec['meta'] ?? [], [
                'slides' => count($newSpec['slides']),
                'prompt_version' => self::EDIT_PROMPT_VERSION,
                'model' => config('services.pptx.model', 'claude-sonnet-4-6'),
                'tokens_in' => (int) ($response->json('usage.input_tokens') ?? 0),
                'tokens_out' => (int) ($response->json('usage.output_tokens') ?? 0),
                'edited_slides' => $changed,
                'last_edit' => mb_substr($instruction, 0, 500),
                'filename' => Str::slug($coverTitle !== '' ? $coverTitle : 'presentazione') . '.pptx',
            ]),
            'spec' => $newSpec,
        ];
    }

    /** System prompt della correzione mirata: cambia solo ciò che l'istruzione chiede. */
    private function buildEditSystemPrompt(): string
    {
        $layouts = implode(', ', self::CONTENT_LAYOUTS);

        return <<<PROMPT
        Sei un editor di presentazioni didattiche. Ricevi (a) la SPEC JSON corrente delle slide e (b) un'istruzione in italiano.
        Applica ESCLUSIVAMENTE la modifica richiesta dall'istruzione: tutte le altre slide devono restare identiche.

        Le slide sono numerate a partire da 1. La slide 1 è la COPERTINA, generata dal sistema: NON modificarla. Non toccare il tema né l'ordine.

        Restituisci SOLO le slide che cambiano, in JSON valido e nient'altro, in questa forma esatta:
        {"edits":[{"slide_number": <numero 1-based della slide da modificare>, "slide": <oggetto slide completo aggiornato>}]}

        Regole:
        - Includi in "edits" SOLO le slide effettivamente modificate dall'istruzione; ometti tutte le altre.
        - Ogni "slide" deve usare uno dei layout consentiti ({$layouts}) con la stessa struttura della spec corrente.
        - Non aggiungere né rimuovere slide: modifica il contenuto di quelle esistenti.
        - Nessun testo, commento o markdown fuori dal JSON.
        PROMPT;
    }

    /**
     * Genera la spec delle slide via Claude.
     *
     * @return array{slides: array, meta: array}
     */
    public function generateSpec(string $content, string $title, array $options = []): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata.');
        }

        if (mb_strlen($content) > self::MAX_CONTENT_CHARS) {
            $content = mb_substr($content, 0, self::MAX_CONTENT_CHARS) . "\n[...troncato]";
        }

        $subject = trim((string) ($options['subject'] ?? ''));
        $systemPrompt = $this->buildSystemPrompt($subject);
        $userMessage = "Titolo lezione: **{$title}**\n\nCorpo della lezione (markdown):\n---\n{$content}\n---\n\nProduci la presentazione in JSON.";

        Log::info('Lesson pptx spec request', $options['log_context'] ?? []);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::CLAUDE_API_URL, [
            'model' => config('services.pptx.model', 'claude-sonnet-4-6'),
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userMessage]],
        ]);

        if (!$response->successful()) {
            Log::error('Lesson pptx Claude API failed', ['status' => $response->status()]);
            throw new RuntimeException('Errore Claude API: ' . $response->status());
        }

        $text = (string) ($response->json('content.0.text') ?? '');
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/```\s*$/', '', $text);
        $data = json_decode(trim($text), true);

        if (!is_array($data) || !isset($data['slides']) || !is_array($data['slides']) || empty($data['slides'])) {
            throw new RuntimeException('Spec presentazione non valida (JSON slides mancante).');
        }

        return [
            'slides' => $this->normalizeSlides($data['slides']),
            'meta' => [
                'model' => config('services.pptx.model', 'claude-sonnet-4-6'),
                'tokens_in' => (int) ($response->json('usage.input_tokens') ?? 0),
                'tokens_out' => (int) ($response->json('usage.output_tokens') ?? 0),
            ],
        ];
    }

    /**
     * Assembla la spec completa per il python: tema + cover brandizzata + slide di contenuto.
     * Puro (niente API): la cover è generata da noi, non dall'LLM.
     *
     * @param array<int, array<string, mixed>> $contentSlides slide già normalizzate
     * @return array<string, mixed>
     */
    public function buildSpec(string $title, string $subtitle, string $schoolName, ResolvedTheme $theme, array $contentSlides): array
    {
        $cover = [
            'layout' => 'cover',
            'title' => $title,
            'subtitle' => $subtitle,
            'school' => $schoolName,
        ];

        return [
            'theme' => $this->themePayload($theme),
            'slides' => array_merge([$cover], array_values($contentSlides)),
        ];
    }

    /**
     * Contratto layout CHIUSO: valida ogni slide dell'LLM contro il menù noto.
     * Layout sconosciuto o campi mancanti → fallback pulito a bullets_clean.
     *
     * @param array<int, mixed> $rawSlides
     * @return array<int, array<string, mixed>>
     */
    public function normalizeSlides(array $rawSlides): array
    {
        $out = [];
        foreach ($rawSlides as $s) {
            if (is_array($s)) {
                $out[] = $this->normalizeSlide($s);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $s @return array<string, mixed> */
    private function normalizeSlide(array $s): array
    {
        $layout = is_string($s['layout'] ?? null) ? $s['layout'] : '';
        $title = trim((string) ($s['title'] ?? ''));
        $notes = isset($s['notes']) ? trim((string) $s['notes']) : null;

        $clean = fn ($v) => trim((string) $v);

        switch ($layout) {
            case 'process_cards':
                $steps = [];
                foreach (is_array($s['steps'] ?? null) ? $s['steps'] : [] as $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $st = ['title' => $clean($step['title'] ?? ''), 'text' => $clean($step['text'] ?? '')];
                    if ($st['title'] !== '' || $st['text'] !== '') {
                        $steps[] = $st;
                    }
                }
                if ($steps === []) {
                    return $this->fallbackBullets($s);
                }

                return ['layout' => 'process_cards', 'title' => $title, 'steps' => array_slice($steps, 0, 5), 'notes' => $notes];

            case 'columns':
                $cols = [];
                foreach (is_array($s['columns'] ?? null) ? $s['columns'] : [] as $col) {
                    if (!is_array($col)) {
                        continue;
                    }
                    $c = ['icon' => $clean($col['icon'] ?? ''), 'title' => $clean($col['title'] ?? ''), 'text' => $clean($col['text'] ?? '')];
                    if ($c['title'] !== '' || $c['text'] !== '') {
                        $cols[] = $c;
                    }
                }
                if (count($cols) < 2) {
                    return $this->fallbackBullets($s);
                }

                return ['layout' => 'columns', 'title' => $title, 'columns' => array_slice($cols, 0, 3), 'notes' => $notes];

            case 'stat':
                $value = $clean($s['value'] ?? '');
                if ($value === '') {
                    return $this->fallbackBullets($s);
                }

                return ['layout' => 'stat', 'title' => $title, 'value' => $value, 'label' => $clean($s['label'] ?? ''), 'notes' => $notes];

            case 'bullets_clean':
                $bullets = $this->extractBullets($s);
                if ($bullets === []) {
                    return $this->fallbackBullets($s);
                }

                return ['layout' => 'bullets_clean', 'title' => $title, 'bullets' => $bullets, 'notes' => $notes];

            default:
                // Layout sconosciuto → fallback (mai una slide rotta).
                return $this->fallbackBullets($s);
        }
    }

    /**
     * Ricava una slide bullets_clean da una slide qualsiasi (anche con layout
     * ignoto): preserva il titolo e recupera testo da bullets/steps/columns.
     *
     * @param array<string, mixed> $s @return array<string, mixed>
     */
    private function fallbackBullets(array $s): array
    {
        $title = trim((string) ($s['title'] ?? '')) ?: 'Contenuto';
        $bullets = $this->extractBullets($s);

        if ($bullets === []) {
            foreach (['steps', 'columns'] as $key) {
                foreach (is_array($s[$key] ?? null) ? $s[$key] : [] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $line = trim(implode(' — ', array_filter([
                        trim((string) ($item['title'] ?? '')),
                        trim((string) ($item['text'] ?? '')),
                    ])));
                    if ($line !== '') {
                        $bullets[] = $line;
                    }
                }
            }
        }
        if ($bullets === [] && ($v = trim((string) ($s['value'] ?? ''))) !== '') {
            $bullets[] = trim($v . ' ' . trim((string) ($s['label'] ?? '')));
        }
        if ($bullets === []) {
            $bullets = [$title];
        }

        return [
            'layout' => 'bullets_clean',
            'title' => $title,
            'bullets' => array_slice($bullets, 0, 8),
            'notes' => isset($s['notes']) ? trim((string) $s['notes']) : null,
        ];
    }

    /** @param array<string, mixed> $s @return list<string> */
    private function extractBullets(array $s): array
    {
        return array_values(array_filter(array_map(
            fn ($b) => trim((string) $b),
            is_array($s['bullets'] ?? null) ? $s['bullets'] : []
        )));
    }

    /** @return array<string, mixed> payload tema per il python */
    private function themePayload(ResolvedTheme $theme): array
    {
        return [
            'ink' => $theme->ink,
            'background' => $theme->background,
            'accent' => $theme->accent,
            'fonts' => $theme->fonts->toArray(),
            'logo_path' => $this->resolveLogoAbsPath($theme),
        ];
    }

    /** Path ASSOLUTO del logo per il python: logo scuola se presente, poi quello piattaforma. */
    private function resolveLogoAbsPath(ResolvedTheme $theme): ?string
    {
        if ($theme->logoPath && Storage::disk('local')->exists($theme->logoPath)) {
            return Storage::disk('local')->path($theme->logoPath);
        }
        if (is_file(public_path('images/logo.png'))) {
            return public_path('images/logo.png');
        }

        return null;
    }

    /** Renderizza la spec in .pptx via python-pptx (Symfony Process, JSON su stdin). */
    public function renderPptx(array $spec, string $absOutPath): void
    {
        $python = config('services.pptx.python', '/home/noscite/venv/bin/python');
        $script = base_path('resources/python/build_pptx.py');

        $process = new Process([$python, $script, $absOutPath]);
        $process->setInput(json_encode($spec, JSON_UNESCAPED_UNICODE));
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Render pptx fallito: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function buildSystemPrompt(string $subject): string
    {
        $subjectLine = $subject !== '' ? "Materia: {$subject}." : '';

        return <<<TXT
Sei un docente di scuola superiore che prepara le slide di una lezione. {$subjectLine}

Trasforma il corpo della lezione in una presentazione efficace ed elegante. Una
slide per ogni SEZIONE principale (segui i titoli ## del markdown). Per OGNI slide
SCEGLI il layout più adatto dal MENÙ qui sotto e compila i campi che richiede.
NON generare la slide di copertina: viene creata automaticamente.

MENÙ DEI LAYOUT (usa esattamente queste stringhe in "layout"):

1) "process_cards" — un PROCESSO o passi SEQUENZIALI (3-5 step, es. un ciclo).
   Campi: "title", "steps": [{"title": "nome passo", "text": "1 frase breve"}].

2) "columns" — 2-3 CONCETTI PARALLELI / categorie / componenti affiancati.
   Campi: "title", "columns": [{"title": "nome", "text": "1-2 frasi"}].

3) "stat" — UN SINGOLO DATO o numero chiave da enfatizzare.
   Campi: "title" (opzionale), "value" (es. "70%", "3x"), "label" (cosa significa).

4) "bullets_clean" — un ELENCO di punti (3-6). Default quando nessun altro calza.
   Campi: "title", "bullets": ["punto 1", "punto 2"].

Regole:
- Frasi BREVI e sintetiche, mai muri di testo. Testo dei card/colonne molto conciso.
- Scegli "process_cards" per sequenze/cicli; "columns" per confronti/elementi paralleli;
  "stat" per un dato d'impatto; "bullets_clean" per il resto.
- Matematica in forma testuale leggibile (es. E = m·c²).
- Non inventare: usa solo i contenuti della lezione. Registro da scuola superiore.

Rispondi SOLO con JSON valido, senza testo extra:
{
  "slides": [
    {"layout": "process_cards", "title": "...", "steps": [{"title": "...", "text": "..."}], "notes": "opzionale"},
    {"layout": "bullets_clean", "title": "...", "bullets": ["...", "..."]}
  ]
}
TXT;
    }
}
