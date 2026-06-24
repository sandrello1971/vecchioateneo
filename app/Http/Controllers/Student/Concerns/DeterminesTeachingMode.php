<?php

namespace App\Http\Controllers\Student\Concerns;

use App\Models\Course;
use App\Models\Student;

trait DeterminesTeachingMode
{
    /**
     * True se lo studente accede al corso come FORMATORE in docenza:
     * insegna il corso ma NON vi è iscritto come discente.
     */
    protected function isTeachingMode(Student $student, Course $course): bool
    {
        if (!$student->isInstructor()) {
            return false;
        }

        if ($student->auto_enroll_all_courses) {
            return false;
        }

        $enrolledAsStudent = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();

        if ($enrolledAsStudent) {
            return false;
        }

        return $student->taughtCourses()
            ->where('courses.id', $course->id)
            ->exists();
    }

    /** True se il formatore insegna il corso (a prescindere dall'iscrizione). */
    protected function teaches(Student $student, Course $course): bool
    {
        return $student->isInstructor()
            && $student->taughtCourses()->where('courses.id', $course->id)->exists();
    }
}
