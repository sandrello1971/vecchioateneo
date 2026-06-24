<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P26 Fase A — Gap di copertura candidato prodotto dallo Scout. Nasce `suggested` (HITL);
 * l'admin lo `accepted` (entrerà nelle fasi B/C/D di stesura+inserimento) o lo `dismissed`.
 */
class CoverageGap extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'topic',
        'title',
        'rationale',
        'source_url',
        'source_label',
        'source_topic',   // P26.2 — topic di provenienza
        'source_weight',  // P26.2 — primary | secondary
        'confidence',
        'status',       // suggested | accepted | dismissed
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function draft(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GapDraft::class, 'coverage_gap_id');
    }

    public function scopeSuggested(Builder $q): Builder
    {
        return $q->where('status', 'suggested');
    }

    public function scopeForCourse(Builder $q, string $courseId): Builder
    {
        return $q->where('course_id', $courseId);
    }
}
