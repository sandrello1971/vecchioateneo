<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstructorResolver
{
    /**
     * Decide the instructor_id for a student↔course enrollment.
     *
     * 0 instructors on course  → null
     * 1 instructor on course   → that id (auto, ignores requested)
     * >1 instructors on course → requested must be one of them, else ValidationException
     */
    public function resolveForCourse(string $courseId, ?string $requestedInstructorId): ?string
    {
        $instructorIds = DB::table('course_instructor')
            ->where('course_id', $courseId)
            ->pluck('instructor_id')
            ->all();

        if (count($instructorIds) === 0) {
            return null;
        }

        if (count($instructorIds) === 1) {
            return $instructorIds[0];
        }

        if (empty($requestedInstructorId) || !in_array($requestedInstructorId, $instructorIds, true)) {
            throw ValidationException::withMessages([
                'instructor_id' => 'Questo corso ha più formatori: selezionarne uno valido per il discente.',
            ]);
        }

        return $requestedInstructorId;
    }
}
