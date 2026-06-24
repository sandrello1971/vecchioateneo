<?php

namespace App\Mail;

use App\Models\School;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Invito/credenziali per la segreteria (school_admin): password temporanea +
// link di accesso (cambio obbligatorio al primo accesso).
class SchoolAdminInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $admin,
        public string $tempPassword,
        public School $school,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Accesso segreteria — ' . $this->school->name);
    }

    public function content(): Content
    {
        $base = rtrim(config('app.url'), '/');

        return new Content(
            markdown: 'emails.school-admin-invite',
            with: [
                'admin' => $this->admin,
                'tempPassword' => $this->tempPassword,
                'schoolName' => $this->school->name,
                'loginUrl' => $base . '/learn/login',
            ],
        );
    }
}
