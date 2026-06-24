<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'is_custom'];

    protected $casts = [
        'is_custom' => 'boolean',
    ];

    public function schoolClasses()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function teachingDocuments()
    {
        return $this->hasMany(TeachingDocument::class);
    }

    public function teachingArtifacts()
    {
        return $this->hasMany(TeachingArtifact::class);
    }
}
