<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadMagnetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public string $pdfPath,
        public string $pdfDisplayName = 'Mappa-Percorso-AI.pdf',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'La tua Mappa Percorso AI — pronta da consultare',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.lead-magnet',
            with: [
                'lead' => $this->lead,
                'contattiUrl' => route('contatti'),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as($this->pdfDisplayName)
                ->withMime('application/pdf'),
        ];
    }
}
