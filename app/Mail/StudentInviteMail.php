<?php

namespace App\Mail;

use App\Models\School;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Invito studente di scuola con email: credenziali iniziali + link di
// impostazione password (cambio obbligatorio al primo accesso).
class StudentInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $tempPassword,
        public School $school,
    ) {
    }

    public function envelope(): Envelope
    {
        $brand = $this->school->setting('instance_name') ?: atheneum_setting('instance_name', 'Atheneum');

        return new Envelope(subject: 'Il tuo accesso — ' . $brand);
    }

    public function content(): Content
    {
        $base = rtrim(config('app.url'), '/');

        return new Content(
            markdown: 'emails.student-invite',
            with: [
                'student' => $this->student,
                'tempPassword' => $this->tempPassword,
                'schoolName' => $this->school->name,
                'loginUrl' => $base . '/learn/login',
            ],
        );
    }
}
