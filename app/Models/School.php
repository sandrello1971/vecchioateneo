<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'city', 'settings',
        'allow_professor_create_classes', 'status', 'dpa_signed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'allow_professor_create_classes' => 'boolean',
        'dpa_signed_at' => 'datetime',
    ];

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** Branding per scuola: legge da settings, fallback al default piattaforma. */
    public function setting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function members()
    {
        return $this->hasMany(Student::class);
    }

    public function schoolAdmins()
    {
        // Segreteria = capacità (flag), non role: un account può essere anche
        // professore della stessa scuola.
        return $this->hasMany(Student::class)->where('is_secretary', true);
    }

    public function teachers()
    {
        return $this->hasMany(Student::class)->where('role', 'professor');
    }

    public function students()
    {
        return $this->hasMany(Student::class)->where('role', 'student');
    }

    public function schoolClasses()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function teachingAssignments()
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function importBatches()
    {
        return $this->hasMany(ImportBatch::class);
    }
}
