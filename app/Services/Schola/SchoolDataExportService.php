<?php

namespace App\Services\Schola;

use App\Models\ArtifactPublication;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;

/**
 * Esporta i dati della PROPRIA scuola (accesso/portabilità GDPR). Sempre
 * vincolato a school_id: nessun dato di altre scuole può finire nell'export.
 */
class SchoolDataExportService
{
    public function generate(\App\Models\School $school): array
    {
        $sid = $school->id;

        $teachers = Student::where('school_id', $sid)->where('role', 'professor')
            ->with('teachableSubjects:id,name')->orderBy('name')->get()
            ->map(fn ($t) => [
                'name' => $t->name, 'email' => $t->email,
                'subjects' => $t->teachableSubjects->pluck('name')->values()->all(),
            ])->all();

        $students = Student::where('school_id', $sid)->where('role', 'student')
            ->with(['classEnrollments' => fn ($q) => $q->where('status', 'active')->with('schoolClass:id,name')])
            ->orderBy('name')->get()
            ->map(fn ($s) => [
                'name' => $s->name, 'email' => $s->email, 'username' => $s->username,
                'birth_date' => $s->birth_date?->toDateString(),
                'classes' => $s->classEnrollments->map(fn ($e) => $e->schoolClass?->name)->filter()->values()->all(),
            ])->all();

        $classes = SchoolClass::forSchool($sid)
            ->with(['coordinator:id,name', 'teachingAssignments.teacher:id,name', 'teachingAssignments.subject:id,name'])
            ->withCount(['classStudents as active_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')->get()
            ->map(fn ($c) => [
                'name' => $c->name, 'school_year' => $c->school_year,
                'coordinator' => $c->coordinator?->name,
                'students_active' => $c->active_count,
                'cattedre' => $c->teachingAssignments->map(fn ($a) => [
                    'teacher' => $a->teacher?->name, 'subject' => $a->subject?->name,
                ])->all(),
            ])->all();

        $classIds = SchoolClass::forSchool($sid)->pluck('id');

        return [
            'generated_at' => now()->toIso8601String(),
            'school' => [
                'name' => $school->name, 'type' => $school->type, 'city' => $school->city,
                'dpa_signed_at' => $school->dpa_signed_at?->toIso8601String(),
            ],
            'counts' => [
                'teachers' => count($teachers),
                'students' => count($students),
                'classes' => count($classes),
                'cattedre' => TeachingAssignment::forSchool($sid)->count(),
                'publications' => ArtifactPublication::whereIn('school_class_id', $classIds)->count(),
                'enrollments_active' => ClassStudent::whereIn('school_class_id', $classIds)->where('status', 'active')->count(),
            ],
            'teachers' => $teachers,
            'students' => $students,
            'classes' => $classes,
        ];
    }
}
