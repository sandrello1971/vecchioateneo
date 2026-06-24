<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentArtifactView extends Model
{
    use HasFactory, HasUuids;

    // La tabella traccia first/last_viewed_at, non created_at/updated_at.
    public $timestamps = false;

    protected $fillable = [
        'artifact_publication_id', 'student_id',
        'first_viewed_at', 'last_viewed_at', 'view_count',
    ];

    protected $casts = [
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
    ];

    public function publication()
    {
        return $this->belongsTo(ArtifactPublication::class, 'artifact_publication_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
