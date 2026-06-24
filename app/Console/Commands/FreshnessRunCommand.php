<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * P25.2 — Lancia una esecuzione dell'agente su un corso (fasi 1-2).
 * Aggancio per ID INTERNO (uuid PK), MAI per nome/slug — guard Str::isUuid come P25.1.
 * Esegue solo estrazione + verifica: nessuna proposta, nessuna modifica al sorgente.
 */
class FreshnessRunCommand extends Command
{
    protected $signature = 'course:freshness-run
        {course_id : ID INTERNO del corso (uuid PK) — MAI nome o slug}
        {--source-version= : Versione del sorgente da analizzare (default: la più recente)}';

    protected $description = 'P25.2 — Esegue l\'agente di aggiornamento (estrazione + verifica) su un corso.';

    public function handle(FreshnessAgent $agent): int
    {
        $courseId = (string) $this->argument('course_id');

        if (!Str::isUuid($courseId)) {
            $this->error("course_id non valido: \"{$courseId}\" non è un uuid.");
            $this->line('Passa l\'ID interno del corso (uuid PK), non il nome né lo slug.');
            return self::FAILURE;
        }

        $course = Course::find($courseId);
        if (!$course) {
            $this->error("course_id interno non trovato: {$courseId}");
            return self::FAILURE;
        }

        try {
            $run = $agent->run($course, $this->option('source-version'));
        } catch (\Throwable $e) {
            $this->error('Run fallita: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Run {$run->id} — {$run->status}");
        $this->line("  corso:  \"{$course->name}\" (id {$course->id})");
        $this->line("  claim trovati: {$run->claims_found} · proposte create: {$run->proposals_created} (P25.2 = 0)");

        return self::SUCCESS;
    }
}
