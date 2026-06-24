<?php

namespace App\Http\Controllers\Student\Concerns;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Support\ExamState;

/**
 * Sugar sintattico per i controller. La logica vera è in
 * App\Support\ExamState: questo trait vi delega, non duplica.
 */
trait EvaluatesExamState
{
    protected function examState(): ExamState
    {
        return app(ExamState::class);
    }

    protected function isExamQuiz(Quiz $quiz): bool
    {
        return $this->examState()->isExamQuiz($quiz);
    }

    protected function activeExamAttempt(string $studentId): ?QuizAttempt
    {
        return $this->examState()->activeExamAttempt($studentId);
    }

    protected function hasActiveExam(string $studentId): bool
    {
        return $this->examState()->hasActiveExam($studentId);
    }
}
