<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P26 Fase A — Una esecuzione async dello Scout di copertura su un corso (osservabilità).
 */
class GapScoutRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id', 'status', 'gaps_found', 'failure_reason', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'gaps_found' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
