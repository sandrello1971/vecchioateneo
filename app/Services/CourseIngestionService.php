<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class CourseIngestionService
{
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';

    public function extractText(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getPathname();

        if ($ext === 'txt') {
            return file_get_contents($path) ?: '';
        }

        if ($ext === 'pdf') {
            try {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($path);
                return $pdf->getText();
            } catch (\Exception $e) {
                Log::warning('PDF extract error: ' . $e->getMessage());
                return '';
            }
        }

        if (in_array($ext, ['doc', 'docx'])) {
            try {
                $phpWord = WordIOFactory::load($path, $ext === 'docx' ? 'Word2007' : 'MsDoc');
                $out = '';
                foreach ($phpWord->getSections() as $section) {
                    $out .= $this->extractFromElements($section->getElements()) . "\n";
                }
                return $out;
            } catch (\Exception $e) {
                Log::warning('DOCX extract error: ' . $e->getMessage());
                return '';
            }
        }

        return '';
    }

    private function extractFromElements(array $elements): string
    {
        $out = '';
        foreach ($elements as $el) {
            $class = class_basename($el);
            if (method_exists($el, 'getText') && is_string($el->getText())) {
                $out .= $el->getText() . "\n";
            } elseif (method_exists($el, 'getElements')) {
                $out .= $this->extractFromElements($el->getElements()) . "\n";
            }
            if ($class === 'Title' || str_starts_with($class, 'Heading')) {
                $out .= "\n";
            }
        }
        return $out;
    }

    public function parseExamToQuestions(string $text): array
    {
        $text = $this->normalizeText($text);
        if (empty(trim($text))) {
            throw new \RuntimeException('Testo esame vuoto.');
        }

        $brand = atheneum_setting('instance_name', 'di formazione professionale');
        $systemPrompt = <<<SYS
Sei un assistant specializzato nell'estrarre quiz a risposta multipla da documenti d'esame {$brand}.
Ricevi il testo di un documento esame che contiene domande con opzioni multiple e risposta corretta indicata.

Il tuo compito:
1. Identificare il titolo del quiz (se presente) e la passing_score (se presente, default 70)
2. Estrarre ogni domanda con le sue 4 opzioni e la risposta corretta
3. Se è presente una spiegazione associata alla risposta corretta, includila
4. Rispondi SOLO con JSON valido, nessun testo extra, nessun markdown

Formato JSON richiesto:
{
  "quiz_title": "Esame finale — nome corso",
  "passing_score": 70,
  "time_limit_minutes": null,
  "questions": [
    {
      "question": "testo domanda",
      "options": ["opzione A", "opzione B", "opzione C", "opzione D"],
      "correct_answer": "testo esatto dell'opzione corretta",
      "explanation": "spiegazione se presente, altrimenti stringa vuota"
    }
  ]
}

Regole:
- Ogni domanda deve avere esattamente 4 opzioni
- correct_answer deve essere la stringa esatta come appare in options (non la lettera A/B/C/D)
- Se nel documento la risposta è indicata come lettera (es. "Risposta corretta: B"), devi mappare alla stringa completa dell'opzione B
- Se una domanda ha più o meno di 4 opzioni, salta la domanda (non includerla)
- Non inventare domande non presenti nel documento
SYS;

        $userPrompt = "Estrai le domande da questo documento d'esame. Testo:\n\n" . substr($text, 0, 120000);

        return $this->callClaudeJson($systemPrompt, $userPrompt);
    }

    public function callClaudeJsonPublic(string $systemPrompt, string $userPrompt): array
    {
        return $this->callClaudeJson($systemPrompt, $userPrompt);
    }

    private function callClaudeJson(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY non configurata.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->withOptions(['stream' => true])
            ->connectTimeout(30)
            ->timeout(600)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => self::CLAUDE_MODEL,
                'max_tokens' => 64000,
                'stream' => true,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ]);

        if ($response->status() >= 400) {
            $errorBody = (string) $response->toPsrResponse()->getBody();
            Log::error('Claude API failed', ['status' => $response->status(), 'body' => substr($errorBody, 0, 500)]);
            throw new \RuntimeException('Claude API error (HTTP ' . $response->status() . ').');
        }

        $text = $this->readSseTextStream($response->toPsrResponse()->getBody());

        $json = $this->stripJsonFences($text);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            Log::error('Claude JSON decode failed', [
                'total_length' => strlen($text),
                'first_200' => substr($text, 0, 200),
                'last_200' => substr($text, -200),
            ]);
            throw new \RuntimeException('Risposta Claude non è JSON valido.');
        }

        return $decoded;
    }

    private function readSseTextStream(\Psr\Http\Message\StreamInterface $stream): string
    {
        $accumulated = '';
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $eventName = null;
                $dataLines = [];
                foreach (explode("\n", $rawEvent) as $line) {
                    if (str_starts_with($line, 'event: ')) {
                        $eventName = substr($line, 7);
                    } elseif (str_starts_with($line, 'data: ')) {
                        $dataLines[] = substr($line, 6);
                    }
                }

                if (empty($dataLines)) {
                    continue;
                }

                $decoded = json_decode(implode("\n", $dataLines), true);
                if (!is_array($decoded)) {
                    continue;
                }

                if ($eventName === 'content_block_delta') {
                    $delta = $decoded['delta'] ?? [];
                    if (($delta['type'] ?? '') === 'text_delta') {
                        $accumulated .= $delta['text'] ?? '';
                    }
                } elseif ($eventName === 'error') {
                    $msg = $decoded['error']['message'] ?? 'Unknown streaming error';
                    throw new \RuntimeException('Claude streaming error: ' . $msg);
                } elseif ($eventName === 'message_delta') {
                    $stopReason = $decoded['delta']['stop_reason'] ?? null;
                    if ($stopReason === 'max_tokens') {
                        throw new \RuntimeException(
                            'Risposta Claude troncata: limite max_tokens raggiunto. Il documento potrebbe essere troppo lungo per essere processato in una singola chiamata.'
                        );
                    }
                }
            }
        }

        return $accumulated;
    }

    private function stripJsonFences(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return $m[0];
        }
        return $text;
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace("/\r\n?/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
