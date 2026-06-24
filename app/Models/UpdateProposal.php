<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// P25.3 — Proposta di aggiornamento nella coda HITL. Nasce da un freshness_claim
// 'obsoleto'. `before` (verbatim) è l'ancora per l'applicazione; nulla viene applicato
// senza status='approved' (HITL non negoziabile).
class UpdateProposal extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'run_id',
        'freshness_claim_id',
        'course_id',
        'content_source',
        'module_id',
        'block_id',
        'sentence_ref',
        'before',
        'after',
        'reason',
        'source',
        'source_type',
        'confidence',
        'audience',
        'status',
        'after_edited_by_human',
        'reviewed_by',
        'reviewed_at',
        'applied_at',
        'apply_error',
        // P25.B-b — coordinamento formatore→discente
        'parent_proposal_id',
        'origin',
        'match_confidence',
        'match_trust',
        'orphaned_at',
        'orphan_reason',
    ];

    protected $casts = [
        'sentence_ref' => 'integer',
        'confidence' => 'float',
        'after_edited_by_human' => 'boolean',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
        'match_confidence' => 'float',
        'orphaned_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // P25.B-a — modulo studente ancorato (solo per content_source='student').
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(FreshnessClaim::class, 'freshness_claim_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(FreshnessRun::class, 'run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    // P25.B-b — la proposta formatore che ha generato questa proposta discente coordinata.
    public function parentProposal(): BelongsTo
    {
        return $this->belongsTo(UpdateProposal::class, 'parent_proposal_id');
    }

    // P25.B-b — le proposte discente coordinate generate da questa proposta formatore.
    public function childProposals(): HasMany
    {
        return $this->hasMany(UpdateProposal::class, 'parent_proposal_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Coordinate (figlie di un'approvazione formatore) vs autonome. */
    public function scopeCoordinated($query)
    {
        return $query->where('origin', 'coordinated');
    }

    /** Orfane: il padre è stato rollbackato/rifiutato dopo la generazione/applicazione. */
    public function scopeOrphaned($query)
    {
        return $query->whereNotNull('orphaned_at');
    }
}
