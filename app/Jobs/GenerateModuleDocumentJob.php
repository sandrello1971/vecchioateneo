<?php

namespace App\Jobs;

use App\Models\ModuleDocument;
use App\Services\ModuleDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Generazione asincrona del PDF di un MODULO di corso Officina (P29). Guscio
// gemello di GenerateModulePresentationJob: il service fa il lavoro e persiste
// ready; il job intercetta gli errori e marca failed con failure_reason. Additivo.
class GenerateModuleDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1; // retry esplicito (rigenerazione dall'UI)

    public function __construct(public string $documentId) {}

    public function handle(ModuleDocumentService $service): void
    {
        $document = ModuleDocument::find($this->documentId);
        if (!$document) {
            return; // eliminato nel frattempo
        }

        try {
            $service->buildDocumentForModule($document);
        } catch (Throwable $e) {
            Log::warning('[officina] generazione documento modulo fallita', [
                'module_document_id' => $document->id,
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
