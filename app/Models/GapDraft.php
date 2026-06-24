<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P26 Fase B — Bozza (formatore + studente) per un gap accettato. Editabile dall'admin prima
 * dell'approvazione. Approvarla la marca pronta per la Fase D (inserimento), NON inserisce nulla.
 */
class GapDraft extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'coverage_gap_id',
        'formatore_html',
        'studente_html',
        'note',
        'status',   // generating | draft | approved | discarded | failed
        'error',
        'reviewed_by',
        'reviewed_at',
        // P26 Fase C — posizione scelta (HITL).
        'place_formatore_block_id',
        'place_student_module_id',
        'place_student_anchor',
        'placement_confirmed',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'placement_confirmed' => 'boolean',
    ];

    public function gap(): BelongsTo
    {
        return $this->belongsTo(CoverageGap::class, 'coverage_gap_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
