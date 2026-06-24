<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Collection;

class StudentCourseAccess
{
    /**
     * Corsi navigabili dallo studente, ciascuno con ->access_kind:
     *  - 'enrolled' : iscritto (o auto_enroll) → discente pieno
     *  - 'teaching' : formatore che insegna il corso ma NON vi è iscritto
     *
     * Rispetta demo e auto_enroll_all_courses esattamente come la
     * dashboard. Ordinati per sort_order.
     */
    public function navigableCourses(Student $student): Collection
    {
        if ($student->auto_enroll_all_courses) {
            $enrolled = Course::where('is_active', true)
                ->orderBy('sort_order')->get();
        } else {
            $enrolled = $student->courses()
                ->wherePivot('is_active', true)
                ->orderBy('sort_order')->get();
        }

        if ($student->is_demo) {
            return $enrolled->where('slug', 'primus')->values()
                ->each(fn ($c) => $c->access_kind = 'enrolled');
        }

        $enrolled->each(fn ($c) => $c->access_kind = 'enrolled');

        $result = $enrolled;

        if ($student->isInstructor() && !$student->auto_enroll_all_courses) {
            $enrolledIds = $enrolled->pluck('id')->all();

            $taught = $student->taughtCourses()
                ->where('is_active', true)
                ->whereNotIn('courses.id', $enrolledIds)
                ->orderBy('sort_order')->get()
                ->each(fn ($c) => $c->access_kind = 'teaching');

            $result = $enrolled->concat($taught)
                ->sortBy('sort_order')->values();
        }

        return $result;
    }
}
