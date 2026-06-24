<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Pubblicazione di una lezione su una classe (livello lezione, gemella di
// ArtifactPublication). La pubblicazione vera è P20.
class LessonPublication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'lesson_id', 'school_class_id', 'students_can_generate',
        'rag_status', 'rag_failure_reason', 'published_at',
    ];

    protected $casts = [
        'students_can_generate' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
