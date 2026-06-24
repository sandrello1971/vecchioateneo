<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P26 Fase 0 — Fonte attendibile per dominio tematico. Condivisa tra corsi dello stesso
 * `topic`. `mode`: search (dominio da cercare) | fetch (pagina specifica). `status`: una
 * fonte diventa `approved` SOLO per azione admin (HITL); l'agente propone `suggested`.
 * Lo Scout (fase successiva) cercherà SOLO tra le fonti approved del topic.
 */
class TrustedSource extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'label',
        'url_or_domain',
        'mode',         // search | fetch
        'topic',
        'status',       // suggested | approved | rejected
        'proposed_by',  // agent | admin
        'notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopeSuggested(Builder $q): Builder
    {
        return $q->where('status', 'suggested');
    }

    /** Filtro opzionale per stato (no-op se null). */
    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('status', $status) : $q;
    }

    /** Filtro opzionale per dominio tematico (no-op se null). */
    public function scopeTopic(Builder $q, ?string $topic): Builder
    {
        return $topic ? $q->where('topic', $topic) : $q;
    }
}
