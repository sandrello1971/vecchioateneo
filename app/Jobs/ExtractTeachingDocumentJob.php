<?php

namespace App\Jobs;

use App\Jobs\IngestArtifactTeacherPrivateJob;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Services\Schola\TeachingDocumentExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Estrazione asincrona del testo da un teaching_document. Dispatch per source_type
// delegato a TeachingDocumentExtractor. Scrive extracted_text + extraction_meta e
// imposta status ready/failed (con failure_reason comprensibile). Ritentabile da UI.
class ExtractTeachingDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 min: trascrizioni audio/video lunghe
    public int $tries = 1;      // il retry è esplicito dall'UI (azione docente)

    public function __construct(public string $documentId) {}

    public function handle(TeachingDocumentExtractor $extractor): void
    {
        $doc = TeachingDocument::find($this->documentId);
        if (!$doc) {
            return; // documento eliminato nel frattempo
        }

        $doc->update(['status' => 'processing', 'failure_reason' => null]);

        try {
            $result = $extractor->extract($doc);

            $doc->update([
                'extracted_text' => $result['text'],
                'extraction_meta' => $result['meta'],
                'status' => 'ready',
                'failure_reason' => null,
            ]);

            $this->ensureTranscriptArtifact($doc, $result);
        } catch (Throwable $e) {
            Log::warning('[schola] estrazione teaching_document fallita', [
                'document_id' => $doc->id,
                'source_type' => $doc->source_type,
                'error' => $e->getMessage(),
            ]);

            $doc->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Crea (o aggiorna, in caso di ri-estrazione) la trascrizione come PRIMO
     * artefatto del materiale: type=transcript, content = testo estratto, ready.
     * Idempotente per (documento, transcript): un retry non duplica l'artefatto.
     */
    private function ensureTranscriptArtifact(TeachingDocument $doc, array $result): void
    {
        $transcript = TeachingArtifact::updateOrCreate(
            ['teaching_document_id' => $doc->id, 'type' => 'transcript'],
            [
                'teacher_id' => $doc->teacher_id,
                'title' => 'Trascrizione — ' . $doc->title,
                'content' => $result['text'],
                'subject_id' => $doc->subject_id,
                'status' => 'ready',
                'generation_meta' => [
                    'source' => 'extraction',
                    'method' => $result['meta']['method'] ?? null,
                ],
            ]
        );

        // Minerva del docente: indicizza la trascrizione come teacher_private.
        IngestArtifactTeacherPrivateJob::dispatch($transcript->id);
    }
}
