<?php

namespace App\Mail;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificationPassedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public Course $course,
        public Certificate $certificate,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎓 Esame finale superato — ' . $this->course->name,
        );
    }

    public function content(): Content
    {
        // Niente score nel template, niente PDF allegato. Il PDF sta dietro login per
        // controllare la distribuzione; lo studente lo scarica dalla piattaforma.
        return new Content(
            markdown: 'emails.certification-passed',
            with: [
                'verifyUrl' => route('certificate.verify', ['code' => $this->certificate->code]),
                'downloadUrl' => route('student.certificate.show', ['course' => $this->course->slug]),
            ],
        );
    }
}
