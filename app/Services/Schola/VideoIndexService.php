<?php

namespace App\Services\Schola;

use App\Models\LessonVideo;
use App\Models\ModuleVideo;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * R2 — indicizza in videoai un video GENERATO usando il testo GIÀ NOTO: il copione
 * (parlato) e il contenuto delle slide dalla spec (visivo), con i timestamp di
 * slide_timings. Nessun Whisper/Vision → nessun costo API: solo embedding mpnet (CPU)
 * lato videoai via POST /api/videos/{id}/index_chunks (R1). Idempotente (R1 fa reset).
 */
class VideoIndexService
{
    public function indexGenerated(LessonVideo|ModuleVideo $video): array
    {
        if ($video->status !== 'ready') {
            throw new RuntimeException('Il video non è pronto: genera prima il video.');
        }

        $script = $video->script ?? [];
        $timings = $video->generation_meta['slide_timings'] ?? [];
        if (empty($script) || empty($timings)) {
            throw new RuntimeException('Copione o slide_timings mancanti: rigenera il video per indicizzarlo.');
        }

        $timeBy = [];
        foreach ($timings as $t) {
            $timeBy[(int) ($t['slide_number'] ?? 0)] = $t;
        }
        $specSlides = $video->presentation?->spec['slides'] ?? [];

        $chunks = [];
        foreach ($script as $line) {
            $n = (int) ($line['slide_number'] ?? 0);
            $t = $timeBy[$n] ?? null;
            if (!$t) {
                continue;
            }
            $start = (float) ($t['start_sec'] ?? 0);
            $end = (float) ($t['end_sec'] ?? 0);

            // PARLATO (copione)
            $spoken = trim((string) ($line['text'] ?? ''));
            if ($spoken !== '') {
                $chunks[] = ['text' => $spoken, 'start' => $start, 'end' => $end, 'type' => 'transcript'];
            }

            // VISIVO NOTO (testo a schermo dalla spec) — niente Vision
            $visual = $this->visualText($specSlides[$n - 1] ?? []);
            if ($visual !== '') {
                $chunks[] = ['text' => $visual, 'start' => $start, 'end' => $end, 'type' => 'frame'];
            }
        }

        if ($chunks === []) {
            throw new RuntimeException('Nessun contenuto indicizzabile nel video.');
        }

        $videoAiId = $video->video_ai_id ?: $this->makeVideoAiId($video);

        $response = Http::withHeaders(['X-Internal-Token' => (string) config('services.videoai.token')])
            ->timeout(60)
            ->post(rtrim((string) config('services.videoai.url'), '/') . "/api/videos/{$videoAiId}/index_chunks", [
                'chunks' => $chunks,
                'meta' => [
                    'title' => $this->title($video),
                    'source' => 'generated',
                    'kind' => $video instanceof LessonVideo ? 'lesson' : 'module',
                ],
            ]);

        if (!$response->successful()) {
            // Errore gestito: il video resta NON indicizzato (nessuno stato falso "ok").
            throw new RuntimeException('Indicizzazione videoai fallita: ' . $response->status());
        }

        $video->update(['video_ai_id' => $videoAiId, 'indexed_at' => now()]);

        return [
            'video_ai_id' => $videoAiId,
            'chunks' => count($chunks),
            'indexed' => $response->json('indexed_chunks'),
        ];
    }

    /** Id-collection videoai stabile per un video generato (preparazione R3). */
    private function makeVideoAiId(LessonVideo|ModuleVideo $video): string
    {
        return ($video instanceof LessonVideo ? 'gen_lessonvideo_' : 'gen_modulevideo_') . $video->id;
    }

    /** Testo a schermo noto di una slide della spec (titolo + bullet/steps/columns/stat). */
    private function visualText(array $slide): string
    {
        $parts = [];
        $title = trim((string) ($slide['title'] ?? ''));
        if ($title !== '') {
            $parts[] = $title;
        }
        if (!empty($slide['subtitle'])) {
            $parts[] = (string) $slide['subtitle'];
        }
        if (!empty($slide['bullets']) && is_array($slide['bullets'])) {
            $parts[] = implode('; ', array_map('strval', $slide['bullets']));
        }
        if (!empty($slide['steps']) && is_array($slide['steps'])) {
            $parts[] = implode('; ', array_map(fn ($s) => trim(($s['title'] ?? '') . ' ' . ($s['text'] ?? '')), $slide['steps']));
        }
        if (!empty($slide['columns']) && is_array($slide['columns'])) {
            $parts[] = implode('; ', array_map(fn ($c) => trim(($c['title'] ?? '') . ' ' . ($c['text'] ?? '')), $slide['columns']));
        }
        if (!empty($slide['value'])) {
            $parts[] = $slide['value'] . ' ' . ($slide['label'] ?? '');
        }

        return trim(implode('. ', array_filter($parts)));
    }

    private function title(LessonVideo|ModuleVideo $video): string
    {
        return $video instanceof LessonVideo
            ? (string) ($video->lesson?->title ?? 'Lezione')
            : (string) ($video->module?->title ?? 'Modulo');
    }
}
