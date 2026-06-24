<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StudentConceptMap extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id', 'course_concept_map_id', 'data',
        'forked_at', 'last_edited_at',
    ];

    protected $casts = [
        'data' => 'array',
        'forked_at' => 'datetime',
        'last_edited_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function master()
    {
        return $this->belongsTo(CourseConceptMap::class, 'course_concept_map_id');
    }
}
