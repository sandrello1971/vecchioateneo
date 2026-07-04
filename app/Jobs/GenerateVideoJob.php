<?php

namespace App\Jobs;

use App\Models\LessonVideo;
use App\Models\ModuleVideo;
use App\Services\Schola\VideoRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

// V3 — render asincrono del video MP4 (TTS + ffmpeg). Lock 'video-render' = UN SOLO
// render alla volta (ffmpeg è pesante, server condiviso). status generating→ready/failed.
// Polimorfico: $videoType = 'lesson' | 'module'.
class GenerateVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1500;
    public int $tries = 1;

    public function __construct(public string $videoId, public string $videoType) {}

    public function handle(VideoRenderService $service): void
    {
        $video = $this->videoType === 'module'
            ? ModuleVideo::find($this->videoId)
            : LessonVideo::find($this->videoId);

        if (!$video) {
            return;
        }

        $video->update(['status' => 'generating']);

        $lock = Cache::lock('video-render', 1500);
        try {
            $lock->block(180); // attende il proprio turno: un render alla volta
            $result = $service->render($video);

            $video->update([
                'status' => 'ready',
                'file_path' => $result['file_path'],
                'generation_meta' => $result['meta'],
                // R3 — nuovo mp4 → indice e pubblicazione precedenti sono stale:
                // va re-indicizzato e ripubblicato (no video pubblicato senza indice valido).
                'indexed_at' => null,
                'published_at' => null,
            ]);
        } catch (Throwable $e) {
            Log::warning('[video] render mp4 fallito', [
                'video_id' => $video->id,
                'type' => $this->videoType,
                'error' => $e->getMessage(),
            ]);
            $video->update([
                'status' => 'failed',
                'generation_meta' => array_merge((array) $video->generation_meta, [
                    'failure_reason' => $e->getMessage(),
                ]),
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
