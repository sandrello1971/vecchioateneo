<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// P25.3c — Backup del contenuto formatore live pre-applicazione (per rollback).
class FormatoreSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id', 'course_source_id', 'version',
        'instructor_manual_section_id', 'content_html',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(InstructorManualSection::class, 'instructor_manual_section_id');
    }
}
