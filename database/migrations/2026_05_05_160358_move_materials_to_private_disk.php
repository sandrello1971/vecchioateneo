<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Sposta i file dei materiali corso da storage/app/public/{file_path}
     * a storage/app/private/{file_path}. Filtra is_instructor_only = false:
     * gli instructor manuals sono già sul disk local, non vanno toccati.
     * I record DB non cambiano: cambia solo il disk di residenza, file_path
     * resta identico (es. "materials/initium/foo.pdf").
     */
    public function up(): void
    {
        $this->moveFiles(
            storage_path('app/public'),
            storage_path('app/private'),
            'public → private'
        );
    }

    /**
     * Rollback funzionante: rimette i file su public. Stesso filtro
     * is_instructor_only=false. Mirror esatto di up() con disk invertito.
     */
    public function down(): void
    {
        $this->moveFiles(
            storage_path('app/private'),
            storage_path('app/public'),
            'private → public'
        );
    }

    private function moveFiles(string $srcRoot, string $dstRoot, string $direction): void
    {
        $moved = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;

        DB::table('materials')
            ->whereNotNull('file_path')
            ->where('is_instructor_only', false)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($srcRoot, $dstRoot, &$moved, &$skipped, &$missing, &$failed) {
                foreach ($rows as $row) {
                    $rel = $row->file_path;
                    $src = $srcRoot . '/' . $rel;
                    $dst = $dstRoot . '/' . $rel;

                    if (!file_exists($src)) {
                        if (file_exists($dst)) {
                            $skipped++;   // già spostato in un run precedente
                        } else {
                            $missing++;
                            Log::warning('Backfill materiali: file mancante', [
                                'file_path' => $rel,
                                'direction' => 'check src=' . $src,
                            ]);
                        }
                        continue;
                    }

                    if (file_exists($dst)) {
                        // Sicurezza: src e dst entrambi presenti significa duplicato; non sovrascrivere.
                        $skipped++;
                        Log::info('Backfill materiali: dst già presente, src lasciato in place', [
                            'file_path' => $rel,
                        ]);
                        continue;
                    }

                    if (!is_dir(dirname($dst))) {
                        @mkdir(dirname($dst), 0755, true);
                    }

                    if (@rename($src, $dst)) {
                        $moved++;
                    } else {
                        $failed++;
                        Log::error('Backfill materiali: rename fallito', [
                            'from' => $src,
                            'to' => $dst,
                        ]);
                    }
                }
            });

        Log::info("Backfill materiali $direction completato", [
            'moved' => $moved,
            'skipped' => $skipped,
            'missing' => $missing,
            'failed' => $failed,
        ]);
    }
};
