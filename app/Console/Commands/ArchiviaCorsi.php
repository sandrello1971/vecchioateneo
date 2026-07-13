<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Archivia (is_active=false) alcuni corsi: STRUCTURA e i duplicati che
 * convergono nei canonici INITIUM e CONSILIUM.
 *
 * NON cancella: i dati restano, e' reversibile rimettendo is_active=true.
 * Transazione, --dry-run, logging [archivia-corsi].
 */
class ArchiviaCorsi extends Command
{
    protected $signature = 'corsi:archivia {--dry-run : Mostra senza scrivere}';
    protected $description = 'Archivia STRUCTURA e i corsi duplicati (is_active=false).';

    /** slug => motivo (per il log e l'output) */
    private const DA_ARCHIVIARE = [
        'structura'                 => 'rimosso dal portfolio',
        'fondamenta-ai-operativa'   => 'duplicato grezzo di INITIUM (tiene initium-fondamenta-ai-operativa)',
    ];

    /** i canonici da NON toccare mai, come salvaguardia */
    private const CANONICI = [
        'initium-fondamenta-ai-operativa',
        'consilium',
        'primus',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info('=== Archivia corsi ===');
        $this->line('Mode: ' . ($dry ? 'DRY RUN (nessuna scrittura)' : 'LIVE'));
        $this->newLine();

        DB::beginTransaction();
        try {
            $done = 0;
            foreach (self::DA_ARCHIVIARE as $slug => $motivo) {
                if (in_array($slug, self::CANONICI, true)) {
                    $this->error("  SALVAGUARDIA: $slug e' un canonico, salto");
                    continue;
                }
                $course = Course::where('slug', $slug)->first();
                if (!$course) { $this->warn("  $slug non trovato, salto"); continue; }
                if (!$course->is_active) {
                    $this->line("  gia' archiviato: $slug");
                    continue;
                }
                if ($dry) {
                    $this->line("  WOULD ARCHIVE: $slug  ($motivo)");
                } else {
                    $course->is_active = false;
                    $course->save();
                    Log::info("[archivia-corsi] archived $slug ($motivo)");
                    $this->line("  ARCHIVED: $slug  ($motivo)");
                }
                $done++;
            }

            if ($dry) {
                DB::rollBack();
                $this->newLine();
                $this->warn("DRY RUN. Da archiviare: $done.");
                return 0;
            }
            DB::commit();
            $this->newLine();
            $this->info("Completato. Archiviati: $done.");
            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Errore: ' . $e->getMessage());
            Log::error('[archivia-corsi] failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
