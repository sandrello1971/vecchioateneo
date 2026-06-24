<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StudentWelcomeMail;
use App\Models\Course;
use App\Models\Student;
use App\Services\InstructorResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function __construct(private InstructorResolver $instructorResolver)
    {
    }

    public function index(Request $request)
    {
        $query = Student::with('courses')
            ->where(function ($q) {
                $q->whereNull('role')->orWhere('role', 'student');
            })
            ->orderByDesc('created_at');

        if ($request->course_id) {
            $query->whereHas('courses', fn($q) => $q->where('courses.id', $request->course_id));
        }

        $students = $query->paginate(20);
        $courses = Course::orderBy('sort_order')->get();

        return view('admin.students.index', compact('students', 'courses'));
    }

    public function create()
    {
        $courses = Course::active()->orderBy('sort_order')->get();
        $courses->load('instructors:id,name,company');
        return view('admin.students.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:100',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'uuid|exists:courses,id',
            'instructor_ids' => 'nullable|array',
            'instructor_ids.*' => 'nullable|uuid|exists:students,id',
        ]);

        $tempPassword = atheneum_temp_password();

        $student = Student::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $tempPassword,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $courseNames = [];
        if (!empty($data['course_ids'])) {
            $attach = [];
            foreach ($data['course_ids'] as $courseId) {
                $requested = $data['instructor_ids'][$courseId] ?? null;
                $attach[$courseId] = [
                    'enrolled_at'   => now(),
                    'is_active'     => true,
                    'instructor_id' => $this->instructorResolver->resolveForCourse($courseId, $requested),
                ];
            }
            $student->courses()->attach($attach);
            $courseNames = Course::whereIn('id', $data['course_ids'])->pluck('name')->toArray();
        }

        try {
            Mail::to($student->email)->send(new StudentWelcomeMail($student, $tempPassword, $courseNames, request()->getSchemeAndHttpHost()));
        } catch (\Throwable $e) {
            session()->flash('warning', 'Studente creato, ma invio email fallito: ' . $e->getMessage());
        }

        return redirect()->route('admin.students.show', $student)
            ->with('success', "Studente {$student->name} creato con successo.");
    }

    public function show(Student $student)
    {
        $student->load([
            'courses.instructors:id,name,company',
            'moduleProgress.module.course',
            'quizAttempts.quiz',
        ]);

        $assignedInstructors = \App\Models\Student::whereIn(
            'id',
            $student->courses->pluck('pivot.instructor_id')->filter()->unique()
        )->get(['id', 'name'])->keyBy('id');

        return view('admin.students.show', compact('student', 'assignedInstructors'));
    }

    public function edit(Student $student)
    {
        $courses = Course::orderBy('sort_order')->get();
        $courses->load('instructors:id,name,company');
        return view('admin.students.edit', compact('student', 'courses'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email,' . $student->id,
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $student->update($data);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Studente aggiornato.');
    }

    public function destroy(Student $student)
    {
        // Soft delete: mette deleted_at, mantiene il record nel DB.
        // Recuperabile dal cestino in admin.students.trashed.
        $name = $student->name;
        $email = $student->email;
        $student->delete();
        \Log::info("Admin soft-delete studente: {$name} ({$email})", [
            'student_id' => $student->id,
            'by_admin_id' => auth()->id(),
        ]);
        return redirect()
            ->route('admin.students.index')
            ->with('success', "Studente {$name} spostato nel cestino. Puoi ripristinarlo o eliminarlo definitivamente da 'Studenti eliminati'.");
    }

    public function trashed()
    {
        $students = Student::onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->paginate(50);
        return view('admin.students.trashed', compact('students'));
    }

    public function restore(string $id)
    {
        $student = Student::onlyTrashed()->findOrFail($id);
        $student->restore();
        \Log::info("Admin restore studente: {$student->name} ({$student->email})", [
            'student_id' => $student->id,
            'by_admin_id' => auth()->id(),
        ]);
        return redirect()
            ->route('admin.students.trashed')
            ->with('success', "Studente {$student->name} ripristinato.");
    }

    public function forceDestroy(string $id)
    {
        $student = Student::onlyTrashed()->findOrFail($id);
        $name = $student->name;
        $email = $student->email;
        $studentId = $student->id;
        // Cascade: tutte le FK figlie hanno cascadeOnDelete (vedi migrations
        // student_course, student_module_progress, student_notes, student_documents,
        // student_canvas_data, exam_attempt_grants, certificates, leads).
        // Quindi forceDelete trigger automatico della cancellazione a cascata.
        $student->forceDelete();
        \Log::warning("Admin HARD DELETE studente: {$name} ({$email})", [
            'student_id' => $studentId,
            'by_admin_id' => auth()->id(),
            'cascade' => 'enrollments, quiz_attempts, certificates, chat_conversations, student_notes, student_documents tutti cancellati per FK cascadeOnDelete',
        ]);
        return redirect()
            ->route('admin.students.trashed')
            ->with('success', "Studente {$name} eliminato definitivamente. Tutti i suoi dati associati sono stati cancellati dal database.");
    }

    public function assignCourse(Request $request, Student $student)
    {
        $data = $request->validate([
            'course_id'     => 'required|uuid|exists:courses,id',
            'instructor_id' => 'nullable|uuid|exists:students,id',
            'expires_at'    => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);

        $instructorId = $this->instructorResolver->resolveForCourse(
            $data['course_id'],
            $data['instructor_id'] ?? null
        );

        $student->courses()->syncWithoutDetaching([
            $data['course_id'] => [
                'enrolled_at'   => now(),
                'expires_at'    => $data['expires_at'] ?? null,
                'is_active'     => true,
                'notes'         => $data['notes'] ?? null,
                'instructor_id' => $instructorId,
            ],
        ]);

        return back()->with('success', 'Corso assegnato.');
    }

    public function removeCourse(Student $student, Course $course)
    {
        $student->courses()->detach($course->id);
        return back()->with('success', 'Corso rimosso.');
    }

    public function updateCourseInstructor(Request $request, Student $student, Course $course)
    {
        $data = $request->validate([
            'instructor_id' => 'nullable|uuid|exists:students,id',
        ]);

        $enrolled = $student->courses()->where('courses.id', $course->id)->exists();
        abort_unless($enrolled, 404, 'Discente non iscritto a questo corso');

        $instructorId = $this->instructorResolver->resolveForCourse(
            $course->id,
            $data['instructor_id'] ?? null
        );

        $student->courses()->updateExistingPivot($course->id, [
            'instructor_id' => $instructorId,
        ]);

        return back()->with('success', 'Formatore aggiornato per il corso.');
    }

    public function sendCredentials(Student $student)
    {
        $tempPassword = atheneum_temp_password();
        $student->update([
            'password' => $tempPassword,
            'must_change_password' => true,
        ]);

        $courseNames = $student->courses()->pluck('courses.name')->toArray();

        try {
            Mail::to($student->email)->send(new StudentWelcomeMail($student, $tempPassword, $courseNames, request()->getSchemeAndHttpHost()));
            return back()->with('success', 'Nuove credenziali inviate.');
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Invio fallito: ' . $e->getMessage()]);
        }
    }

    public function updateSystemRole(Request $request, Student $student)
    {
        $validRoles = array_keys(Student::SYSTEM_ROLES);

        $data = $request->validate([
            'role' => 'nullable|in:' . implode(',', $validRoles),
            'auto_enroll_all_courses' => 'sometimes|boolean',
            'is_instructor' => 'sometimes|boolean',
        ]);

        $newRole = empty($data['role']) ? null : $data['role'];
        $newAutoEnroll = $request->boolean('auto_enroll_all_courses');
        $newIsInstructor = $request->boolean('is_instructor');

        $currentAdminEmail = session('admin_email');
        if ($currentAdminEmail && $student->email === $currentAdminEmail
            && $student->role === 'admin' && $newRole !== 'admin') {
            return back()->with('error',
                'Non puoi declassare te stesso (anti-lockout). Chiedi a un altro admin.');
        }

        $oldRole = $student->role;
        $oldAutoEnroll = $student->auto_enroll_all_courses;

        $oldIsInstructor = (bool) $student->is_instructor;

        $student->update([
            'role' => $newRole,
            'auto_enroll_all_courses' => $newAutoEnroll,
            'is_instructor' => $newIsInstructor,
        ]);

        Log::info('Admin updated student system role', [
            'admin' => $currentAdminEmail ?? 'unknown',
            'student' => $student->email,
            'role_change' => "$oldRole → $newRole",
            'is_instructor_change' => ($oldIsInstructor ? 'true' : 'false') . ' → '
                . ($newIsInstructor ? 'true' : 'false'),
            'auto_enroll_change' => ($oldAutoEnroll ? 'true' : 'false') . ' → '
                . ($newAutoEnroll ? 'true' : 'false'),
        ]);

        $msg = 'Permessi aggiornati.';
        if ($oldRole !== $newRole) {
            $oldLabel = $oldRole ? Student::SYSTEM_ROLES[$oldRole] : 'Studente';
            $newLabel = $newRole ? Student::SYSTEM_ROLES[$newRole] : 'Studente';
            $msg = "Ruolo cambiato: $oldLabel → $newLabel.";
        }

        return redirect()->route('admin.students.show', $student)
            ->with('success', $msg);
    }
}
