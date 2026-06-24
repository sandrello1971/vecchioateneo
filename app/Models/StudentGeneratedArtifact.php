<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGeneratedArtifact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'student_id', 'artifact_publication_id', 'lesson_publication_id', 'type',
        'content', 'quiz_id', 'status', 'failure_reason',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function publication()
    {
        return $this->belongsTo(ArtifactPublication::class, 'artifact_publication_id');
    }

    // Sorgente alternativa: una lezione pubblicata (P20c).
    public function lessonPublication()
    {
        return $this->belongsTo(LessonPublication::class, 'lesson_publication_id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
