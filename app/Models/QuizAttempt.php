<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quiz_id', 'student_id', 'started_at', 'completed_at',
        'score', 'passed', 'abandoned', 'time_spent_seconds', 'attempt_number',
        'selected_question_ids',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'abandoned' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'selected_question_ids' => 'array',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }
}
