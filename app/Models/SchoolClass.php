<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id', 'teacher_id', 'name', 'subject_id', 'school_year',
        'invite_code', 'invite_enabled', 'requires_approval', 'is_archived',
    ];

    protected $casts = [
        'invite_enabled' => 'boolean',
        'requires_approval' => 'boolean',
        'is_archived' => 'boolean',
    ];

    // Alfabeto senza caratteri ambigui: niente 0/O/1/I/L.
    public const INVITE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Codice invito univoco di 7 caratteri non ambigui. */
    public static function generateInviteCode(int $length = 7): string
    {
        $alphabet = self::INVITE_ALPHABET;
        $max = strlen($alphabet) - 1;
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    /** Una classe accetta nuovi ingressi solo se non archiviata e col codice attivo. */
    public function acceptsNewMembers(): bool
    {
        return !$this->is_archived && $this->invite_enabled;
    }

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    // Fase 2: nel modello scuola teacher_id assume il significato di
    // "coordinatore" (opzionale). Alias semantico.
    public function coordinator()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function classStudents()
    {
        return $this->hasMany(ClassStudent::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_students', 'school_class_id', 'student_id')
            ->withPivot('status', 'approved_at')
            ->withTimestamps();
    }

    public function publications()
    {
        return $this->hasMany(ArtifactPublication::class);
    }

    // Fase 2: cattedre sulla classe (professore × materia).
    public function teachingAssignments()
    {
        return $this->hasMany(TeachingAssignment::class, 'school_class_id');
    }
}
