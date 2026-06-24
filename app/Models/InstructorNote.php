<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstructorNote extends Model
{
    use HasUuids, SoftDeletes;

    public const KINDS = [
        'metafora'           => ['emoji' => '💡', 'label' => 'Metafora'],
        'aneddoto'           => ['emoji' => '📖', 'label' => 'Aneddoto / storia'],
        'errore_comune'      => ['emoji' => '🎯', 'label' => 'Errore comune'],
        'domanda_frequente'  => ['emoji' => '❓', 'label' => 'Domanda frequente'],
        'caso_aziendale'     => ['emoji' => '🏢', 'label' => 'Caso aziendale'],
        'esercizio_extra'    => ['emoji' => '🎓', 'label' => 'Esercizio extra'],
        'setup_tecnico'      => ['emoji' => '🔧', 'label' => 'Setup tecnico'],
        'timing'             => ['emoji' => '⏱', 'label' => 'Timing / ritmo'],
        'materiale_extra'    => ['emoji' => '📊', 'label' => 'Materiale extra'],
        'link'               => ['emoji' => '🔗', 'label' => 'Link / risorsa'],
        'promemoria'         => ['emoji' => '📌', 'label' => 'Promemoria'],
        'idea_futura'        => ['emoji' => '🆕', 'label' => 'Idea futura'],
    ];

    protected $fillable = [
        'instructor_id', 'course_id', 'module_id', 'instructor_manual_section_id',
        'kind', 'title', 'body_markdown', 'tags', 'is_shared',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_shared' => 'boolean',
    ];

    public function instructor()
    {
        return $this->belongsTo(Student::class, 'instructor_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function section()
    {
        return $this->belongsTo(InstructorManualSection::class, 'instructor_manual_section_id');
    }

    public function images()
    {
        return $this->hasMany(InstructorNoteImage::class);
    }

    public function getEmojiAttribute(): string
    {
        return self::KINDS[$this->kind]['emoji'] ?? '📝';
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind]['label'] ?? $this->kind;
    }

    public function scopeVisibleTo($query, string $instructorId)
    {
        return $query->where(function ($q) use ($instructorId) {
            $q->where('instructor_id', $instructorId)
              ->orWhere('is_shared', true);
        });
    }
}
