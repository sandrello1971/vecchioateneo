<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InstructorNoteImage extends Model
{
    use HasUuids;

    protected $fillable = ['instructor_note_id', 'file_path', 'file_size', 'mime_type'];

    public function note()
    {
        return $this->belongsTo(InstructorNote::class, 'instructor_note_id');
    }
}
