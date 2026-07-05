<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Video CARICATO dal docente (non generato): analizzato da noscite-videoai
 * (trascrizione + frame + Vision), riproducibile e ricercabile al suo interno.
 * Contesto lezione (lesson_id) o materiale generico (subject_id). Una lezione può
 * averne più di uno.
 */
class UploadedVideo extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'teacher_id', 'lesson_id', 'subject_id', 'school_id', 'artifact_id',
        'title', 'source_filename', 'file_path', 'status', 'failure_reason',
        'video_ai_id', 'indexed_at', 'duration_seconds', 'published_at', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'indexed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    // Trascrizione/analisi come artefatto (per il RAG Minerva).
    public function artifact()
    {
        return $this->belongsTo(TeachingArtifact::class, 'artifact_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isSearchable(): bool
    {
        return $this->status === 'ready' && $this->video_ai_id !== null;
    }
}
