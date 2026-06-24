<?php

namespace App\Jobs;

use App\Services\Schola\ArtifactRagIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Ritiro pubblicazione: rimuove i chunk scope='class' dal RAG. Idempotente
// (lavora per publication_id nel metadata). Riceve l'id perché la riga
// ArtifactPublication potrebbe essere già stata eliminata.
class PurgeWithdrawnPublicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $publicationId) {}

    public function handle(ArtifactRagIngestor $ingestor): void
    {
        $ingestor->purgePublication($this->publicationId);
    }
}
