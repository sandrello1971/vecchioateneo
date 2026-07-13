<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id', 'course_id', 'type', 'source',
        'course_session_id', 'module_id', 'occurred_at', 'hours_credited', 'ip', 'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'hours_credited' => 'decimal:2',
        'meta' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }
}
