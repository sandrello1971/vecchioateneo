<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// P25.2 — Config dell'agente per corso: ricerca web disattivabile + fonti primarie
// ancorate (spec §2 Fase 2). Una riga per corso.
class CourseFreshnessConfig extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'web_search_enabled',
        'primary_sources',
        'audience',
        'audience_overridden',
        'proposals_enabled',
        'student_proposals_enabled',
        'cadence',
        'last_run_at',
        'topic', // P26 — dominio tematico del corso, per lo Scout di copertura
    ];

    protected $casts = [
        'web_search_enabled' => 'boolean',
        'primary_sources' => 'array',
        'proposals_enabled' => 'boolean',
        'student_proposals_enabled' => 'boolean',
        'audience_overridden' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
