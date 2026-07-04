<?php

namespace App\Console\Commands;

use App\Models\LessonVideo;
use App\Models\ModuleVideo;
use App\Services\Schola\VideoIndexService;
use Illuminate\Console\Command;

// R2 — trigger interno per indicizzare un video generato (poi agganciato al publish in R3).
class IndexGeneratedVideo extends Command
{
    protected $signature = 'schola:video-index {type : lesson|module} {videoId}';

    protected $description = 'Indicizza in videoai un video generato (copione + slide noti).';

    public function handle(VideoIndexService $service): int
    {
        $video = $this->argument('type') === 'module'
            ? ModuleVideo::find($this->argument('videoId'))
            : LessonVideo::find($this->argument('videoId'));

        if (!$video) {
            $this->error('Video non trovato.');

            return self::FAILURE;
        }

        try {
            $result = $service->indexGenerated($video);
            $this->info("Indicizzati {$result['chunks']} chunk → collection {$result['video_ai_id']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
