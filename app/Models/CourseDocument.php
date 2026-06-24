<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Documento PDF generato dell'INTERO corso Officina (file in storage privato).
// Gemella di ModuleDocument (P29), a livello corso: content_hash = hash AGGREGATO
// dei moduli ordinati. Sorgente = module.content concatenati, brand = piattaforma
// (GLITCH). isStale() confronta con Course::currentContentHash().
class CourseDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id', 'file_path', 'status', 'content_hash', 'generation_meta',
    ];

    protected $casts = [
        'generation_meta' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * True se esiste un documento generato ma il corso è cambiato dopo la
     * generazione (modulo modificato/aggiunto/rimosso/riordinato). Stesso pattern
     * di ModuleDocument::isStale() ma sull'hash aggregato del corso. Marca
     * obsoleto, NON rigenera (la rigenerazione è azione esplicita).
     */
    public function isStale(): bool
    {
        if ($this->status !== 'ready' || empty($this->content_hash)) {
            return false;
        }

        return $this->content_hash !== $this->course->currentContentHash();
    }
}
