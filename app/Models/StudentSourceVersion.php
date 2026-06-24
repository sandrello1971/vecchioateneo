<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// P25.B-a — Sorgente studente versionato (equivalente di course_sources + backup per il
// rollback, in una sola tabella: per lo studente sorgente == live). `content` =
// [{module_id, content_html}] copia completa del contenuto studente a quella versione.
// Immutabile: una nuova versione è una nuova riga (niente updated_at).
class StudentSourceVersion extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'course_id',
        'version',
        'content',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
