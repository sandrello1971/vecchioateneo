<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use Illuminate\Console\Command;

/**
 * P25.3e — Backfill una-tantum dell'audience via euristica sul nome del corso
 * (lice|istitut|scuol → minor). IDEMPOTENTE: rieseguibile senza effetti collaterali.
 * NON sovrascrive gli override manuali (audience_overridden=true).
 *
 * Verificato: cattura "LICEI…" e "Istituti tecnici…"; NON falsa "SCHOLA — dirigenti
 * scolastici…" (il pattern 'scuol' non matcha 'scolastici').
 */
class BackfillCourseAudience extends Command
{
    protected $signature = 'freshness:backfill-audience {--dry-run : Mostra cosa farebbe senza scrivere}';

    protected $description = 'P25.3e — Marca minor i corsi per minori via euristica nome (idempotente, rispetta gli override).';

    private const MINOR_NAME_PATTERN = '/(lice|istitut|scuol)/iu';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $marked = 0;
        $alreadyMinor = 0;
        $skippedOverride = 0;

        foreach (Course::all() as $course) {
            if (!preg_match(self::MINOR_NAME_PATTERN, $course->name)) {
                continue; // non è un corso "per minori" secondo l'euristica
            }

            $cfg = $course->freshnessConfig;

            if ($cfg && $cfg->audience_overridden) {
                $this->line("  skip (override manuale): {$course->name} → {$cfg->audience}");
                $skippedOverride++;
                continue;
            }
            if ($cfg && $cfg->audience === 'minor') {
                $alreadyMinor++;
                continue; // idempotente
            }

            $this->line("  minor ← {$course->name}");
            if (!$dryRun) {
                CourseFreshnessConfig::updateOrCreate(
                    ['course_id' => $course->id],
                    ['audience' => 'minor'] // NB: non imposta audience_overridden (è euristica)
                );
            }
            $marked++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Backfill audience — marcati minor: {$marked}, già minor: {$alreadyMinor}, saltati (override): {$skippedOverride}.");

        return self::SUCCESS;
    }
}
