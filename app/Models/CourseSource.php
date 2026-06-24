<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// P25.1 — Sorgente strutturato versionato di un corso. `blocks` è il sorgente di
// verità del contenuto; il PDF è un output. Immutabile: niente updated_at, una
// nuova versione è una nuova riga. Sempre agganciato per `course_id` interno.
class CourseSource extends Model
{
    use HasFactory, HasUuids;

    // Immutabile e versionato: gestiamo solo created_at (vedi migrazione).
    public const UPDATED_AT = null;

    protected $fillable = [
        'course_id',
        'version',
        'blocks',
    ];

    protected $casts = [
        'blocks' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
