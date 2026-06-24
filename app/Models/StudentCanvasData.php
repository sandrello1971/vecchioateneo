<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StudentCanvasData extends Model
{
    use HasUuids;

    protected $table = 'student_canvas_data';

    protected $fillable = ['student_id', 'material_id', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
