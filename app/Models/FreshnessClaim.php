<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// P25.2 — Affermazione databile estratta (Fase 1) e verificata (Fase 2).
// Posizione (course_id, block_id, sentence_ref) = ancora per il diff chirurgico di
// P25.3. I campi Fase 2 (verdict/source_*/confidence/verified_at) sono null finché
// la verifica non avviene.
class FreshnessClaim extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'run_id',
        'course_id',
        'content_source',
        'module_id',
        'block_id',
        'sentence_ref',
        'claim_text',
        'category',
        'verdict',
        'source_url',
        'source_type',
        'source_date',
        'confidence',
        'verified_at',
    ];

    protected $casts = [
        'sentence_ref' => 'integer',
        'source_date' => 'date',
        'confidence' => 'float',
        'verified_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(FreshnessRun::class, 'run_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // P25.B-a — modulo studente ancorato (solo per content_source='student').
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
