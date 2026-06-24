<?php

namespace App\Jobs;

use App\Models\TeachingArtifact;
use App\Services\Schola\ArtifactRagIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Indicizza un artefatto come scope='teacher_private' alla sua creazione: la
// Minerva del docente vede anche i materiali non pubblicati. Idempotente.
class IngestArtifactTeacherPrivateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $artifactId) {}

    public function handle(ArtifactRagIngestor $ingestor): void
    {
        $artifact = TeachingArtifact::find($this->artifactId);
        if (!$artifact || $artifact->status !== 'ready') {
            return;
        }

        $ingestor->ingestTeacherPrivate($artifact);
    }
}
