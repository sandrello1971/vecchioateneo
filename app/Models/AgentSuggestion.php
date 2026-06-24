<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentSuggestion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'recipient_type', 'recipient_id', 'school_class_id', 'type',
        'title', 'body', 'payload', 'status', 'source', 'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function recipient()
    {
        return $this->belongsTo(Student::class, 'recipient_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }
}
