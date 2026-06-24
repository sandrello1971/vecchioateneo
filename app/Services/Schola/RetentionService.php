<?php

namespace App\Services\Schola;

use App\Models\ClassStudent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Retention di fine anno scolastico (P16, §7 GDPR).
 *
 * POLICY (esplicita e documentata):
 * - Bersaglio: studenti della scuola (role=student) che hanno avuto almeno una
 *   iscrizione in una classe dell'anno indicato e che NON hanno più alcuna
 *   iscrizione `active` in una classe non archiviata della scuola (studenti
 *   "usciti").
 * - Azione: ANONIMIZZAZIONE della PII (nome, email, username, data di nascita),
 *   account disattivato. La riga resta (integrità referenziale delle attività
 *   aggregate); i dati personali spariscono.
 * - I materiali e gli artefatti del DOCENTE sono CONSERVATI (non toccati).
 * - Nessuna scrittura senza dry-run + force (vedi comando schola:retention).
 */
class RetentionService
{
    /** @return Collection<int,Student> studenti da anonimizzare */
    public function candidates(School $school, string $schoolYear): Collection
    {
        // Classi della scuola nell'anno indicato.
        $yearClassIds = SchoolClass::where('school_id', $school->id)
            ->where('school_year', $schoolYear)->pluck('id');

        if ($yearClassIds->isEmpty()) {
            return collect();
        }

        // Studenti con un'iscrizione (qualsiasi stato) in quelle classi.
        $studentIds = ClassStudent::whereIn('school_class_id', $yearClassIds)
            ->pluck('student_id')->unique();

        // Classi NON archiviate della scuola (qualunque anno).
        $activeClassIds = SchoolClass::where('school_id', $school->id)
            ->where('is_archived', false)->pluck('id');

        return Student::whereIn('id', $studentIds)
            ->where('school_id', $school->id)
            ->where('role', 'student')
            ->get()
            ->filter(function (Student $s) use ($activeClassIds) {
                // "Uscito" = nessuna iscrizione active in una classe non archiviata.
                return !ClassStudent::where('student_id', $s->id)
                    ->whereIn('school_class_id', $activeClassIds)
                    ->where('status', 'active')
                    ->exists();
            })
            ->values();
    }

    /** Anonimizza la PII di uno studente (irreversibile). */
    public function anonymize(Student $student): void
    {
        $student->forceFill([
            'name' => 'Studente anonimizzato',
            'email' => null,
            'username' => null,
            'birth_date' => null,
            'is_active' => false,
            'must_change_password' => false,
            'password' => bcrypt(\Illuminate\Support\Str::random(40)),
        ])->save();
    }
}
