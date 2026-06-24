<?php

namespace App\Jobs;

use App\Models\Lesson;
use App\Services\LessonGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Composizione asincrona del CORPO di una lezione a partire dai materiali ad essa
// associati (teaching_documents con lesson_id, status ready). Usa LessonGenerationService
// per fondere N fonti eterogenee in un testo unico. Imposta generation_status
// generating→ready/failed e valorizza SEMPRE generation_meta (modello, token, fonti).
// I riferimenti temporali dei materiali audio/video (segments) sono conservati.
class GenerateLessonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1; // il retry è esplicito (rigenerazione dall'UI)

    public function __construct(public string $lessonId) {}

    public function handle(LessonGenerationService $composer): void
    {
        $lesson = Lesson::with('topic.subject')->find($this->lessonId);
        if (!$lesson) {
            return; // lezione eliminata nel frattempo
        }

        $log = ['lesson_id' => $lesson->id, 'topic_id' => $lesson->topic_id];

        // Materiali pronti della lezione, con testo estratto, in ordine di creazione.
        $docs = $lesson->teachingDocuments()
            ->where('status', 'ready')
            ->orderBy('created_at')
            ->get()
            ->filter(fn ($d) => trim((string) $d->extracted_text) !== '');

        if ($docs->isEmpty()) {
            $this->markFailed($lesson, 'Nessun materiale pronto con testo da comporre. Carica e assegna materiali alla lezione.');
            return;
        }

        // Costruisce le fonti: testo + segments (se presenti) + artefatti già generati.
        $sources = $docs->map(function ($doc) {
            $artifacts = $doc->artifacts()
                ->where('status', 'ready')
                ->whereIn('type', ['summary', 'outline'])
                ->whereNotNull('content')
                ->get(['type', 'title', 'content'])
                ->map(fn ($a) => ['type' => $a->type, 'title' => $a->title, 'content' => $a->content])
                ->all();

            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'source_type' => $doc->source_type,
                'text' => (string) $doc->extracted_text,
                'segments' => $doc->extraction_meta['segments'] ?? null,
                'artifacts' => $artifacts,
            ];
        })->all();

        try {
            $result = $composer->generateFromSources($sources, $lesson->title, [
                'topic' => $lesson->topic?->name,
                'subject' => $lesson->topic?->subject?->name,
                'log_context' => $log,
            ]);

            $lesson->update([
                'content' => $result['content'],
                'generation_status' => 'ready',
                'generation_meta' => $result['meta'],
            ]);
        } catch (Throwable $e) {
            Log::warning('[schola] composizione lezione fallita', array_merge($log, [
                'error' => $e->getMessage(),
            ]));
            $this->markFailed($lesson, $e->getMessage());
        }
    }

    private function markFailed(Lesson $lesson, string $reason): void
    {
        $lesson->update([
            'generation_status' => 'failed',
            'generation_meta' => array_merge((array) $lesson->generation_meta, [
                'failure_reason' => $reason,
            ]),
        ]);
    }
}
