<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Competenza del docente (materia che PUÒ insegnare) nella sua scuola.
class ProfessorSubject extends Model
{
    use HasUuids, BelongsToSchool;

    protected $fillable = ['teacher_id', 'subject_id', 'school_id'];

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
