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
        // Condivisione con altri docenti: null=privato | 'subject' | 'all'.
        'share_scope', 'shared_school_id',
    ];

    protected $casts = [
        'source_files' => 'array',
        'extraction_meta' => 'array',
        'tags' => 'array',
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

    // Scuola nel cui perimetro è stato condiviso (solo per share_scope='subject').
    public function sharedSchool()
    {
        return $this->belongsTo(School::class, 'shared_school_id');
    }

    public function isShared(): bool
    {
        return $this->share_scope !== null;
    }

    /**
     * Materiali condivisi VISIBILI a un docente (esclusi i propri):
     *  - share_scope='all'  → tutti i docenti;
     *  - share_scope='subject' → docenti con cattedra (stessa materia + stessa scuola
     *    del momento della condivisione) in professor_subjects.
     * Solo materiali pronti (status=ready).
     */
    public function scopeVisibleAsSharedTo($query, Student $teacher)
    {
        return $query->whereNotNull('share_scope')
            ->where('status', 'ready')
            ->where('teacher_id', '!=', $teacher->id)
            ->where(function ($q) use ($teacher) {
                $q->where('share_scope', 'all')
                  ->orWhere(function ($q2) use ($teacher) {
                      $q2->where('share_scope', 'subject')
                         ->whereExists(function ($sub) use ($teacher) {
                             $sub->select(DB::raw('1'))
                                 ->from('professor_subjects as ps')
                                 ->whereColumn('ps.subject_id', 'teaching_documents.subject_id')
                                 ->whereColumn('ps.school_id', 'teaching_documents.shared_school_id')
                                 ->where('ps.teacher_id', $teacher->id);
                         });
                  });
            });
    }
}
