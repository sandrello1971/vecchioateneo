<?php

namespace App\Services\Schola;

use App\Models\LessonVideo;
use App\Models\ModuleVideo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * V1 — genera il COPIONE narrato per slide con Claude, dalla presentazione sorgente
 * (ready, con spec). Source-agnostic: lavora sulla spec del video->presentation, quindi
 * uguale per lezioni e moduli. Nessun TTS/ffmpeg (V3): qui solo testo, salvato come
 * script=[{slide_number,text}] e script_status='draft'. Chunking per presentazioni
 * lunghe + cache su hash(spec+prompt_version) per non richiamare Claude inutilmente.
 */
class VideoScriptService
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const PROMPT_VERSION = 'video-script-v1';
    private const EDIT_PROMPT_VERSION = 'video-script-edit-v1';
    private const CHUNK = 10;
    private const TEMPERATURE = 0.5;
    private const MAX_TOKENS = 4000;

    /**
     * @return array{script: array<int,array{slide_number:int,text:string}>, meta: array, cached: bool}
     */
    public function generateScript(LessonVideo|ModuleVideo $video, bool $force = false): array
    {
        $presentation = $video->presentation;
        if (!$presentation || $presentation->status !== 'ready' || empty($presentation->spec)) {
            throw new RuntimeException('Rigenera le slide dal sistema per abilitare il copione del video.');
        }

        $slides = array_values($presentation->spec['slides'] ?? []);
        if ($slides === []) {
            throw new RuntimeException('La presentazione non ha slide.');
        }

        $hash = md5(json_encode($slides, JSON_UNESCAPED_UNICODE) . '|' . self::PROMPT_VERSION);

        // CACHE: copione già in bozza e slide invariate → niente nuova chiamata a Claude.
        if (!$force && $video->script_status === 'draft' && !empty($video->script)
            && ($video->generation_meta['script_hash'] ?? null) === $hash) {
            return ['script' => $video->script, 'meta' => (array) $video->generation_meta, 'cached' => true];
        }

        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata.');
        }

        $summaries = [];
        foreach ($slides as $i => $slide) {
            $summaries[] = ['n' => $i + 1, 'summary' => $this->summarizeSlide(is_array($slide) ? $slide : [], $i + 1)];
        }

        Log::info('Video script request', ['video_id' => $video->id, 'slides' => count($slides)]);

        $byNumber = [];
        $tokensIn = 0;
        $tokensOut = 0;
        $chunks = 0;
        foreach (array_chunk($summaries, self::CHUNK) as $chunk) {
            $chunks++;
            [$lines, $ti, $to] = $this->callClaude($apiKey, $chunk);
            $tokensIn += $ti;
            $tokensOut += $to;
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $n = (int) ($line['slide_number'] ?? 0);
                $text = trim((string) ($line['text'] ?? ''));
                if ($n >= 1 && $text !== '') {
                    $byNumber[$n] = $text;
                }
            }
        }

        // Una riga per OGNI slide, in ordine; fallback al titolo se Claude l'ha saltata
        // (garanzia di copertura completa anche su presentazioni lunghe / chunk parziali).
        $script = [];
        foreach ($slides as $i => $slide) {
            $n = $i + 1;
            $script[] = ['slide_number' => $n, 'text' => $byNumber[$n] ?? $this->fallbackText(is_array($slide) ? $slide : [])];
        }

        return [
            'script' => $script,
            'meta' => array_merge((array) $video->generation_meta, [
                'prompt_version' => self::PROMPT_VERSION,
                'model' => config('services.pptx.model', 'claude-sonnet-4-6'),
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'slides' => count($slides),
                'chunk_count' => $chunks,
                'script_hash' => $hash,
            ]),
            'cached' => false,
        ];
    }

    /**
     * V2 — correzione A MANO di una riga del copione: sostituisce SOLO quella slide,
     * riporta a 'draft' e invalida i derivati audio/video di quella slide.
     */
    public function editLine(LessonVideo|ModuleVideo $video, int $slideNumber, string $text): void
    {
        $script = $this->replaceLine($video->script ?? [], $slideNumber, trim($text));
        $video->update(['script' => $script, 'script_status' => 'draft']);
        $this->invalidateDerivatives($video, $slideNumber);
    }

    /**
     * V2 — correzione VIA PROMPT di una riga: Claude riscrive SOLO quel testo (merge
     * mirato per indice, lato PHP). Le altre righe non si toccano. → 'draft' + invalida.
     */
    public function editLineViaPrompt(LessonVideo|ModuleVideo $video, int $slideNumber, string $instruction): void
    {
        $script = $video->script ?? [];
        $current = null;
        foreach ($script as $line) {
            if ((int) ($line['slide_number'] ?? 0) === $slideNumber) {
                $current = (string) ($line['text'] ?? '');
                break;
            }
        }
        if ($current === null) {
            throw new RuntimeException('Slide non presente nel copione.');
        }

        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata.');
        }

        $presentation = $video->presentation;
        $slide = $presentation->spec['slides'][$slideNumber - 1] ?? [];
        $context = $this->summarizeSlide(is_array($slide) ? $slide : [], $slideNumber);

        $newText = $this->callClaudeRewrite($apiKey, $current, $instruction, $context);

        $updated = $this->replaceLine($script, $slideNumber, $newText);
        $video->update([
            'script' => $updated,
            'script_status' => 'draft',
            'generation_meta' => array_merge((array) $video->generation_meta, [
                'last_line_edit' => ['slide_number' => $slideNumber, 'prompt_version' => self::EDIT_PROMPT_VERSION],
            ]),
        ]);
        $this->invalidateDerivatives($video, $slideNumber);
    }

    /** Sostituisce il testo della riga slideNumber; errore se la slide non c'è. */
    private function replaceLine(array $script, int $slideNumber, string $text): array
    {
        $found = false;
        foreach ($script as &$line) {
            if ((int) ($line['slide_number'] ?? 0) === $slideNumber) {
                $line['text'] = $text;
                $found = true;
                break;
            }
        }
        unset($line);
        if (!$found) {
            throw new RuntimeException('Slide non presente nel copione.');
        }

        return $script;
    }

    /**
     * Invalida i derivati di una slide quando il copione cambia. GANCIO V3: quando
     * esisteranno gli MP3 per-slide e l'mp4, qui vanno rimossi. Per ora: se esiste un
     * mp4 reso, lo si elimina e il video torna 'pending' (da rifare).
     */
    private function invalidateDerivatives(LessonVideo|ModuleVideo $video, ?int $slideNumber = null): void
    {
        if ($video->file_path && Storage::disk('local')->exists($video->file_path)) {
            Storage::disk('local')->delete($video->file_path);
        }
        // R3 — copione cambiato → mp4 + indice + pubblicazione stale: va rifatto tutto.
        if ($video->status === 'ready' || $video->indexed_at || $video->published_at) {
            $video->update(['status' => 'pending', 'file_path' => null, 'indexed_at' => null, 'published_at' => null]);
        }
    }

    /** Riscrittura di una singola riga: ritorna SOLO il nuovo testo (no JSON). */
    private function callClaudeRewrite(string $apiKey, string $current, string $instruction, string $context): string
    {
        $user = "Contesto della slide: {$context}\n\nTesto di narrazione attuale:\n{$current}\n\nIstruzione: {$instruction}";

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::CLAUDE_API_URL, [
            'model' => config('services.pptx.model', 'claude-sonnet-4-6'),
            'max_tokens' => 800,
            'temperature' => self::TEMPERATURE,
            'system' => 'Riscrivi SOLO questo testo di narrazione secondo l\'istruzione. Mantieni '
                . 'lunghezza simile (~80-100 parole), tono parlato e discorsivo, lingua italiana. '
                . 'Restituisci SOLO il nuovo testo, senza virgolette, titoli o preamboli.',
            'messages' => [['role' => 'user', 'content' => $user]],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore Claude API: ' . $response->status());
        }

        $text = trim((string) ($response->json('content.0.text') ?? ''));
        $text = trim(preg_replace('/^```.*?\n|\n```$/s', '', $text));
        if ($text === '') {
            throw new RuntimeException('Riscrittura vuota.');
        }

        return $text;
    }

    /** @return array{0: array, 1: int, 2: int} [lines, tokensIn, tokensOut] */
    private function callClaude(string $apiKey, array $chunk): array
    {
        $list = '';
        foreach ($chunk as $c) {
            $list .= "Slide {$c['n']}: {$c['summary']}\n";
        }
        $numbers = implode(', ', array_map(fn ($c) => $c['n'], $chunk));
        $user = "Scrivi il copione narrato. Rispondi SOLO con le slide numerate {$numbers}, "
            . "una voce per slide:\n\n{$list}";

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::CLAUDE_API_URL, [
            'model' => config('services.pptx.model', 'claude-sonnet-4-6'),
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $this->systemPrompt(),
            'messages' => [['role' => 'user', 'content' => $user]],
        ]);

        if (!$response->successful()) {
            Log::error('Video script Claude API failed', ['status' => $response->status()]);
            throw new RuntimeException('Errore Claude API: ' . $response->status());
        }

        $text = (string) ($response->json('content.0.text') ?? '');
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/```\s*$/', '', $text);
        $data = json_decode(trim($text), true);

        if (!is_array($data)) {
            throw new RuntimeException('Copione non valido (JSON atteso).');
        }
        // Accetta sia [...] sia {"script":[...]}.
        $lines = (isset($data['script']) && is_array($data['script'])) ? $data['script'] : $data;

        return [
            is_array($lines) ? $lines : [],
            (int) ($response->json('usage.input_tokens') ?? 0),
            (int) ($response->json('usage.output_tokens') ?? 0),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Sei un autore di copioni per video formativi. Ricevi un elenco di slide (numero +
        titolo + punti). Per OGNI slide scrivi un testo NARRATO, discorsivo e parlato — come
        lo direbbe a voce un narratore — NON un elenco di punti. Max ~80-100 parole per slide.
        La slide 1 (copertina) è una breve introduzione che presenta l'argomento.
        Lingua: italiano. Mantieni i numeri di slide ricevuti.
        Restituisci SOLO JSON valido, nient'altro, nella forma:
        [{"slide_number": <numero>, "text": "<testo narrato>"}]
        PROMPT;
    }

    /** Sintesi testuale di una slide della spec (titolo + contenuto), per il prompt. */
    private function summarizeSlide(array $slide, int $n): string
    {
        $layout = $slide['layout'] ?? '';
        $title = trim((string) ($slide['title'] ?? ''));

        if ($layout === 'cover') {
            $sub = trim((string) ($slide['subtitle'] ?? ''));

            return trim("[copertina] {$title}" . ($sub !== '' ? " — {$sub}" : ''));
        }

        $parts = [];
        if ($title !== '') {
            $parts[] = $title;
        }
        if (!empty($slide['bullets']) && is_array($slide['bullets'])) {
            $parts[] = 'Punti: ' . implode('; ', array_map('strval', $slide['bullets']));
        }
        if (!empty($slide['steps']) && is_array($slide['steps'])) {
            $parts[] = 'Passi: ' . implode('; ', array_map(fn ($s) => trim(($s['title'] ?? '') . ' ' . ($s['text'] ?? '')), $slide['steps']));
        }
        if (!empty($slide['columns']) && is_array($slide['columns'])) {
            $parts[] = 'Colonne: ' . implode('; ', array_map(fn ($c) => trim(($c['title'] ?? '') . ' ' . ($c['text'] ?? '')), $slide['columns']));
        }
        if (!empty($slide['value'])) {
            $parts[] = 'Dato: ' . $slide['value'] . ' ' . ($slide['label'] ?? '');
        }

        return $parts === [] ? ($title !== '' ? $title : "Slide {$n}") : implode('. ', $parts);
    }

    private function fallbackText(array $slide): string
    {
        $title = trim((string) ($slide['title'] ?? ''));

        return $title !== '' ? $title : 'Contenuto della slide.';
    }
}
