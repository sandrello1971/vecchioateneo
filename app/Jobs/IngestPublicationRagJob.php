<?php

namespace App\Jobs;

use App\Models\ArtifactPublication;
use App\Services\Schola\ArtifactRagIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Ingestion RAG di una pubblicazione: chunk scope='class' in documents_rag.
// Lo stato (rag_status) è gestito dall'ingestor per il feedback UX/polling.
class IngestPublicationRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $publicationId) {}

    public function handle(ArtifactRagIngestor $ingestor): void
    {
        $publication = ArtifactPublication::with('artifact')->find($this->publicationId);
        if (!$publication) {
            return; // ritirata nel frattempo
        }

        $ingestor->ingestPublication($publication);
    }
}
