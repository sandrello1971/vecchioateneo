<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDocument extends Model
{
    use HasUuids, SoftDeletes;

    public const VISIBILITY = [
        'private'     => 'Solo io',
        'instructors' => 'Anche i docenti',
    ];

    protected $fillable = [
        'student_id', 'course_id', 'module_id',
        'title', 'description',
        'original_filename', 'file_path', 'file_type', 'file_size',
        'visibility',
    ];

    protected $casts = [
        'file_size' => 'integer',
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

    public function scopeOwnedBy($query, string $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeSharedWithInstructors($query)
    {
        return $query->where('visibility', 'instructors');
    }

    public function getVisibilityLabelAttribute(): string
    {
        return self::VISIBILITY[$this->visibility] ?? $this->visibility;
    }

    public function getHumanSizeAttribute(): string
    {
        $size = (int) $this->file_size;
        if ($size <= 0) return '—';
        if ($size < 1024) return $size . ' B';
        if ($size < 1024 * 1024) return number_format($size / 1024, 1) . ' KB';
        return number_format($size / (1024 * 1024), 1) . ' MB';
    }
}
