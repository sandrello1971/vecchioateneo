<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnansweredQuestion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'school_class_id', 'student_id', 'question', 'best_similarity', 'status',
    ];

    protected $casts = [
        'best_similarity' => 'float',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
