<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// V0 — video narrato di un MODULO (mp4 in storage privato), derivato da una
// ModulePresentation. Gemella di LessonVideo.
class ModuleVideo extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'presentation_id', 'file_path', 'status', 'script_status',
        'script', 'generation_meta', 'published_at', 'video_ai_id', 'indexed_at',
    ];

    protected $casts = [
        'script' => 'array',
        'generation_meta' => 'array',
        'published_at' => 'datetime',
        'indexed_at' => 'datetime',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function presentation()
    {
        return $this->belongsTo(ModulePresentation::class);
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
