<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtifactPublication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'teaching_artifact_id', 'school_class_id',
        'students_can_generate', 'downloadable', 'published_at',
        'rag_status', 'rag_failure_reason',
    ];

    protected $casts = [
        'students_can_generate' => 'boolean',
        'downloadable' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function artifact()
    {
        return $this->belongsTo(TeachingArtifact::class, 'teaching_artifact_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function views()
    {
        return $this->hasMany(StudentArtifactView::class);
    }
}
