<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// P25.3c — Storico applicazioni/rollback (audit). Tabella `course_changelog`.
class CourseChangelog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'course_changelog';

    protected $fillable = [
        'course_id', 'proposal_id', 'parent_proposal_id', 'content_source', 'version_from', 'version_to',
        'kind', 'summary', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(UpdateProposal::class, 'proposal_id');
    }

    // P25.B-b — proposta formatore da cui nasce questa modifica discente coordinata.
    public function parentProposal(): BelongsTo
    {
        return $this->belongsTo(UpdateProposal::class, 'parent_proposal_id');
    }
}
