<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Ricevuta di lettura di un annuncio di classe (P22). Rispecchia AnnouncementRead.
class ClassAnnouncementRead extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'class_announcement_id', 'student_id', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(ClassAnnouncement::class, 'class_announcement_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
