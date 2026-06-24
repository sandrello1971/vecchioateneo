<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationId;
    public string $messageId;
    public string $senderId;
    public string $senderName;
    public string $body;
    public string $createdAt;
    public string $recipientId;

    public function __construct(Conversation $conversation, Message $message)
    {
        $this->conversationId = $conversation->id;
        $this->messageId = $message->id;
        $this->senderId = $message->sender_id;
        $this->senderName = $message->sender->name;
        $this->body = $message->body;
        $this->createdAt = $message->created_at->toIso8601String();
        // Recipient = l'altro partecipante (per bumpare il suo unread badge)
        $this->recipientId = $message->sender_id === $conversation->student_id
            ? $conversation->instructor_id
            : $conversation->student_id;
    }

    /**
     * Broadcast su 2 channel:
     * - conversation.{id}: i partecipanti che hanno il thread aperto appendono live
     * - user.{recipient}: il destinatario aggiorna il badge sidebar/inbox
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
            new PrivateChannel('user.' . $this->recipientId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Payload pubblicato sul client. Tutto ciò che serve per renderizzare
     * la bubble nuova nel thread + aggiornare i counter.
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id'      => $this->messageId,
            'sender_id'       => $this->senderId,
            'sender_name'     => $this->senderName,
            'body'            => $this->body,
            'created_at'      => $this->createdAt,
        ];
    }
}
