<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P26 Fase D — Registro di un inserimento eseguito (formatore + studente), con tutto il
 * necessario per l'undo. status: inserted → reverted. Append-only.
 */
class GapInsertion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'gap_draft_id', 'course_id',
        'formatore_version_from', 'formatore_version_to', 'inserted_block_ids',
        'instructor_section_id', 'instructor_section_html_before',
        'student_module_id', 'student_version_from', 'student_version_to',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'inserted_block_ids' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(GapDraft::class, 'gap_draft_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
