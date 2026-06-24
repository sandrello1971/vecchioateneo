<?php

namespace App\Services\Schola;

use App\Models\TeachingDocument;
use App\Services\VideoAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

// Estrazione testo dai materiali grezzi del docente. Un metodo per source_type.
// Ritorna ['text' => markdown, 'meta' => [...]] oppure lancia RuntimeException
// (intercettata dal job, che imposta status=failed + failure_reason).
class TeachingDocumentExtractor
{
    private const VISION_API_URL = 'https://api.anthropic.com/v1/messages';
    private const VISION_PROMPT = <<<TXT
Trascrivi FEDELMENTE in Markdown tutto il testo presente nell'immagine.
Regole:
- Mantieni la struttura: usa heading (#, ##) dove ci sono titoli.
- Le formule matematiche vanno in LaTeX inline (\$...\$) o display (\$\$...\$\$).
- Riporta elenchi e tabelle in Markdown.
- Dove il testo è illeggibile scrivi [illeggibile].
- NON aggiungere commenti tuoi: solo la trascrizione.
TXT;

    public function __construct(private VideoAIService $videoai) {}

    public function extract(TeachingDocument $doc): array
    {
        return match ($doc->source_type) {
            'audio'   => $this->extractAudio($doc),
            'youtube' => $this->extractYouTube($doc),
            'photos'  => $this->extractPhotos($doc),
            'pdf'     => $this->extractPdf($doc),
            'docx'    => $this->extractDocx($doc),
            'text'    => $this->extractText($doc),
            default   => throw new RuntimeException("source_type non supportato: {$doc->source_type}"),
        };
    }

    private function abs(string $relativePath): string
    {
        if (!Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException("File sorgente mancante: {$relativePath}");
        }
        return Storage::disk('local')->path($relativePath);
    }

    private function firstSource(TeachingDocument $doc): string
    {
        $files = $doc->source_files ?? [];
        if (empty($files)) {
            throw new RuntimeException('Nessun file sorgente associato al documento.');
        }
        return $this->abs($files[0]);
    }

    private function extractAudio(TeachingDocument $doc): array
    {
        $path = $this->firstSource($doc);
        $result = $this->videoai->transcribeAudio($path, basename($path));

        return [
            'text' => trim((string) ($result['transcript'] ?? '')),
            'meta' => [
                'method' => 'whisper',
                'language' => $result['language'] ?? null,
                'duration_seconds' => $result['duration_seconds'] ?? null,
                // Segments con minutaggio: servono alle citazioni RAG (pacchetto 6).
                'segments' => $this->normalizeSegments($result['segments'] ?? null),
            ],
        ];
    }

    private function extractYouTube(TeachingDocument $doc): array
    {
        $result = $this->videoai->transcribeYouTube($doc->source_url);

        return [
            'text' => trim((string) ($result['transcript'] ?? '')),
            'meta' => [
                'method' => $result['method'] ?? 'whisper', // native_transcript | whisper
                'language' => $result['language'] ?? null,
                'duration_seconds' => $result['duration_seconds'] ?? null,
                'video' => $result['video'] ?? null, // titolo, canale, durata
                // Segments con minutaggio per le citazioni RAG (pacchetto 6).
                'segments' => $this->normalizeSegments($result['segments'] ?? null),
            ],
        ];
    }

    /**
     * Normalizza i segments di videoai in {start_seconds, end_seconds, text}.
     * Accetta le chiavi note (start_seconds/end_seconds oppure start/end).
     * Ritorna null se assenti/non validi (es. trascrizioni senza timing).
     *
     * @return array<int, array{start_seconds: float, end_seconds: float, text: string}>|null
     */
    private function normalizeSegments($segments): ?array
    {
        if (!is_array($segments) || empty($segments)) {
            return null;
        }

        $out = [];
        foreach ($segments as $seg) {
            if (!is_array($seg)) {
                continue;
            }
            $start = $seg['start_seconds'] ?? $seg['start'] ?? null;
            $end = $seg['end_seconds'] ?? $seg['end'] ?? null;
            $text = trim((string) ($seg['text'] ?? ''));
            if ($start === null || $text === '') {
                continue;
            }
            $out[] = [
                'start_seconds' => round((float) $start, 2),
                'end_seconds' => round((float) ($end ?? $start), 2),
                'text' => $text,
            ];
        }

        return $out ?: null;
    }

    private function extractPhotos(TeachingDocument $doc): array
    {
        $files = $doc->source_files ?? [];
        if (empty($files)) {
            throw new RuntimeException('Nessuna immagine da trascrivere.');
        }

        $pages = [];
        $tokensIn = 0;
        $tokensOut = 0;

        // Una chiamata per immagine, NELL'ORDINE dato, poi riassemblaggio.
        foreach ($files as $i => $rel) {
            $vision = $this->visionTranscribe($this->abs($rel));
            $pages[] = $vision['text'];
            $tokensIn += $vision['tokens_in'];
            $tokensOut += $vision['tokens_out'];
        }

        return [
            'text' => $this->reassemble($pages),
            'meta' => [
                'method' => 'vision',
                'pages' => count($files),
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'estimated_cost_usd' => $this->estimateCost($tokensIn, $tokensOut),
            ],
        ];
    }

    private function extractPdf(TeachingDocument $doc): array
    {
        $path = $this->firstSource($doc);

        // 1) Layer testo? pdftotext; se produce testo significativo lo usiamo.
        $text = $this->pdfToText($path);
        if (mb_strlen(trim($text)) >= 40) {
            return [
                'text' => trim($text),
                'meta' => ['method' => 'pdftext'],
            ];
        }

        // 2) PDF scansionato: rasterizza le pagine e usa lo stesso percorso vision.
        $images = $this->rasterizePdf($path);
        $pages = [];
        $tokensIn = 0;
        $tokensOut = 0;
        try {
            foreach ($images as $img) {
                $vision = $this->visionTranscribe($img);
                $pages[] = $vision['text'];
                $tokensIn += $vision['tokens_in'];
                $tokensOut += $vision['tokens_out'];
            }
        } finally {
            foreach ($images as $img) {
                @unlink($img); // pulizia file temporanei rasterizzati
            }
        }

        return [
            'text' => $this->reassemble($pages),
            'meta' => [
                'method' => 'vision_ocr',
                'pages' => count($images),
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'estimated_cost_usd' => $this->estimateCost($tokensIn, $tokensOut),
            ],
        ];
    }

    private function extractDocx(TeachingDocument $doc): array
    {
        $path = $this->firstSource($doc);

        $process = new Process(['pandoc', $path, '--from=docx', '--to=gfm', '--wrap=none']);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Conversione DOCX (pandoc) fallita: ' . trim($process->getErrorOutput()));
        }

        return [
            'text' => trim($process->getOutput()),
            'meta' => ['method' => 'pandoc'],
        ];
    }

    private function extractText(TeachingDocument $doc): array
    {
        $path = $this->firstSource($doc);
        $text = file_get_contents($path);
        if ($text === false) {
            throw new RuntimeException('Impossibile leggere il testo incollato.');
        }

        return [
            'text' => trim($text),
            'meta' => ['method' => 'passthrough'],
        ];
    }

    // ===== Helpers =====

    private function reassemble(array $pages): string
    {
        $out = [];
        foreach ($pages as $i => $page) {
            $page = trim((string) $page);
            if ($page === '') {
                continue;
            }
            $out[] = $page;
        }
        return implode("\n\n", $out);
    }

    private function pdfToText(string $absPath): string
    {
        $process = new Process(['pdftotext', '-layout', $absPath, '-']);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            // pdftotext assente o errore → trattiamo come "nessun layer testo"
            return '';
        }
        return $process->getOutput();
    }

    private function rasterizePdf(string $absPath): array
    {
        $tmpPrefix = sys_get_temp_dir() . '/tdoc_' . bin2hex(random_bytes(6));
        // pdftoppm: una PNG per pagina, prefisso tmpPrefix-1.png, -2.png, ...
        $process = new Process(['pdftoppm', '-png', '-r', '150', $absPath, $tmpPrefix]);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'Rasterizzazione PDF (pdftoppm) non riuscita: il PDF non ha testo selezionabile e '
                . 'poppler-utils non è disponibile. ' . trim($process->getErrorOutput())
            );
        }

        $images = glob($tmpPrefix . '*.png') ?: [];
        sort($images); // ordine pagine
        if (empty($images)) {
            throw new RuntimeException('Nessuna pagina rasterizzata dal PDF.');
        }
        return $images;
    }

    private function visionTranscribe(string $absImagePath): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new RuntimeException('Anthropic API key non configurata.');
        }

        $bytes = file_get_contents($absImagePath);
        if ($bytes === false) {
            throw new RuntimeException("Immagine non leggibile: {$absImagePath}");
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post(self::VISION_API_URL, [
                'model' => config('services.anthropic.vision_model', 'claude-sonnet-4-5'),
                'max_tokens' => (int) config('services.anthropic.vision_max_tokens', 4000),
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $this->imageMediaType($absImagePath),
                                'data' => base64_encode($bytes),
                            ],
                        ],
                        ['type' => 'text', 'text' => self::VISION_PROMPT],
                    ],
                ]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Claude vision error: ' . $response->status());
        }

        $text = $response->json('content.0.text');
        if ($text === null) {
            throw new RuntimeException('Risposta vision vuota.');
        }

        return [
            'text' => $text,
            'tokens_in' => (int) $response->json('usage.input_tokens', 0),
            'tokens_out' => (int) $response->json('usage.output_tokens', 0),
        ];
    }

    private function imageMediaType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    // Stima costo (USD) indicativa per monitoraggio consumi (Sonnet:
    // ~3$/Mtok input, ~15$/Mtok output). Solo ordine di grandezza.
    private function estimateCost(int $tokensIn, int $tokensOut): float
    {
        return round(($tokensIn / 1_000_000) * 3.0 + ($tokensOut / 1_000_000) * 15.0, 4);
    }
}
