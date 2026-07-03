<?php

namespace App\Jobs;

use App\Models\LessonVideo;
use App\Models\ModuleVideo;
use App\Services\Schola\VideoScriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// V1 — generazione asincrona del copione narrato (Claude). Durante: video.status
// 'generating'. A fine: script + script_status='draft' e status torna 'pending'
// (l'mp4 non è ancora reso — quello è V3). Errore → status 'failed' + reason.
// Unico job polimorfico: $videoType = 'lesson' | 'module'.
class GenerateVideoScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public string $videoId, public string $videoType) {}

    public function handle(VideoScriptService $service): void
    {
        $video = $this->videoType === 'module'
            ? ModuleVideo::find($this->videoId)
            : LessonVideo::find($this->videoId);

        if (!$video) {
            return; // eliminato nel frattempo
        }

        $video->update(['status' => 'generating']);

        try {
            $result = $service->generateScript($video);

            $video->update([
                'status' => 'pending', // copione pronto; l'mp4 si renderà in V3
                'script' => $result['script'],
                'script_status' => 'draft',
                'generation_meta' => $result['meta'],
            ]);
        } catch (Throwable $e) {
            Log::warning('[video] generazione copione fallita', [
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
        }
    }
}
