<?php

namespace App\Http\Controllers\Scuola\Concerns;

use App\Models\School;
use App\Models\Student;

/**
 * Tenancy fase 2: una segreteria opera SOLO sulla propria scuola (confine non
 * negoziabile, §2). Gemello di `ResolvesScholaAccess` di fetta 1. Usato dai
 * controller dell'area `/scuola` (da P12); definito qui in P11 perché lo strato
 * di tenancy nasce con lo schema.
 *
 * Il platform admin NON usa questo trait: attraversa le scuole dall'area /admin.
 */
trait ResolvesSchoolAccess
{
    protected function currentSchoolAdmin(): Student
    {
        $student = Student::findOrFail(session('student_id'));
        abort_unless($student->isSchoolAdmin() && $student->school_id, 403);

        return $student;
    }

    protected function currentSchoolId(): string
    {
        return $this->currentSchoolAdmin()->school_id;
    }

    protected function currentSchool(): School
    {
        return School::findOrFail($this->currentSchoolId());
    }

    /**
     * 403/404 se la risorsa non appartiene alla scuola dell'utente. Accetta
     * qualsiasi model con `school_id` (o uno school_id grezzo).
     */
    protected function assertSameSchool($resourceOrSchoolId): void
    {
        $schoolId = is_string($resourceOrSchoolId)
            ? $resourceOrSchoolId
            : ($resourceOrSchoolId->school_id ?? null);

        abort_unless($schoolId !== null && $schoolId === $this->currentSchoolId(), 403,
            'Risorsa non appartenente alla tua scuola.');
    }

    protected function belongsToCurrentSchool($resource): bool
    {
        return ($resource->school_id ?? null) === $this->currentSchoolId();
    }
}
