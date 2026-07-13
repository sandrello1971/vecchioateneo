<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Services\AttendanceRegisterPdfBuilder;
use App\Services\AttendanceService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registro di frequenza: prospetto delle ore maturate per corso (sincrono +
 * FAD asincrono) ed esportazione PDF a valore probatorio.
 */
class AttendanceRegisterController extends Controller
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    /** Registro del corso: una riga per discente iscritto. */
    public function course(Course $course)
    {
        $rows = $this->attendance->courseRegister($course);
        $sessions = $course->sessions()->orderBy('scheduled_at')->get();

        return view('admin.attendance.register.course', compact('course', 'rows', 'sessions'));
    }

    /** Dettaglio cronologico di un discente. */
    public function student(Course $course, Student $student)
    {
        $records = $this->attendance->studentCourseDetail($course, $student);
        $row = $this->attendance->courseRegister($course)->firstWhere('student.id', $student->id);

        return view('admin.attendance.register.student', compact('course', 'student', 'records', 'row'));
    }

    /** Esporta il registro del corso in PDF. */
    public function coursePdf(Course $course, AttendanceRegisterPdfBuilder $builder): StreamedResponse
    {
        $rows = $this->attendance->courseRegister($course);
        $sessions = $course->sessions()->orderBy('scheduled_at')->get();
        $bytes = $builder->buildCourseRegister($course, $rows, $sessions);

        $filename = 'registro-frequenza-' . $course->slug . '.pdf';

        return response()->streamDownload(fn () => print($bytes), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
