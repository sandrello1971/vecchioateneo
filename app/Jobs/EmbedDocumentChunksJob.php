<?php

namespace App\Jobs;

use App\Services\EmbeddingService;
use App\Support\PgVector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coda di recupero degli embedding: completa i chunk di documents_rag rimasti
 * senza embedding (es. videoai giù al momento della creazione). NON blocca mai
 * l'ingestion — è il percorso asincrono di recupero. La rete di sicurezza
 * durevole resta il comando schola:backfill-embeddings (schedulabile).
 *
 * Idempotente: lavora solo sulle righe ancora con embedding IS NULL tra gli id
 * passati. Cattura gli errori (niente blocco a cascata); se nel run sync il
 * servizio è ancora giù, i chunk restano NULL e li recupera il backfill.
 */
class EmbedDocumentChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /** @param list<string> $chunkIds */
    public function __construct(public array $chunkIds) {}

    public function handle(EmbeddingService $embeddings): void
    {
        if (empty($this->chunkIds) || !PgVector::available()) {
            return;
        }

        $rows = DB::table('documents_rag')
            ->whereIn('id', $this->chunkIds)
            ->whereNull('embedding')
            ->get(['id', 'content']);

        if ($rows->isEmpty()) {
            return; // già completati altrove
        }

        try {
            $vectors = $embeddings->embed($rows->map(fn ($r) => (string) $r->content)->all());

            DB::transaction(function () use ($rows, $vectors) {
                foreach ($rows->values() as $i => $row) {
                    DB::update(
                        'UPDATE documents_rag SET embedding = ?::vector WHERE id = ?',
                        [PgVector::toLiteral($vectors[$i]), $row->id]
                    );
                }
            });
        } catch (Throwable $e) {
            // Niente rethrow nel percorso sync (non deve travolgere l'ingestion).
            // Con un worker asincrono il job verrà comunque ritentato (tries/backoff).
            Log::warning('[schola] recupero embedding fallito', [
                'count' => $rows->count(),
                'error' => $e->getMessage(),
            ]);

            if (!app()->runningUnitTests() && $this->attempts() < $this->tries) {
                $this->release($this->backoff);
            }
        }
    }
}
