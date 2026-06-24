<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'course_id', 'title', 'description', 'file_path', 'file_type',
        'file_size', 'external_url', 'sort_order', 'is_downloadable',
        'video_ai_id', 'is_instructor_only', 'content_html', 'sections_extracted_at',
    ];

    protected $casts = [
        'is_downloadable' => 'boolean',
        'is_instructor_only' => 'boolean',
        'sections_extracted_at' => 'datetime',
    ];

    public function instructorManualSections()
    {
        return $this->hasMany(InstructorManualSection::class, 'material_id')
            ->orderBy('sort_order');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeInstructorOnly($query)
    {
        return $query->where('is_instructor_only', true);
    }

    public function scopeForStudents($query)
    {
        return $query->where('is_instructor_only', false);
    }
}
