<?php

namespace App\Console\Commands;

use App\Models\QuizAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FailStaleExamAttempts extends Command
{
    protected $signature = 'exams:fail-stale';
    protected $description = 'Chiude come abbandonati i tentativi d\'esame scaduti (oltre time_limit_minutes o oltre il cap di default).';

    /**
     * Cap di default per i quiz d'esame senza time_limit_minutes.
     * 180 minuti = 3 ore: tetto generoso ma non infinito.
     */
    public const DEFAULT_MAX_EXAM_MINUTES = 180;

    public function handle(): int
    {
        $now = now();

        $stale = QuizAttempt::query()
            ->whereNull('completed_at')
            ->whereHas('quiz', fn ($q) =>
                $q->whereNotNull('course_id')->whereNull('module_id'))
            ->with('quiz')
            ->get();

        $closed = 0;

        foreach ($stale as $att) {
            $limit = (int) ($att->quiz?->time_limit_minutes
                ?: self::DEFAULT_MAX_EXAM_MINUTES);

            if (!$att->started_at) {
                continue;
            }

            $deadline = $att->started_at->copy()->addMinutes($limit);
            if ($deadline->isFuture()) {
                continue;
            }

            $att->update([
                'completed_at' => $now,
                'score'        => 0,
                'passed'       => false,
                'abandoned'    => true,
                'time_spent_seconds' => (int) max(0,
                    $att->started_at->diffInSeconds($now)),
            ]);
            $closed++;
            Log::info('Quiz attempt auto-failed (reaper, stale)', [
                'attempt_id' => $att->id,
                'student_id' => $att->student_id,
                'quiz_id'    => $att->quiz_id,
                'limit_minutes' => $limit,
                'started_at' => $att->started_at->toIso8601String(),
            ]);
        }

        $this->info("Closed {$closed} stale exam attempt(s).");
        return self::SUCCESS;
    }
}
