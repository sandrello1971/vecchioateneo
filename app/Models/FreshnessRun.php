<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// P25.2 — Una esecuzione dell'agente su un corso. `proposals_created` resta 0 in
// P25.2 (le proposte sono P25.3). Aggancio per course_id interno.
class FreshnessRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'status',
        'started_at',
        'finished_at',
        'claims_found',
        'proposals_created',
        'failure_reason',
        'dismissed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'claims_found' => 'integer',
        'proposals_created' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(FreshnessClaim::class, 'run_id');
    }
}
