<?php

namespace App\Services\Schola;

use App\Models\LessonVideo;
use App\Models\ModuleVideo;
use App\Services\Tts\TtsProvider;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * V3 — compone il video narrato (MP4) di una presentazione: per ogni slide usa il
 * PNG già reso da S1 + l'MP3 TTS della riga di copione, poi ffmpeg (via build_video.py,
 * sotto nice/ionice) genera l'mp4. GATE: solo da copione 'confirmed'.
 * Cache MP3 per testo+voce: righe invariate non vengono ri-sintetizzate (no doppio costo).
 */
class VideoRenderService
{
    private const DISK = 'local';

    public function __construct(
        private SlidePreviewService $preview,
        private TtsProvider $tts,
    ) {}

    /**
     * @return array{file_path: string, meta: array}
     */
    public function render(LessonVideo|ModuleVideo $video): array
    {
        if ($video->script_status !== 'confirmed' || empty($video->script)) {
            throw new RuntimeException('Conferma il copione prima di generare il video.');
        }

        $presentation = $video->presentation;
        if (!$presentation || $presentation->status !== 'ready' || empty($presentation->file_path)) {
            throw new RuntimeException('Presentazione sorgente non disponibile.');
        }

        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($presentation->file_path)) {
            throw new RuntimeException('File della presentazione sorgente mancante.');
        }

        $images = $this->preview->imagesFor($presentation->file_path); // PNG ordinati slide_1..N
        $provider = (string) config('services.tts.provider', 'elevenlabs');
        $voiceId = (string) config('services.tts.voice_id', config('services.elevenlabs.voice_id', 'HuK8QKF35exsCh2e7fLT'));

        $manifest = [];
        $slideNumbers = [];
        foreach ($video->script as $line) {
            $n = (int) ($line['slide_number'] ?? 0);
            $text = trim((string) ($line['text'] ?? ''));
            $pngRel = $images[$n - 1] ?? null;
            if ($n < 1 || $pngRel === null || $text === '') {
                continue;
            }

            // CACHE MP3 per (provider+voce+testo): cambiando provider o voce, l'audio si rigenera.
            $hash = md5($provider . '|' . $voiceId . '|' . $text);
            $audioRel = $this->audioDir($video) . "/slide_{$n}_{$hash}.mp3";
            if (!$disk->exists($audioRel)) {
                $disk->put($audioRel, $this->tts->synthesize($text, ['voice_id' => $voiceId]));
            }

            $manifest[] = ['image' => $disk->path($pngRel), 'audio' => $disk->path($audioRel)];
            $slideNumbers[] = $n;
        }

        if ($manifest === []) {
            throw new RuntimeException('Nessuna slide componibile (PNG o copione mancanti).');
        }

        $outRel = $this->videoPath($video);
        $disk->makeDirectory(dirname($outRel));
        $render = $this->runFfmpeg($manifest, $disk->path($outRel));

        if (!$disk->exists($outRel)) {
            throw new RuntimeException('Il file video non è stato creato.');
        }

        // slide_timings: slide_number → [start_sec, end_sec] dalle durate dei segmenti
        // (in ordine). Timestamp per-slide a costo zero, per la ricerca (R2/R4).
        $timings = [];
        $cursor = 0.0;
        foreach (($render['durations'] ?? []) as $i => $d) {
            $start = round($cursor, 2);
            $cursor += (float) $d;
            $timings[] = ['slide_number' => $slideNumbers[$i] ?? ($i + 1), 'start_sec' => $start, 'end_sec' => round($cursor, 2)];
        }

        return [
            'file_path' => $outRel,
            'meta' => array_merge((array) $video->generation_meta, [
                'seconds' => $render['total'] ?? 0,
                'rendered_slides' => count($manifest),
                'slide_timings' => $timings,
                'tts_provider' => $provider,
                'voice_id' => $voiceId,
            ]),
        ];
    }

    private function audioDir(LessonVideo|ModuleVideo $video): string
    {
        return "video-audio/{$video->id}";
    }

    private function videoPath(LessonVideo|ModuleVideo $video): string
    {
        return $video instanceof LessonVideo
            ? "lesson-videos/{$video->lesson_id}/{$video->id}.mp4"
            : "module-videos/{$video->module_id}/{$video->id}.mp4";
    }

    /**
     * Lancia build_video.py (ffmpeg) sotto nice/ionice.
     * @return array{total: float, durations: array<int, float>}
     */
    private function runFfmpeg(array $manifestSlides, string $outAbs): array
    {
        $python = config('services.ffmpeg.python', '/home/noscite/venv/bin/python');
        $script = base_path('resources/python/build_video.py');

        // nice -n 19 + ionice -c3 (idle): non disturba gli altri siti del server condiviso.
        $process = new Process(['nice', '-n', '19', 'ionice', '-c3', $python, $script]);
        $process->setInput(json_encode([
            'out' => $outAbs,
            'slides' => $manifestSlides,
            'config' => config('services.video'),
        ], JSON_UNESCAPED_UNICODE));
        $process->setTimeout(1200);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Render video fallito: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $out = trim($process->getOutput());
        $data = json_decode($out, true);
        if (is_array($data) && isset($data['total'])) {
            return ['total' => (float) $data['total'], 'durations' => array_map('floatval', $data['durations'] ?? [])];
        }

        return ['total' => (float) $out, 'durations' => []]; // fallback difensivo
    }
}
