<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Student $student, public ?string $baseUrl = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '📚 Ti aspettiamo su ' . atheneum_setting('instance_name', 'Atheneum'));
    }

    public function content(): Content
    {
        $base = rtrim($this->baseUrl ?? config('app.url'), '/');

        return new Content(
            markdown: 'emails.student-reminder',
            with: [
                'dashboardUrl' => $base . '/learn/dashboard',
            ],
        );
    }
}
