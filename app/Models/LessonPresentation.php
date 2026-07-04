<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Presentazione generata della lezione (file in storage privato). Generazione: P19.
class LessonPresentation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'lesson_id', 'file_path', 'status', 'source', 'generation_meta', 'spec', 'published_at',
    ];

    protected $casts = [
        'generation_meta' => 'array',
        'spec' => 'array',
        'published_at' => 'datetime',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Pubblicate (visibili agli studenti). */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /** Bozze (in lavorazione, non visibili agli studenti). */
    public function scopeDraft($query)
    {
        return $query->whereNull('published_at');
    }
}
