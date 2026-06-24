<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Nota del docente per paragrafo di una lezione (P20b). Visibile a tutti gli
// studenti delle classi dove la lezione è pubblicata (didattica, non privata).
class LessonTeacherNote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['lesson_id', 'teacher_id', 'anchor', 'content'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }
}
