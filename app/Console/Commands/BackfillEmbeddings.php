<?php

namespace App\Console\Commands;

use App\Services\EmbeddingService;
use App\Support\PgVector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Vettorizza a batch i chunk di documents_rag privi di embedding.
 * Riprendibile: lavora SOLO sulle righe con embedding IS NULL, quindi un
 * secondo run completa ciò che è rimasto (utile se videoai cade a metà).
 */
class BackfillEmbeddings extends Command
{
    protected $signature = 'schola:backfill-embeddings
        {--batch= : Numero di chunk per richiesta (default: services.embeddings.batch)}
        {--limit=0 : Massimo numero di chunk da processare in questo run (0 = tutti)}
        {--dry-run : Mostra quanti chunk verrebbero processati e termina}';

    protected $description = 'Calcola gli embedding mancanti dei chunk di documents_rag (RAG vettoriale Schola).';

    public function handle(EmbeddingService $embeddings): int
    {
        if (!PgVector::available()) {
            $this->error('pgvector non disponibile su questo database: impossibile fare il backfill.');
            $this->line('Esegui prima la sessione pgvector (CREATE EXTENSION vector) e la migrazione.');

            return self::FAILURE;
        }

        $batch = (int) ($this->option('batch') ?: config('services.embeddings.batch', 128));
        $batch = max(1, $batch);
        $limit = (int) $this->option('limit');

        $pending = (int) DB::table('documents_rag')->whereNull('embedding')->count();
        $total = (int) DB::table('documents_rag')->count();
        $already = $total - $pending;

        $this->info("documents_rag: {$total} chunk totali, {$already} già con embedding, {$pending} da processare.");
        $this->line("Modello: " . config('services.embeddings.model') . " · dim " . $embeddings->dimensions() . " · batch {$batch}");

        if ($pending === 0) {
            $this->info('Niente da fare: tutti i chunk hanno già un embedding.');

            return self::SUCCESS;
        }

        $target = $limit > 0 ? min($limit, $pending) : $pending;
        if ($this->option('dry-run')) {
            $this->info("[dry-run] verrebbero processati {$target} chunk.");

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($target);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% · %elapsed:6s%/%estimated:-6s% · %message%");
        $bar->setMessage('avvio…');
        $bar->start();

        $processed = 0;
        $failedBatches = 0;
        $startedAt = microtime(true);

        try {
            while ($processed < $target) {
                $take = min($batch, $target - $processed);

                // Riprendibile: sempre le prossime righe senza embedding.
                $rows = DB::table('documents_rag')
                    ->whereNull('embedding')
                    ->orderBy('id')
                    ->limit($take)
                    ->get(['id', 'content']);

                if ($rows->isEmpty()) {
                    break; // qualcun altro ha completato nel frattempo
                }

                $texts = $rows->map(fn ($r) => (string) $r->content)->all();

                try {
                    $vectors = $embeddings->embed(array_values($texts));
                } catch (Throwable $e) {
                    $failedBatches++;
                    $bar->setMessage('ERRORE: ' . $e->getMessage());
                    $this->newLine(2);
                    $this->error('Backfill interrotto: ' . $e->getMessage());
                    $this->warn("Già completati {$processed} chunk in questo run. Rilancia il comando per riprendere.");

                    return self::FAILURE;
                }

                DB::transaction(function () use ($rows, $vectors) {
                    foreach ($rows as $i => $row) {
                        DB::update(
                            'UPDATE documents_rag SET embedding = ?::vector WHERE id = ?',
                            [PgVector::toLiteral($vectors[$i]), $row->id]
                        );
                    }
                });

                $processed += $rows->count();
                $rate = $processed / max(0.001, microtime(true) - $startedAt);
                $bar->setMessage(sprintf('%.0f chunk/s', $rate));
                $bar->advance($rows->count());
            }
        } finally {
            $bar->finish();
            $this->newLine(2);
        }

        $elapsed = microtime(true) - $startedAt;
        $remaining = (int) DB::table('documents_rag')->whereNull('embedding')->count();

        $this->info(sprintf(
            'Completati %d chunk in %.1fs (%.0f chunk/s). Rimasti senza embedding: %d. Batch falliti: %d.',
            $processed, $elapsed, $processed / max(0.001, $elapsed), $remaining, $failedBatches
        ));

        return $remaining === 0 ? self::SUCCESS : self::FAILURE;
    }
}
