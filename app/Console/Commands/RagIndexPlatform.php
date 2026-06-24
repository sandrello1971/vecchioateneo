<?php

namespace App\Console\Commands;

use App\Models\DocumentRag;
use App\Services\RagService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RagIndexPlatform extends Command
{
    protected $signature = 'rag:index-platform {--base=https://atheneum.noscite.it}';
    protected $description = 'Indicizza le pagine pubbliche di Atheneum (home + corsi + contatti) per Minerva';

    private array $pages = [
        'home' => '/',
        'primus' => '/primus',
        'consilium' => '/consilium',
        'initium' => '/initium',
        'structura' => '/structura',
        'ai-agents-mcp' => '/ai-agents-mcp',
        'risorse' => '/risorse',
        'contatti' => '/contatti',
    ];

    public function handle(RagService $rag): int
    {
        $base = rtrim($this->option('base'), '/');

        DocumentRag::whereNull('course_id')
            ->where('title', 'LIKE', 'Officina Page: %')
            ->delete();

        foreach ($this->pages as $key => $path) {
            $url = $base . $path;
            $this->line("Fetching {$url}...");

            try {
                $res = Http::timeout(20)->get($url);
                if ($res->failed()) {
                    $this->warn("  skip {$key}: HTTP " . $res->status());
                    continue;
                }
                $text = $this->extractText($res->body());
                if (mb_strlen($text) < 50) {
                    $this->warn("  skip {$key}: testo troppo corto");
                    continue;
                }
                $title = 'Officina Page: ' . ucfirst($key);
                $rag->indexDocument($text, $title, null, null, null);
                $this->info("  ✓ {$key} (" . mb_strlen($text) . " char)");
            } catch (\Exception $e) {
                $this->error("  error {$key}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function extractText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
        $html = preg_replace('/<nav\b[^>]*>.*?<\/nav>/is', ' ', $html);
        $html = preg_replace('/<footer\b[^>]*>.*?<\/footer>/is', ' ', $html);
        $html = preg_replace('/<header\b[^>]*>.*?<\/header>/is', ' ', $html);
        $html = preg_replace('/<(br|p|div|h[1-6]|li|tr|td)\b[^>]*>/i', "\n", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Strip bytes not valid in UTF-8 (e.g. stray 0x8e from copy-paste Windows-1252)
        $text = preg_replace('/[\x80-\xFF](?![\x80-\xBF])|(?<![\xC2-\xF4])[\x80-\xBF]/', '', $text);
        $text = iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: '';
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{2,}/', "\n\n", $text);
        return trim($text);
    }
}
