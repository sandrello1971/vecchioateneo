<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

// Annuncio del docente a tutta la classe (P22). Rispecchia App\Models\Announcement.
class ClassAnnouncement extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'school_class_id', 'teacher_id', 'subject', 'body',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function reads()
    {
        return $this->hasMany(ClassAnnouncementRead::class);
    }

    /** Marca come letto da $user (upsert idempotente). True alla prima lettura. */
    public function markReadBy(Student $user): bool
    {
        $exists = DB::table('class_announcement_reads')
            ->where('class_announcement_id', $this->id)
            ->where('student_id', $user->id)
            ->exists();
        if ($exists) {
            return false;
        }
        ClassAnnouncementRead::create([
            'class_announcement_id' => $this->id,
            'student_id' => $user->id,
            'read_at' => now(),
        ]);
        return true;
    }

    public function isReadBy(Student $user): bool
    {
        return DB::table('class_announcement_reads')
            ->where('class_announcement_id', $this->id)
            ->where('student_id', $user->id)
            ->exists();
    }

    public function readsCount(): int
    {
        return $this->reads()->count();
    }

    /** Destinatari = studenti attivi della classe (snapshot). */
    public function recipientsCount(): int
    {
        return DB::table('class_students')
            ->where('school_class_id', $this->school_class_id)
            ->where('status', 'active')
            ->count();
    }
}
