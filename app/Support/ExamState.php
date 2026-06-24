<?php

namespace App\Support;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

class ExamState
{
    /** L'esame è il quiz a livello corso (non di modulo). */
    public function isExamQuiz(Quiz $quiz): bool
    {
        return !empty($quiz->course_id) && empty($quiz->module_id);
    }

    /**
     * Tentativo d'esame attualmente in corso per lo studente:
     * un QuizAttempt non completato su un quiz d'esame.
     * Restituisce il QuizAttempt o null.
     */
    public function activeExamAttempt(string $studentId): ?QuizAttempt
    {
        if ($studentId === '') {
            return null;
        }

        return QuizAttempt::query()
            ->where('student_id', $studentId)
            ->whereNull('completed_at')
            ->whereHas('quiz', fn ($q) =>
                $q->whereNotNull('course_id')->whereNull('module_id'))
            ->latest('started_at')
            ->first();
    }

    public function hasActiveExam(string $studentId): bool
    {
        return $this->activeExamAttempt($studentId) !== null;
    }

    /**
     * Tetto effettivo dei tentativi: max_attempts del quiz + eventuale
     * bonus concesso dall'admin (exam_attempt_grants). Null = illimitato.
     * È l'unico punto autoritativo: start() e show() lo consumano.
     */
    public function effectiveMaxAttempts(Quiz $quiz, string $studentId): ?int
    {
        $base = (int) ($quiz->max_attempts ?? 0);
        if ($base <= 0) {
            return null;
        }

        $extra = (int) DB::table('exam_attempt_grants')
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->value('extra_attempts');

        return $base + $extra;
    }
}

