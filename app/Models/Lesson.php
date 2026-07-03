<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Lezione: unità fruibile dallo studente, composta da N materiali. Il corpo
// (`content`) sarà generato in P19; qui vive solo come schema/organizzazione.
class Lesson extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'topic_id', 'teacher_id', 'title', 'position', 'content',
        'generation_status', 'generation_meta',
    ];

    protected $casts = [
        'position' => 'integer',
        'generation_meta' => 'array',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    // Materiali grezzi classificati in questa lezione (pool → lezione).
    public function teachingDocuments()
    {
        return $this->hasMany(TeachingDocument::class);
    }

    // Artefatti a livello di lezione (P19).
    public function teachingArtifacts()
    {
        return $this->hasMany(TeachingArtifact::class);
    }

    public function publications()
    {
        return $this->hasMany(LessonPublication::class);
    }

    // Note del docente per paragrafo (didattiche, visibili agli studenti).
    public function teacherNotes()
    {
        return $this->hasMany(LessonTeacherNote::class);
    }

    public function presentations()
    {
        return $this->hasMany(LessonPresentation::class);
    }

    // V0 — video narrati della lezione (derivati da una presentazione).
    public function videos()
    {
        return $this->hasMany(LessonVideo::class);
    }
}
