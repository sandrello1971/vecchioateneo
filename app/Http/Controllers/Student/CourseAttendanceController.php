<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Student;
use App\Services\AttendanceRegisterPdfBuilder;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registro di frequenza lato FORMATORE: il docente del corso gestisce le proprie
 * sessioni sincrone, segna le presenze e consulta/esporta il registro, dentro
 * l'area /learn. Stesse aggregazioni dell'area admin (AttendanceService).
 */
class CourseAttendanceController extends Controller
{
    use DeterminesTeachingMode;

    public function __construct(private AttendanceService $attendance)
    {
    }

    /** Solo il formatore che insegna il corso può accedere. */
    private function guard(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));
        abort_unless($this->teaches($student, $course), 403);

        return $student;
    }

    public function sessions(Course $course)
    {
        $this->guard($course);
        $sessions = $course->sessions()
            ->withCount(['attendanceRecords as present_count' => fn ($q) => $q->where('source', 'instructor_mark')])
            ->orderByDesc('scheduled_at')
            ->get();

        return view('student.course.attendance.sessions', compact('course', 'sessions'));
    }

    public function createSession(Course $course)
    {
        $this->guard($course);

        return view('student.course.attendance.create', compact('course'));
    }

    public function storeSession(Request $request, Course $course)
    {
        $student = $this->guard($course);
        $data = $this->validated($request);
        $data['course_id'] = $course->id;
        $data['created_by'] = $student->email;

        $session = CourseSession::create($data);

        return redirect()
            ->route('student.course.sessions.show', [$course->slug, $session])
            ->with('success', 'Sessione creata. Registra le presenze.');
    }

    public function showSession(Course $course, CourseSession $session)
    {
        $this->guard($course);
        abort_unless($session->course_id === $course->id, 404);

        $students = $course->students()->orderBy('name')->get();
        $marked = $session->attendanceRecords()
            ->where('source', 'instructor_mark')
            ->get()
            ->keyBy('student_id');

        return view('student.course.attendance.mark', compact('course', 'session', 'students', 'marked'));
    }

    public function mark(Request $request, Course $course, CourseSession $session)
    {
        $this->guard($course);
        abort_unless($session->course_id === $course->id, 404);

        $present = (array) $request->input('present', []);
        $hoursInput = (array) $request->input('hours', []);
        $marks = [];
        foreach ($present as $studentId) {
            $marks[$studentId] = $hoursInput[$studentId] ?? null;
        }

        $count = $this->attendance->markSessionAttendance($session, $marks);

        return redirect()
            ->route('student.course.sessions.show', [$course->slug, $session])
            ->with('success', "Presenze salvate: {$count} presenti.");
    }

    public function destroySession(Course $course, CourseSession $session)
    {
        $this->guard($course);
        abort_unless($session->course_id === $course->id, 404);
        $session->attendanceRecords()->delete();
        $session->delete();

        return redirect()
            ->route('student.course.sessions.index', $course->slug)
            ->with('success', 'Sessione eliminata.');
    }

    public function register(Course $course)
    {
        $this->guard($course);
        $rows = $this->attendance->courseRegister($course);
        $sessions = $course->sessions()->orderBy('scheduled_at')->get();

        return view('student.course.attendance.register', compact('course', 'rows', 'sessions'));
    }

    public function studentDetail(Course $course, Student $student)
    {
        $this->guard($course);
        $records = $this->attendance->studentCourseDetail($course, $student);
        $row = $this->attendance->courseRegister($course)->firstWhere('student.id', $student->id);

        return view('student.course.attendance.student', compact('course', 'student', 'records', 'row'));
    }

    public function registerPdf(Course $course, AttendanceRegisterPdfBuilder $builder): StreamedResponse
    {
        $this->guard($course);
        $rows = $this->attendance->courseRegister($course);
        $sessions = $course->sessions()->orderBy('scheduled_at')->get();
        $bytes = $builder->buildCourseRegister($course, $rows, $sessions);

        return response()->streamDownload(fn () => print($bytes), 'registro-frequenza-' . $course->slug . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'scheduled_at'     => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'modality'         => ['required', 'in:in_person,live_online'],
            'location'         => ['nullable', 'string', 'max:255'],
        ]);
    }
}
