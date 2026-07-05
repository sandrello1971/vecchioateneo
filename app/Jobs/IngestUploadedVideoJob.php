<?php

namespace App\Jobs;

use App\Models\TeachingArtifact;
use App\Models\UploadedVideo;
use App\Services\VideoAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Ingest di un video CARICATO su noscite-videoai: upload → analisi completa
 * (trascrizione + estrazione frame + Vision) → polling stato. A 'ready' il video è
 * riproducibile e ricercabile al suo interno; il suo testo (parlato + descrizioni
 * visive) viene indicizzato nella Minerva del docente (TeachingArtifact transcript).
 * Best-effort: un fallimento imposta status=failed con motivo, senza stato falso.
 */
class IngestUploadedVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public string $uploadedVideoId) {}

    public function handle(VideoAIService $videoAI): void
    {
        $video = UploadedVideo::find($this->uploadedVideoId);
        if (!$video || !$video->file_path) {
            return;
        }

        try {
            $localPath = Storage::disk('local')->path($video->file_path);
            $result = $videoAI->ingestVideo($localPath, $video->source_filename ?: basename($localPath));
            $videoAiId = $result['video_id'] ?? null;
            if (!$videoAiId) {
                throw new \RuntimeException('videoai non ha restituito un video_id.');
            }

            $video->update(['video_ai_id' => $videoAiId, 'status' => 'processing']);

            $data = $this->pollUntilReady($videoAI, $videoAiId, $video);

            $video->update([
                'status' => 'ready',
                'indexed_at' => now(),
                'duration_seconds' => (int) ($data['duration'] ?? $video->duration_seconds ?? 0) ?: null,
                'meta' => array_merge($video->meta ?? [], ['progress' => 100]),
            ]);

            // RAG Minerva: testo del video (parlato + Vision) → transcript teacher_private.
            $this->ingestForMinerva($videoAI, $video);
        } catch (\Throwable $e) {
            Log::warning('[schola] ingest video caricato fallito', [
                'uploaded_video' => $video->id,
                'error' => $e->getMessage(),
            ]);
            $video->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
        }
    }

    /** Polling stato videoai fino a ready/error, aggiornando meta.progress per la UI. */
    private function pollUntilReady(VideoAIService $videoAI, string $videoAiId, UploadedVideo $video): array
    {
        $interval = (int) config('services.videoai.poll_interval', 3);
        $maxAttempts = (int) config('services.videoai.poll_max_attempts', 200);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $data = $videoAI->getStatus($videoAiId);
            $status = $data['status'] ?? 'processing';
            $progress = (int) ($data['progress'] ?? 0);

            $video->update(['meta' => array_merge($video->meta ?? [], ['progress' => max(1, $progress)])]);

            if (in_array($status, ['ready', 'completed', 'done'], true)) {
                return $data;
            }
            if (in_array($status, ['failed', 'error'], true)) {
                throw new \RuntimeException('Analisi videoai fallita: ' . ($data['reason'] ?? 'motivo non specificato'));
            }

            sleep($interval);
        }

        throw new \RuntimeException('Analisi videoai: timeout di polling superato.');
    }

    /**
     * Recupera i chunk testuali (parlato + descrizioni Vision) e li indicizza come
     * TeachingArtifact type=transcript → Minerva del docente (teacher_private).
     * Best-effort: senza testo non si crea nulla, il video resta comunque fruibile.
     */
    private function ingestForMinerva(VideoAIService $videoAI, UploadedVideo $video): void
    {
        $chunks = $videoAI->getChunksText($video->video_ai_id);
        $text = trim(implode("\n", array_filter(array_map(
            fn ($c) => trim((string) ($c['text'] ?? '')),
            is_array($chunks) ? $chunks : []
        ))));

        // Fallback: se l'endpoint chunks_text non è disponibile, usa il solo parlato.
        if ($text === '') {
            $segments = $videoAI->getTranscript($video->video_ai_id)['segments'] ?? [];
            $text = trim(implode(' ', array_map(fn ($s) => (string) ($s['text'] ?? ''), is_array($segments) ? $segments : [])));
        }

        if ($text === '') {
            return;
        }

        $attrs = [
            'teacher_id' => $video->teacher_id,
            'lesson_id' => $video->lesson_id,
            'subject_id' => $video->subject_id,
            'type' => 'transcript',
            'title' => 'Video — ' . $video->title,
            'content' => $text,
            'status' => 'ready',
            'generation_meta' => ['source' => 'uploaded_video', 'uploaded_video_id' => $video->id],
        ];

        // Re-ingest idempotente: aggiorna l'artefatto esistente, altrimenti crealo.
        $artifact = $video->artifact_id ? TeachingArtifact::find($video->artifact_id) : null;
        if ($artifact) {
            $artifact->update($attrs);
        } else {
            $artifact = TeachingArtifact::create($attrs);
            $video->update(['artifact_id' => $artifact->id]);
        }

        IngestArtifactTeacherPrivateJob::dispatch($artifact->id);
    }
}
