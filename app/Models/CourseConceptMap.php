<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseConceptMap extends Model
{
    use HasFactory, HasUuids;

    public const VISIBILITY_DRAFT = 'draft';
    public const VISIBILITY_PUBLISHED = 'published';

    protected $fillable = [
        'course_id', 'module_id', 'title', 'description', 'data',
        'visibility', 'ai_generated', 'ai_generated_at',
        'content_hash', 'sort_order',
    ];

    protected $casts = [
        'data' => 'array',
        'ai_generated' => 'boolean',
        'ai_generated_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function studentForks()
    {
        return $this->hasMany(StudentConceptMap::class, 'course_concept_map_id');
    }

    public function isPublished(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLISHED;
    }

    public function isCourseLevel(): bool
    {
        return $this->module_id === null;
    }

    public function isModuleLevel(): bool
    {
        return $this->module_id !== null;
    }

    /**
     * Hash MD5 del contenuto sorgente per detect staleness.
     * - Mappa di modulo: hash del singolo Module.content
     * - Mappa di corso: hash di tutti i Module.content del corso concatenati
     */
    public function currentContentHash(): string
    {
        if ($this->isModuleLevel() && $this->module) {
            return md5(strip_tags($this->module->content ?? ''));
        }

        $aggregated = $this->course
            ->modules()
            ->orderBy('sort_order')
            ->pluck('content')
            ->map(fn ($c) => strip_tags($c ?? ''))
            ->implode("\n---\n");

        return md5($aggregated);
    }

    /**
     * True se la mappa è AI-generated e il contenuto dei moduli è cambiato dopo.
     */
    public function isStale(): bool
    {
        if (! $this->ai_generated || empty($this->content_hash)) {
            return false;
        }

        return $this->content_hash !== $this->currentContentHash();
    }

    public function scopePublished($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLISHED);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }
}
