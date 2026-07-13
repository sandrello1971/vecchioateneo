<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSession;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

/**
 * Gestione delle sessioni sincrone (aula / live online) di un corso e
 * registrazione della presenza dei discenti da parte del docente/admin.
 */
class CourseSessionController extends Controller
{
    public function index(Course $course)
    {
        $sessions = $course->sessions()
            ->withCount(['attendanceRecords as present_count' => fn ($q) => $q->where('source', 'instructor_mark')])
            ->orderByDesc('scheduled_at')
            ->get();

        return view('admin.attendance.sessions.index', compact('course', 'sessions'));
    }

    public function create(Course $course)
    {
        return view('admin.attendance.sessions.create', compact('course'));
    }

    public function store(Request $request, Course $course)
    {
        $data = $this->validated($request);
        $data['course_id'] = $course->id;
        $data['created_by'] = session('admin_email');

        $session = CourseSession::create($data);

        return redirect()
            ->route('admin.courses.sessions.show', [$course, $session])
            ->with('success', 'Sessione creata. Registra le presenze.');
    }

    /** Foglio presenze: elenco iscritti con lo stato attuale. */
    public function show(Course $course, CourseSession $session)
    {
        abort_unless($session->course_id === $course->id, 404);

        $students = $course->students()->orderBy('name')->get();
        $marked = $session->attendanceRecords()
            ->where('source', 'instructor_mark')
            ->get()
            ->keyBy('student_id');

        return view('admin.attendance.sessions.show', compact('course', 'session', 'students', 'marked'));
    }

    /** Salva il foglio presenze. */
    public function mark(Request $request, Course $course, CourseSession $session, AttendanceService $attendance)
    {
        abort_unless($session->course_id === $course->id, 404);

        // present[] = id degli studenti spuntati; hours[id] = ore opzionali.
        $present = (array) $request->input('present', []);
        $hoursInput = (array) $request->input('hours', []);
        $marks = [];
        foreach ($present as $studentId) {
            $marks[$studentId] = $hoursInput[$studentId] ?? null;
        }

        $count = $attendance->markSessionAttendance($session, $marks);

        return redirect()
            ->route('admin.courses.sessions.show', [$course, $session])
            ->with('success', "Presenze salvate: {$count} presenti.");
    }

    public function update(Request $request, Course $course, CourseSession $session)
    {
        abort_unless($session->course_id === $course->id, 404);
        $session->update($this->validated($request));

        return back()->with('success', 'Sessione aggiornata.');
    }

    public function destroy(Course $course, CourseSession $session)
    {
        abort_unless($session->course_id === $course->id, 404);
        $session->attendanceRecords()->delete();
        $session->delete();

        return redirect()
            ->route('admin.courses.sessions.index', $course)
            ->with('success', 'Sessione eliminata.');
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
