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
        'lesson_id', 'file_path', 'status', 'source', 'generation_meta', 'spec',
    ];

    protected $casts = [
        'generation_meta' => 'array',
        'spec' => 'array',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
