<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use App\Services\CertificatePdfBuilder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    public function __construct(
        private CertificatePdfBuilder $pdfBuilder,
    ) {
    }

    public function download(Course $course): Response
    {
        $cert = $this->resolveCertificate($course);
        return $this->serve($cert, $course, asDownload: true);
    }

    public function show(Course $course): Response
    {
        $cert = $this->resolveCertificate($course);
        return $this->serve($cert, $course, asDownload: false);
    }

    /**
     * Carica il certificato per (studente loggato, corso). Verifica l'iscrizione
     * attiva al corso e l'esistenza del certificato (gating: solo se ha superato
     * il final quiz). Senza certificato emesso → 403.
     */
    private function resolveCertificate(Course $course): Certificate
    {
        $student = Student::findOrFail(session('student_id'));

        $enrolled = $student->courses()
            ->wherePivot('is_active', true)
            ->where('courses.id', $course->id)
            ->exists();
        abort_unless($enrolled, 403);

        $cert = Certificate::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->latest('issued_at')
            ->first();

        abort_unless(
            $cert,
            403,
            'Devi superare l\'esame finale prima di scaricare il certificato.'
        );

        return $cert;
    }

    /**
     * Strategia di consegna del PDF, in ordine di preferenza:
     *
     *  1. effectivePdfPath() != null → servire dal disco. Veloce e
     *     include la firma digitale se il PDF è quello signed.
     *
     *  2. effectivePdfPath() == null → certificato legacy creato prima
     *     del refactor (oppure observer fallito). Rigenerazione on-the-fly
     *     via CertificatePdfBuilder, comportamento identico al pre-refactor.
     */
    private function serve(Certificate $cert, Course $course, bool $asDownload): Response
    {
        $effectivePath = $cert->effectivePdfPath();
        $filename = $this->filename($cert, $course);

        if ($effectivePath !== null) {
            $absolutePath = Storage::disk('local')->path($effectivePath);

            if ($asDownload) {
                return response()->download($absolutePath, $filename, [
                    'Content-Type' => 'application/pdf',
                ]);
            }

            return response()->file($absolutePath, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        // Fallback on-the-fly per certificati legacy (pre refactor)
        // o edge case observer fallito.
        // build() ora ritorna bytes (string) — non più oggetto DomPDF —
        // dopo lo switch a FPDI+TCPDF.
        $bytes = $this->pdfBuilder->build($cert);
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => ($asDownload ? 'attachment' : 'inline')
                . '; filename="' . ($asDownload ? $filename : 'certificato.pdf') . '"',
        ];

        return response()->make($bytes, 200, $headers);
    }

    private function filename(Certificate $cert, Course $course): string
    {
        $student = $cert->student;
        return 'Certificato-'
            . str_replace(' ', '-', $course->name) . '-'
            . str_replace(' ', '-', $student->name) . '.pdf';
    }
}
