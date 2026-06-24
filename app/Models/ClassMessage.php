<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Messaggio di un thread di classe (P22). Rispecchia App\Models\Message.
class ClassMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'class_conversation_id', 'sender_id', 'body', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(ClassConversation::class, 'class_conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(Student::class, 'sender_id');
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}
