<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Presentazione generata di un MODULO di corso Officina (file in storage privato).
// Gemella di LessonPresentation; sorgente = module.content, brand = piattaforma. P28.
class ModulePresentation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'file_path', 'status', 'generation_meta', 'spec',
    ];

    protected $casts = [
        'generation_meta' => 'array',
        'spec' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
