<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emesso quando un thread viene aperto ex-novo. Notifica il destinatario in
 * tempo reale cosi puo' aggiornare l'inbox (apparizione thread + badge) senza
 * refresh, anche se non e' sulla pagina del thread.
 */
class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationId;
    public string $recipientId;
    public string $subject;
    public string $initiatorName;
    public ?string $courseName;

    public function __construct(Conversation $conversation, string $initiatorId)
    {
        $this->conversationId = $conversation->id;
        $this->recipientId = $initiatorId === $conversation->student_id
            ? $conversation->instructor_id
            : $conversation->student_id;
        $this->subject = $conversation->subject;
        $this->initiatorName = $initiatorId === $conversation->student_id
            ? $conversation->student->name
            : $conversation->instructor->name;
        $this->courseName = $conversation->course?->name;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'ConversationCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'subject'         => $this->subject,
            'initiator_name'  => $this->initiatorName,
            'course_name'     => $this->courseName,
        ];
    }
}
