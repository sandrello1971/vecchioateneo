<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Documento PDF generato di un MODULO di corso Officina (file in storage privato).
// Gemella di ModulePresentation (P28), in più lo STALE: content_hash registrato
// alla generazione, confrontato con l'hash corrente del modulo. Sorgente =
// module.content, brand = piattaforma (GLITCH). P29.
class ModuleDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'file_path', 'status', 'content_hash', 'generation_meta',
    ];

    protected $casts = [
        'generation_meta' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * True se esiste un documento generato ma il content del modulo è cambiato
     * dopo la generazione. Stesso pattern di Module::isMindmapStale(): confronta
     * il content_hash registrato con quello corrente. Marca obsoleto, NON
     * rigenera (la rigenerazione è azione esplicita).
     */
    public function isStale(): bool
    {
        if ($this->status !== 'ready' || empty($this->content_hash)) {
            return false;
        }

        return $this->content_hash !== $this->module->currentContentHash();
    }
}
