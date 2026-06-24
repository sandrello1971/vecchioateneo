<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id', 'title', 'description', 'content',
        'duration_minutes', 'sort_order', 'is_active', 'video_url',
        'video_ai_id', 'video_filename', 'video_status',
        'mindmap_markdown', 'mindmap_content_hash', 'mindmap_generated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'mindmap_generated_at' => 'datetime',
    ];

    /**
     * Calcola hash MD5 del content corrente per detectare modifiche.
     * Strip HTML per ignorare differenze cosmetiche (a-tag, span, class names).
     */
    public function currentContentHash(): string
    {
        return md5(strip_tags($this->content ?? ''));
    }

    /**
     * True se esiste una mindmap ma il content e' cambiato dopo la generazione.
     * Usato dal badge UI "mappa obsoleta".
     */
    public function isMindmapStale(): bool
    {
        if (empty($this->mindmap_markdown) || empty($this->mindmap_content_hash)) {
            return false;
        }
        return $this->mindmap_content_hash !== $this->currentContentHash();
    }

    /**
     * True se esiste una mindmap generata (anche se obsoleta).
     */
    public function hasMindmap(): bool
    {
        return !empty($this->mindmap_markdown);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class)->orderBy('sort_order');
    }

    // P28 — presentazione .pptx generata del modulo (una per modulo).
    public function presentation()
    {
        return $this->hasOne(ModulePresentation::class);
    }

    // P29 — documento PDF generato del modulo (uno per modulo).
    public function document()
    {
        return $this->hasOne(ModuleDocument::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function documentsRag()
    {
        return $this->hasMany(DocumentRag::class);
    }

    public function instructorManualSections()
    {
        return $this->hasMany(InstructorManualSection::class, 'module_id')
            ->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
