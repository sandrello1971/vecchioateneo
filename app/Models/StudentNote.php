<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StudentNote extends Model
{
    use HasUuids;

    protected $fillable = ['student_id', 'module_id', 'anchor', 'content'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
