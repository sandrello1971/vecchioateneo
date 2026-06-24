<?php

namespace App\Jobs;

use App\Models\LessonPublication;
use App\Services\Schola\LessonRagIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Ingestion RAG di una pubblicazione di LEZIONE: chunk scope='class' in
// documents_rag (corpo + segments materiali + artefatti). rag_status gestito
// dall'ingestor per il feedback UX/polling.
class IngestLessonRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $publicationId) {}

    public function handle(LessonRagIngestor $ingestor): void
    {
        $publication = LessonPublication::find($this->publicationId);
        if (!$publication) {
            return; // ritirata nel frattempo
        }

        $ingestor->ingestPublication($publication);
    }
}
