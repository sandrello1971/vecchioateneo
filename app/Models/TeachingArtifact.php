<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingArtifact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'teaching_document_id', 'teacher_id', 'lesson_id', 'type', 'title', 'content',
        'quiz_id', 'status', 'generation_meta', 'shared_with_teachers',
        'origin_artifact_id', 'subject_id', 'tags',
    ];

    protected $casts = [
        'generation_meta' => 'array',
        'tags' => 'array',
        'shared_with_teachers' => 'boolean',
    ];

    public function teachingDocument()
    {
        return $this->belongsTo(TeachingDocument::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    // Lezione a cui l'artefatto è legato (livello lezione, NULL = nessuna).
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function publications()
    {
        return $this->hasMany(ArtifactPublication::class);
    }

    // Lineage fork (origin_artifact_id senza FK: l'attribuzione sopravvive
    // alla cancellazione dell'originale).
    public function origin()
    {
        return $this->belongsTo(TeachingArtifact::class, 'origin_artifact_id');
    }

    public function forks()
    {
        return $this->hasMany(TeachingArtifact::class, 'origin_artifact_id');
    }
}
