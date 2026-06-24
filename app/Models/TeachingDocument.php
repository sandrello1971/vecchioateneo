<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'teacher_id', 'lesson_id', 'title', 'source_type', 'source_url', 'source_files',
        'status', 'failure_reason', 'extracted_text', 'extraction_meta',
        'subject_id', 'tags',
    ];

    protected $casts = [
        'source_files' => 'array',
        'extraction_meta' => 'array',
        'tags' => 'array',
    ];

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    // Lezione in cui il materiale è classificato (NULL = pool "da organizzare").
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function artifacts()
    {
        return $this->hasMany(TeachingArtifact::class);
    }
}
