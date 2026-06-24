<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\ClassStudent;
use App\Models\ProfessorSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Gestione classi della scuola (segreteria): CRUD, roster, cattedre. La classe
// appartiene alla SCUOLA. Tutto scoped su school_id. Il roster è competenza
// ESCLUSIVA della segreteria (§3): il docente lo vede in sola lettura.
class ClassController extends Controller
{
    use ResolvesSchoolAccess;

    public function index()
    {
        $school = $this->currentSchool();

        $classes = SchoolClass::forSchool($school->id)
            ->with('coordinator:id,name')
            ->withCount([
                'classStudents as active_count' => fn ($q) => $q->where('status', 'active'),
                'teachingAssignments as cattedre_count',
            ])
            ->orderBy('name')
            ->get();

        return view('scuola.classi.index', compact('classes'));
    }

    public function create()
    {
        $school = $this->currentSchool();
        return view('scuola.classi.create', ['teachers' => $this->teachers($school->id)]);
    }

    public function store(Request $request)
    {
        $school = $this->currentSchool();
        $data = $this->validateClass($request, $school->id);

        $class = SchoolClass::create([
            'school_id' => $school->id,
            'teacher_id' => $data['coordinator_id'] ?? null,
            'name' => $data['name'],
            'subject_id' => null,
            'school_year' => $data['school_year'],
            'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false,
            'requires_approval' => false,
            'is_archived' => false,
        ]);

        return redirect()->route('scuola.classi.show', $class)->with('success', 'Classe creata.');
    }

    public function show(SchoolClass $class)
    {
        $this->assertSameSchool($class);
        $school = $this->currentSchool();

        $class->load([
            'coordinator:id,name',
            'classStudents' => fn ($q) => $q->where('status', 'active')->with('student:id,name,email,username'),
            'teachingAssignments.teacher:id,name',
            'teachingAssignments.subject:id,name',
        ]);

        $enrolledIds = $class->classStudents->pluck('student_id')->all();
        $availableStudents = Student::where('school_id', $school->id)->where('role', 'student')
            ->whereNotIn('id', $enrolledIds)->orderBy('name')->get(['id', 'name']);

        return view('scuola.classi.show', [
            'class' => $class,
            'teachers' => $this->teachers($school->id),
            'subjects' => Subject::orderBy('name')->get(),
            'availableStudents' => $availableStudents,
        ]);
    }

    public function update(Request $request, SchoolClass $class)
    {
        $this->assertSameSchool($class);
        $data = $this->validateClass($request, $class->school_id);

        $class->update([
            'name' => $data['name'],
            'school_year' => $data['school_year'],
            'teacher_id' => $data['coordinator_id'] ?? null,
            'is_archived' => $request->boolean('is_archived'),
        ]);

        return redirect()->route('scuola.classi.show', $class)->with('success', 'Classe aggiornata.');
    }

    // ===== roster (solo segreteria) =====

    public function assignStudents(Request $request, SchoolClass $class)
    {
        $this->assertSameSchool($class);
        $school = $this->currentSchool();

        $data = $request->validate([
            'action' => 'required|in:add,remove',
            'student_id' => 'required|uuid',
        ]);

        // Lo studente deve appartenere alla scuola.
        $student = Student::where('id', $data['student_id'])->where('school_id', $school->id)
            ->where('role', 'student')->first();
        abort_unless($student, 403, 'Studente non appartenente alla tua scuola.');

        if ($data['action'] === 'add') {
            ClassStudent::updateOrCreate(
                ['school_class_id' => $class->id, 'student_id' => $student->id],
                ['status' => 'active', 'approved_at' => now()]
            );
            $msg = 'Studente aggiunto alla classe.';
        } else {
            ClassStudent::where('school_class_id', $class->id)->where('student_id', $student->id)->delete();
            $msg = 'Studente rimosso dalla classe.';
        }

        return redirect()->route('scuola.classi.show', $class)->with('success', $msg);
    }

    // ===== cattedre =====

    public function assignCattedra(Request $request, SchoolClass $class)
    {
        $this->assertSameSchool($class);
        $school = $this->currentSchool();

        $data = $request->validate([
            'teacher_id' => 'required|uuid',
            'subject_id' => 'required|uuid|exists:subjects,id',
        ]);

        $teacher = Student::where('id', $data['teacher_id'])->where('school_id', $school->id)
            ->where('role', 'professor')->first();
        abort_unless($teacher, 403, 'Docente non appartenente alla tua scuola.');

        TeachingAssignment::firstOrCreate([
            'teacher_id' => $teacher->id,
            'subject_id' => $data['subject_id'],
            'school_class_id' => $class->id,
            'school_year' => $class->school_year,
        ], ['school_id' => $school->id]);

        $redirect = redirect()->route('scuola.classi.show', $class)->with('success', 'Cattedra assegnata.');

        // Avviso (non blocco) se la materia è fuori dalle competenze del docente.
        $covered = ProfessorSubject::where('teacher_id', $teacher->id)
            ->where('subject_id', $data['subject_id'])->where('school_id', $school->id)->exists();
        if (!$covered) {
            $redirect->with('warning', 'Nota: la materia non è tra le competenze dichiarate del docente.');
        }

        return $redirect;
    }

    public function destroyCattedra(TeachingAssignment $assignment)
    {
        $this->assertSameSchool($assignment);
        $class = $assignment->schoolClass;
        $assignment->delete();

        return redirect()->route('scuola.classi.show', $class)->with('success', 'Cattedra rimossa.');
    }

    // ===== helper =====

    private function validateClass(Request $request, string $schoolId): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'school_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'coordinator_id' => ['nullable', 'uuid', Rule::exists('students', 'id')->where('school_id', $schoolId)->where('role', 'professor')],
            'is_archived' => 'sometimes|boolean',
        ], ['school_year.regex' => 'Anno scolastico nel formato AAAA/AAAA (es. 2026/2027).']);
    }

    private function teachers(string $schoolId)
    {
        return Student::where('school_id', $schoolId)->where('role', 'professor')->orderBy('name')->get(['id', 'name']);
    }
}
