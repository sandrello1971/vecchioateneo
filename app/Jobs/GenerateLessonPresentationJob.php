<?php

namespace App\Jobs;

use App\Models\LessonPresentation;
use App\Services\Schola\LessonPresentationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Generazione asincrona del .pptx di una lezione (P21). status generating→
// ready/failed + generation_meta (modello, token, slide, failure_reason). Il file
// vive in storage PRIVATO. Su rigenerazione il vecchio file viene rimosso.
class GenerateLessonPresentationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1; // retry esplicito (rigenerazione dall'UI)

    public function __construct(public string $presentationId) {}

    public function handle(LessonPresentationService $service): void
    {
        $presentation = LessonPresentation::find($this->presentationId);
        if (!$presentation) {
            return; // eliminata nel frattempo
        }

        $oldPath = $presentation->file_path;
        $presentation->update(['status' => 'generating']);

        try {
            $result = $service->build($presentation);

            // Rimuove il file precedente se sostituito (rigenerazione).
            if ($oldPath && $oldPath !== $result['file_path'] && Storage::disk('local')->exists($oldPath)) {
                Storage::disk('local')->delete($oldPath);
            }

            $presentation->update([
                'file_path' => $result['file_path'],
                'status' => 'ready',
                'generation_meta' => $result['meta'],
                'spec' => $result['spec'] ?? null, // S0: spec persistita per la correzione via prompt
            ]);
        } catch (Throwable $e) {
            Log::warning('[schola] generazione presentazione fallita', [
                'presentation_id' => $presentation->id,
                'error' => $e->getMessage(),
            ]);
            $presentation->update([
                'status' => 'failed',
                'generation_meta' => array_merge((array) $presentation->generation_meta, [
                    'failure_reason' => $e->getMessage(),
                ]),
            ]);
        }
    }
}
