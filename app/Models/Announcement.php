<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Announcement extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'course_id',
        'instructor_id',
        'subject',
        'body',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Student::class, 'instructor_id');
    }

    public function reads()
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Marca questo annuncio come letto da $user (upsert idempotente).
     * Ritorna true se il read receipt e' stato creato (prima lettura),
     * false se gia' esisteva.
     */
    public function markReadBy(Student $user): bool
    {
        $exists = DB::table('announcement_reads')
            ->where('announcement_id', $this->id)
            ->where('student_id', $user->id)
            ->exists();
        if ($exists) {
            return false;
        }
        AnnouncementRead::create([
            'announcement_id' => $this->id,
            'student_id'      => $user->id,
            'read_at'         => now(),
        ]);
        return true;
    }

    public function isReadBy(Student $user): bool
    {
        return DB::table('announcement_reads')
            ->where('announcement_id', $this->id)
            ->where('student_id', $user->id)
            ->exists();
    }

    /**
     * Numero di studenti iscritti attivi del corso che hanno letto.
     * Per la "Letto da X di Y" del formatore.
     */
    public function readsCount(): int
    {
        return $this->reads()->count();
    }

    /**
     * Numero totale dei destinatari (studenti iscritti attivi del corso).
     * Snapshot al momento della chiamata.
     */
    public function recipientsCount(): int
    {
        return DB::table('student_course')
            ->where('course_id', $this->course_id)
            ->where('is_active', true)
            ->count();
    }
}
