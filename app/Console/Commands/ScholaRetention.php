<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Schola\RetentionService;
use Illuminate\Console\Command;

// Retention di fine anno (P16). Guard espliciti: --school e --school-year
// OBBLIGATORI; dry-run di DEFAULT (elenca senza scrivere); --force per eseguire.
// NIENTE azioni distruttive senza dry-run + force.
class ScholaRetention extends Command
{
    protected $signature = 'schola:retention {--school= : id o slug della scuola} {--school-year= : anno (es. 2025/2026)} {--force : esegue davvero (default dry-run)}';
    protected $description = 'Anonimizza la PII degli studenti usciti di un anno scolastico chiuso (dry-run di default).';

    public function handle(RetentionService $service): int
    {
        $schoolOpt = $this->option('school');
        $year = $this->option('school-year');

        if (!$schoolOpt || !$year) {
            $this->error('Opzioni obbligatorie: --school=<id|slug> --school-year=<AAAA/AAAA>.');
            return self::FAILURE;
        }

        // Match per slug, oppure per id solo se è un UUID valido (evita il cast
        // error di Postgres su id quando si passa uno slug).
        $school = School::where('slug', $schoolOpt)
            ->when(\Illuminate\Support\Str::isUuid($schoolOpt), fn ($q) => $q->orWhere('id', $schoolOpt))
            ->first();
        if (!$school) {
            $this->error("Scuola non trovata: {$schoolOpt}.");
            return self::FAILURE;
        }

        $candidates = $service->candidates($school, $year);

        $this->info("Scuola: {$school->name} · anno: {$year}");
        $this->line("Studenti usciti candidati all'anonimizzazione: {$candidates->count()}");
        foreach ($candidates as $s) {
            $this->line("  - {$s->name} <{$s->email}" . ($s->username ? "|{$s->username}" : '') . ">");
        }

        if (!$this->option('force')) {
            $this->warn('DRY-RUN: nessuna scrittura. Ripeti con --force per anonimizzare definitivamente.');
            return self::SUCCESS;
        }

        if ($candidates->isEmpty()) {
            $this->info('Nessuno studente da anonimizzare.');
            return self::SUCCESS;
        }

        foreach ($candidates as $s) {
            $service->anonymize($s);
        }

        $this->info("Anonimizzati {$candidates->count()} studenti (PII rimossa, account disattivato). Materiali docente conservati.");

        return self::SUCCESS;
    }
}
