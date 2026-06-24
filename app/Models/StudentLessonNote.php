<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Appunto personale dello studente per paragrafo di una lezione (P20b).
class StudentLessonNote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['student_id', 'lesson_id', 'anchor', 'content'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
