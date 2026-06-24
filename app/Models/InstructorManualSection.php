<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InstructorManualSection extends Model
{
    use HasUuids;

    protected $fillable = [
        'material_id', 'course_id', 'module_id',
        'title', 'anchor', 'heading_level', 'sort_order',
        'content_html', 'module_assigned_manually',
    ];

    protected $casts = [
        'module_assigned_manually' => 'boolean',
        'heading_level' => 'integer',
        'sort_order' => 'integer',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
