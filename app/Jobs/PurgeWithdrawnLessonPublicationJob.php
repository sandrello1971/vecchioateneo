<?php

namespace App\Jobs;

use App\Services\Schola\LessonRagIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Ritiro pubblicazione di lezione: rimuove i chunk scope='class' dal RAG.
// Idempotente (lavora per lesson_publication_id nel metadata). Riceve l'id
// perché la riga LessonPublication potrebbe essere già stata eliminata.
class PurgeWithdrawnLessonPublicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $publicationId) {}

    public function handle(LessonRagIngestor $ingestor): void
    {
        $ingestor->purgePublication($this->publicationId);
    }
}
