<?php

namespace App\Services\Schola;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;

/**
 * Criterio di accesso del docente a una classe (P15). Fonte unica della regola
 * proprietà-vs-cattedra, usata da pubblicazione, cruscotto, Minerva, viste.
 *
 * - Classe LIBERA (`school_id` NULL): vale la PROPRIETÀ (`teacher_id`) — il
 *   comportamento di fetta 1 resta BYTE-IDENTICO.
 * - Classe di SCUOLA (`school_id` valorizzato): vale la CATTEDRA
 *   (`teaching_assignments`); la proprietà non conta (teacher_id = coordinatore).
 */
class TeacherClassAccess
{
    /** Il docente insegna in questa classe (può pubblicare / aprire Minerva / vedere il cruscotto)? */
    public function canTeach(string $teacherId, SchoolClass $class): bool
    {
        if ($class->school_id === null) {
            return $class->teacher_id === $teacherId;
        }

        return TeachingAssignment::where('teacher_id', $teacherId)
            ->where('school_class_id', $class->id)
            ->exists();
    }

    /** Query delle classi su cui il docente opera (index/selettori/Minerva). */
    public function classesQuery(string $teacherId)
    {
        return SchoolClass::where(function ($q) use ($teacherId) {
            $q->where(function ($free) use ($teacherId) {
                $free->whereNull('school_id')->where('teacher_id', $teacherId);
            })->orWhereIn('id', TeachingAssignment::where('teacher_id', $teacherId)->select('school_class_id'));
        });
    }

    /**
     * La materia dell'artefatto è coperta da una cattedra del docente in questa
     * classe? Solo informativo (avviso, NON blocco). Classe libera o artefatto
     * senza materia → nessun avviso.
     */
    public function subjectCoveredByCattedra(string $teacherId, SchoolClass $class, ?string $subjectId): bool
    {
        if ($class->school_id === null || $subjectId === null) {
            return true;
        }

        return TeachingAssignment::where('teacher_id', $teacherId)
            ->where('school_class_id', $class->id)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    /** Può GESTIRE la classe (modifica/codice/roster): solo classi libere proprie. */
    public function canManage(string $teacherId, SchoolClass $class): bool
    {
        return $class->school_id === null && $class->teacher_id === $teacherId;
    }

    /** Può creare classi? Docente libero sempre; docente di scuola solo con deroga. */
    public function canCreateClasses(Student $teacher): bool
    {
        if ($teacher->school_id === null) {
            return true;
        }

        return (bool) optional($teacher->school)->allow_professor_create_classes;
    }
}
