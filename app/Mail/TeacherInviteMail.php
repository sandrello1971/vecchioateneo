<?php

namespace App\Mail;

use App\Models\School;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Invito docente importato dalla segreteria: credenziali iniziali + link di
// impostazione password (cambio obbligatorio al primo accesso).
class TeacherInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $teacher,
        public string $tempPassword,
        public School $school,
    ) {
    }

    public function envelope(): Envelope
    {
        $brand = $this->school->setting('instance_name') ?: atheneum_setting('instance_name', 'Atheneum');

        return new Envelope(subject: 'Accesso docente — ' . $brand);
    }

    public function content(): Content
    {
        $base = rtrim(config('app.url'), '/');

        return new Content(
            markdown: 'emails.teacher-invite',
            with: [
                'teacher' => $this->teacher,
                'tempPassword' => $this->tempPassword,
                'schoolName' => $this->school->name,
                'loginUrl' => $base . '/learn/login',
            ],
        );
    }
}
