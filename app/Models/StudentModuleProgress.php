<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentModuleProgress extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'student_module_progress';

    protected $fillable = [
        'student_id', 'module_id', 'status',
        'started_at', 'completed_at', 'time_spent_minutes',
        'tracked_seconds', 'last_heartbeat_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'tracked_seconds' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
