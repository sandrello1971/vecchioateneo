<?php

namespace App\Notifications;

use App\Models\ClassConversation;
use App\Models\ClassMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Notifica email all'altro partecipante alla CREAZIONE di un thread di classe
// (P22). Rispecchia ConversationCreatedNotification del mondo corsi: email una
// sola volta, i follow-up non generano email. Il link va alla view del lato
// destinatario (docente o studente).
class ClassConversationCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ClassConversation $conversation,
        public ClassMessage $firstMessage,
        public bool $recipientIsTeacher,
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

        $preview = mb_strlen($this->firstMessage->body) > 200
            ? mb_substr($this->firstMessage->body, 0, 200) . '...'
            : $this->firstMessage->body;

        $link = $this->recipientIsTeacher
            ? route('docente.classi.messaggi.show', [$this->conversation->school_class_id, $this->conversation->id])
            : route('student.classi.messaggi.show', [$this->conversation->school_class_id, $this->conversation->id]);

        return (new MailMessage)
            ->subject("Nuovo messaggio da {$sender->name} — {$platformName}")
            ->greeting("Ciao {$notifiable->name},")
            ->line("**{$sender->name}** ha avviato una nuova conversazione con te.")
            ->line("**Oggetto**: {$this->conversation->subject}")
            ->line('**Anteprima del messaggio**:')
            ->line($preview)
            ->action('Leggi e rispondi', $link)
            ->line("Riceverai questa email solo all'apertura della conversazione: i messaggi successivi nello stesso thread non generano email.")
            ->salutation("— {$platformOwner}");
    }
}
