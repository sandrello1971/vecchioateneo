<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Presentazione generata di un MODULO di corso Officina (file in storage privato).
// Gemella di LessonPresentation; sorgente = module.content, brand = piattaforma. P28.
class ModulePresentation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'file_path', 'status', 'source', 'generation_meta', 'spec', 'published_at',
    ];

    protected $casts = [
        'generation_meta' => 'array',
        'spec' => 'array',
        'published_at' => 'datetime',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Pubblicate (visibili ai corsisti). */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /** Bozze (in lavorazione, non visibili ai corsisti). */
    public function scopeDraft($query)
    {
        return $query->whereNull('published_at');
    }
}
