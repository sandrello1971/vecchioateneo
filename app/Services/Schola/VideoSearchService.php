<?php

namespace App\Services\Schola;

use Illuminate\Support\Facades\Http;

/**
 * R4 — ricerca PER-VIDEO su videoai (un solo video_id). Stessa ricerca per generati e
 * caricati: cambia solo il video_ai_id risolto a monte. Ritorna i match con start (sec)
 * per il seek nel player. Niente cross-video.
 */
class VideoSearchService
{
    /**
     * @return array<int, array{start: float, text: string, type: string, timestamp_str: string}>
     */
    public function perVideo(string $videoAiId, string $query): array
    {
        $response = Http::withHeaders(['X-Internal-Token' => (string) config('services.videoai.token')])
            ->timeout(30)
            ->post(rtrim((string) config('services.videoai.url'), '/') . '/api/search', [
                'question' => $query,
                'video_ids' => [$videoAiId], // SOLO questo video
            ]);

        if (!$response->successful()) {
            return [];
        }

        $results = $response->json();
        $matches = is_array($results) && !empty($results) ? ($results[0]['matches'] ?? []) : [];
        $max = (int) config('services.videoai.search_max_results', 5);

        return array_map(fn ($m) => [
            'start' => (float) ($m['start'] ?? 0),
            'text' => (string) ($m['text'] ?? ''),
            'type' => (string) ($m['type'] ?? ''),
            'timestamp_str' => (string) ($m['timestamp_str'] ?? ''),
        ], array_slice(is_array($matches) ? $matches : [], 0, $max));
    }
}
