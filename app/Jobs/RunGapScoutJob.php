<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CoverageGap;
use App\Models\GapScoutRun;
use App\Services\GapScout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P26 Fase A — Esegue lo Scout di copertura in ASINCRONO e salva i candidati in coverage_gaps
 * (status='suggested'). Traccia l'esito in gap_scout_runs per l'osservabilità: su fallimento,
 * `failure_reason` porta il messaggio reale (es. credito via AnthropicError, propagato da GapScout).
 *
 * SOLO rilevamento: nessuna stesura/inserimento. Legge course_sources, scrive coverage_gaps +
 * gap_scout_runs. Mai su corsi/moduli/studenti.
 */
class RunGapScoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;       // un run fallito non si ritenta (API costose)
    public int $timeout = 600;

    public function __construct(public string $courseId) {}

    public function handle(GapScout $scout): void
    {
        $course = Course::find($this->courseId);
        if (!$course) {
            Log::warning('[RunGapScoutJob] corso inesistente', ['course_id' => $this->courseId]);
            return;
        }

        $run = GapScoutRun::create([
            'course_id' => $course->id, 'status' => 'running', 'started_at' => now(),
        ]);

        try {
            $res = $scout->scout($course);

            if (!empty($res['no_sources'])) {
                $run->update([
                    'status' => 'completed', 'finished_at' => now(), 'gaps_found' => 0,
                    'failure_reason' => 'Nessuna fonte approvata per il topic del corso.',
                ]);
                return;
            }

            $created = 0;
            foreach ($res['gaps'] ?? [] as $g) {
                // Dedup leggero: non ri-proporre un gap già presente (qualsiasi stato) per il corso.
                $exists = CoverageGap::where('course_id', $course->id)
                    ->where('title', $g['title'])->exists();
                if ($exists) {
                    continue;
                }

                CoverageGap::create([
                    'course_id' => $course->id,
                    'topic' => $g['source_topic'] ?? ($res['topic'] ?? ''),
                    'title' => $g['title'],
                    'rationale' => $g['rationale'] ?? '',
                    'source_url' => $g['source_url'] ?? null,
                    'source_label' => $this->labelFor($g['source_url'] ?? null),
                    'source_topic' => $g['source_topic'] ?? null,   // P26.2 — provenienza
                    'source_weight' => $g['source_weight'] ?? null,
                    'confidence' => $g['confidence'] ?? null,
                    'status' => 'suggested',
                ]);
                $created++;
            }

            $run->update(['status' => 'completed', 'finished_at' => now(), 'gaps_found' => $created]);
        } catch (\Throwable $e) {
            // Isolamento + osservabilità: il motivo reale resta in gap_scout_runs per la UI.
            $run->update(['status' => 'failed', 'finished_at' => now(), 'failure_reason' => $e->getMessage()]);
            Log::warning('[RunGapScoutJob] scout non completato', [
                'course_id' => $course->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** Etichetta fonte = host dell'URL citato (per mostrare "da quale dominio"). */
    private function labelFor(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? preg_replace('#^www\.#i', '', strtolower($host)) : null;
    }
}
