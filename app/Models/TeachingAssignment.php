<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Cattedra: professore × materia × classe × anno scolastico.
class TeachingAssignment extends Model
{
    use HasFactory, HasUuids, BelongsToSchool;

    protected $fillable = [
        'school_id', 'teacher_id', 'subject_id', 'school_class_id', 'school_year',
    ];

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }
}
