<?php

namespace App\Jobs;

use App\Models\TeachingDocument;
use App\Services\Schola\ArtifactRagIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Indicizza il transcript di un materiale CONDIVISO come scope='teacher_shared',
// così i docenti idonei (stessa materia+scuola, oppure tutti) lo pescano via Minerva.
// Rilegge lo stato corrente del documento: robusto a share/unshare ravvicinati.
class IngestMaterialSharedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $documentId) {}

    public function handle(ArtifactRagIngestor $ingestor): void
    {
        $doc = TeachingDocument::find($this->documentId);

        // Non indicizzabile (non pronto, o né condiviso né materiale di scuola): rimuove ed esce.
        if (!$doc || $doc->status !== 'ready' || (!$doc->isShared() && !$doc->is_school_material)) {
            $ingestor->purgeTeacherShared($this->documentId);

            return;
        }

        $transcript = $doc->artifacts()
            ->where('type', 'transcript')
            ->where('status', 'ready')
            ->latest('created_at')
            ->first();

        if (!$transcript) {
            return;
        }

        // Materiale di scuola dell'admin senza scope esplicito → visibile a tutta la scuola.
        $shareScope = $doc->share_scope ?? ($doc->is_school_material ? 'all' : 'all');

        $ingestor->ingestTeacherShared(
            $transcript,
            $doc->id,
            $shareScope,
            $doc->subject_id,
            $doc->school_id,
        );
    }
}
