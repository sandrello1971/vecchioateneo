<?php

namespace App\Http\Controllers\Student\Concerns;

use App\Models\ArtifactPublication;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;

/**
 * Accessi condivisi lato studente per Schola: iscrizione ATTIVA alla classe
 * (pending/removed esclusi) e coerenza pubblicazione↔classe. Defense in depth:
 * ogni controller (feed, fruizione, generazione, download sorgente) richiama
 * questi gate.
 */
trait ResolvesScholaAccess
{
    protected function currentStudent(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    /** True se lo studente ha iscrizione ATTIVA nella classe. */
    protected function isActiveInClass(string $classId, string $studentId): bool
    {
        return ClassStudent::where('school_class_id', $classId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
    }

    /** 403 se non iscritto attivo (pending/removed/estraneo). */
    protected function assertActiveEnrollment(SchoolClass $class, string $studentId): void
    {
        abort_unless($this->isActiveInClass($class->id, $studentId), 403,
            'Devi essere iscritto e approvato in questa classe.');
    }

    /** 404 se la pubblicazione non appartiene alla classe del path. */
    protected function assertPublicationInClass(ArtifactPublication $publication, SchoolClass $class): void
    {
        abort_unless($publication->school_class_id === $class->id, 404);
    }
}
