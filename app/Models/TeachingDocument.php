<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class TeachingDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'teacher_id', 'lesson_id', 'title', 'source_type', 'source_url', 'source_files',
        'status', 'failure_reason', 'extracted_text', 'extraction_meta',
        'subject_id', 'tags',
        // Scuola del materiale + condivisione: share_scope null=privato | 'subject' | 'all'(=scuola).
        'school_id', 'share_scope', 'is_school_material',
    ];

    protected $casts = [
        'source_files' => 'array',
        'extraction_meta' => 'array',
        'tags' => 'array',
        'is_school_material' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    // Lezione in cui il materiale è classificato (NULL = pool "da organizzare").
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function artifacts()
    {
        return $this->hasMany(TeachingArtifact::class);
    }

    // Scuola del materiale (= scuola del proprietario alla creazione).
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function isShared(): bool
    {
        return $this->share_scope !== null;
    }

    public function isSchoolMaterial(): bool
    {
        return (bool) $this->is_school_material;
    }

    /**
     * Materiali VISIBILI a un docente nella Biblioteca (esclusi i propri), status=ready:
     *  - is_school_material  → materiale caricato dall'admin della SUA scuola;
     *  - share_scope='all'   → condiviso con tutta la SUA scuola;
     *  - share_scope='subject' → docenti con cattedra (stessa materia + stessa scuola)
     *    in professor_subjects.
     */
    public function scopeVisibleAsSharedTo($query, Student $teacher)
    {
        return $query->where('status', 'ready')
            ->where('teacher_id', '!=', $teacher->id)
            ->where(function ($q) use ($teacher) {
                // Materiale di scuola (admin) della scuola del docente.
                $q->where(function ($q0) use ($teacher) {
                    $q0->where('is_school_material', true)
                       ->where('school_id', $teacher->school_id);
                })
                // Condiviso con tutta la scuola.
                ->orWhere(function ($q1) use ($teacher) {
                    $q1->where('share_scope', 'all')
                       ->where('school_id', $teacher->school_id);
                })
                // Condiviso con la stessa materia (verifica cattedra).
                ->orWhere(function ($q2) use ($teacher) {
                    $q2->where('share_scope', 'subject')
                       ->whereExists(function ($sub) use ($teacher) {
                           $sub->select(DB::raw('1'))
                               ->from('professor_subjects as ps')
                               ->whereColumn('ps.subject_id', 'teaching_documents.subject_id')
                               ->whereColumn('ps.school_id', 'teaching_documents.school_id')
                               ->where('ps.teacher_id', $teacher->id);
                       });
                });
            });
    }

    // Tutti i materiali di una scuola (docenti + admin): area segreteria.
    public function scopeInSchool($query, string $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
