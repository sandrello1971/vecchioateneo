<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Thread 1:1 studente↔docente nell'ambito di una classe (P22). Rispecchia
// App\Models\Conversation del mondo corsi.
class ClassConversation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'school_class_id', 'student_id', 'teacher_id', 'subject', 'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function messages()
    {
        return $this->hasMany(ClassMessage::class)->orderBy('created_at');
    }

    public function unreadCountFor(Student $user): int
    {
        return $this->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->count();
    }

    public function markReadFor(Student $user): int
    {
        return $this->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);
    }

    public function otherParticipant(Student $user): ?Student
    {
        if ($user->id === $this->student_id) {
            return $this->teacher;
        }
        if ($user->id === $this->teacher_id) {
            return $this->student;
        }
        return null;
    }

    public function hasParticipant(Student $user): bool
    {
        return $user->id === $this->student_id || $user->id === $this->teacher_id;
    }
}
