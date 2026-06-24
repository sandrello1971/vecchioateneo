<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P25.3d — Esegue l'agente (Fase 1-2-3: estrazione + verifica + generazione proposte)
 * in modo ASINCRONO sulla queue. Il run fa chiamate API lente (Sonnet + Opus + web_search):
 * non deve bloccare il browser.
 *
 * SOLO generazione → proposte `pending` nella coda. NON applica nulla (l'applicazione
 * resta HITL manuale, P25.3c). Aggiorna `last_run_at` per lo scheduler.
 */
class RunFreshnessAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;       // un run fallito non si ritenta in loop (API costose)
    public int $timeout = 900;   // i run possono durare minuti

    public function __construct(public string $courseId) {}

    public function handle(FreshnessAgent $agent): void
    {
        $course = Course::find($this->courseId);
        if (!$course) {
            Log::warning('[RunFreshnessAgentJob] corso inesistente', ['course_id' => $this->courseId]);
            return;
        }

        try {
            $agent->run($course);
        } catch (\Throwable $e) {
            // Es. nessun course_sources: la run è già registrata 'failed' dall'agente.
            Log::warning('[RunFreshnessAgentJob] run non completata', [
                'course_id' => $course->id, 'error' => $e->getMessage(),
            ]);
        } finally {
            // Marca il tentativo per lo scheduler (anche in caso di fallimento, per non
            // ritentare a ogni tick).
            CourseFreshnessConfig::updateOrCreate(
                ['course_id' => $course->id],
                ['last_run_at' => now()]
            );
        }
    }
}
