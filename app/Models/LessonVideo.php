<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// V0 — video narrato di una lezione (mp4 in storage privato), derivato da una
// LessonPresentation. Mirror di LessonPresentation. Il copione vive in `script`
// = [{slide_number, text}]; script_status: none|draft|confirmed.
class LessonVideo extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'lesson_id', 'presentation_id', 'file_path', 'status', 'script_status',
        'script', 'generation_meta', 'published_at', 'video_ai_id', 'indexed_at',
    ];

    protected $casts = [
        'script' => 'array',
        'generation_meta' => 'array',
        'published_at' => 'datetime',
        'indexed_at' => 'datetime',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function presentation()
    {
        return $this->belongsTo(LessonPresentation::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeDraft($query)
    {
        return $query->whereNull('published_at');
    }
}
