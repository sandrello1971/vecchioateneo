<?php

namespace App\Jobs;

use App\Models\CourseDocument;
use App\Services\CourseDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Generazione asincrona del PDF dell'INTERO corso Officina (P29 Fase 2). Guscio
// gemello di GenerateModuleDocumentJob, a livello corso: il service concatena i
// moduli e persiste ready; il job marca failed su errore. Additivo.
class GenerateCourseDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1; // retry esplicito (rigenerazione dall'UI)

    public function __construct(public string $documentId) {}

    public function handle(CourseDocumentService $service): void
    {
        $document = CourseDocument::find($this->documentId);
        if (!$document) {
            return; // eliminato nel frattempo
        }

        try {
            $service->buildDocumentForCourse($document);
        } catch (Throwable $e) {
            Log::warning('[officina] generazione documento corso fallita', [
                'course_document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            $document->update([
                'status' => 'failed',
                'generation_meta' => array_merge((array) $document->generation_meta, [
                    'failure_reason' => $e->getMessage(),
                ]),
            ]);
        }
    }
}
