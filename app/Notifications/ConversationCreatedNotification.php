<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public Message $firstMessage,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $sender = $this->firstMessage->sender;
        $platformName = atheneum_setting('instance_name', 'Atheneum');
        $platformOwner = atheneum_setting('platform_owner', 'Noscite SRLS');

        $bodyPreview = mb_strlen($this->firstMessage->body) > 200
            ? mb_substr($this->firstMessage->body, 0, 200) . '...'
            : $this->firstMessage->body;

        $link = route('student.messages.show', $this->conversation);

        return (new MailMessage)
            ->subject("Nuovo messaggio da {$sender->name} — {$platformName}")
            ->greeting("Ciao {$notifiable->name},")
            ->line("**{$sender->name}** ha avviato una nuova conversazione con te su {$platformName}.")
            ->line("**Oggetto**: {$this->conversation->subject}")
            ->line("**Anteprima del messaggio**:")
            ->line($bodyPreview)
            ->action('Leggi e rispondi', $link)
            ->line("Riceverai questa email solo all'apertura della conversazione. I messaggi successivi nello stesso thread non genereranno email — usa l'app per le notifiche in tempo reale.")
            ->salutation("— {$platformOwner}");
    }
}
