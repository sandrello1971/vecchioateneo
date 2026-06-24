<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Caricamento massivo (docenti/studenti): preview → commit. Schema da P11,
// logica di import da P13/P14.
class ImportBatch extends Model
{
    use HasFactory, HasUuids, BelongsToSchool;

    protected $fillable = [
        'school_id', 'created_by', 'type', 'status', 'source_filename', 'summary', 'rows',
    ];

    protected $casts = [
        'summary' => 'array',
        'rows' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(Student::class, 'created_by');
    }
}
