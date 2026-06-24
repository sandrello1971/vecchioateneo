<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'student_id',
        'instructor_id',
        'course_id',
        'subject',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Student::class, 'instructor_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    // Nota: niente relation latestMessage() perché Eloquent ofMany() aggiunge
    // sempre MAX(id) come tiebreaker, e PostgreSQL non ha MAX(uuid). Per la
    // preview "ultimo messaggio" l'inbox usa addSelect con subquery diretta
    // (vedi ConversationController::index).

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
            return $this->instructor;
        }
        if ($user->id === $this->instructor_id) {
            return $this->student;
        }
        return null;
    }
}
