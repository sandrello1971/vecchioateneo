<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Schola\TeacherClassAccess;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function __construct(private TeacherClassAccess $access) {}

    /** Id del docente loggato (garantito professor dal middleware). */
    private function teacherId(): string
    {
        return session('student_id');
    }

    /** Accesso in lettura/operativo: classe libera propria O cattedra (P15). */
    private function authorizeView(SchoolClass $class): void
    {
        abort_unless($this->access->canTeach($this->teacherId(), $class), 403);
    }

    /** Gestione (modifica/codice/roster): SOLO classi libere proprie. Le classi
     *  di scuola sono gestite dalla segreteria (§3 confine). */
    private function authorizeManage(SchoolClass $class): void
    {
        abort_unless($this->access->canManage($this->teacherId(), $class), 403);
    }

    public function index()
    {
        $classes = $this->access->classesQuery($this->teacherId())
            ->with('subject')
            ->withCount([
                'classStudents as active_count' => fn ($q) => $q->where('status', 'active'),
                'classStudents as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->orderByDesc('created_at')
            ->get();

        $subjects = Subject::orderBy('name')->get();
        $canCreate = $this->access->canCreateClasses(Student::findOrFail($this->teacherId()));

        return view('docente.classi.index', compact('classes', 'subjects', 'canCreate'));
    }

    public function store(Request $request)
    {
        abort_unless($this->access->canCreateClasses(Student::findOrFail($this->teacherId())), 403,
            'La creazione delle classi è gestita dalla segreteria.');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'school_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'requires_approval' => 'sometimes|boolean',
        ], [
            'school_year.regex' => 'Anno scolastico nel formato AAAA/AAAA (es. 2026/2027).',
        ]);

        $class = SchoolClass::create([
            'teacher_id' => $this->teacherId(),
            'name' => $data['name'],
            'subject_id' => $data['subject_id'],
            'school_year' => $data['school_year'],
            'requires_approval' => $request->boolean('requires_approval'),
            'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true,
            'is_archived' => false,
        ]);

        return redirect()->route('docente.classes.show', $class)
            ->with('success', 'Classe creata. Condividi il codice invito con gli studenti.');
    }

    public function show(SchoolClass $class)
    {
        $this->authorizeView($class);

        $class->load(['subject', 'classStudents.student']);

        $roster = $class->classStudents->groupBy('status');
        $openQuestionsCount = app(\App\Services\Schola\ClassSignalsService::class)->openQuestionsCount($class);

        return view('docente.classi.show', compact('class', 'roster', 'openQuestionsCount'));
    }

    public function update(Request $request, SchoolClass $class)
    {
        $this->authorizeManage($class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'requires_approval' => 'sometimes|boolean',
            'invite_enabled' => 'sometimes|boolean',
            'is_archived' => 'sometimes|boolean',
        ]);

        $class->update([
            'name' => $data['name'],
            'requires_approval' => $request->boolean('requires_approval'),
            'invite_enabled' => $request->boolean('invite_enabled'),
            'is_archived' => $request->boolean('is_archived'),
        ]);

        return redirect()->route('docente.classes.show', $class)
            ->with('success', 'Classe aggiornata.');
    }

    public function regenerateCode(SchoolClass $class)
    {
        $this->authorizeManage($class);

        $class->update(['invite_code' => SchoolClass::generateInviteCode()]);

        return redirect()->route('docente.classes.show', $class)
            ->with('success', 'Nuovo codice invito generato. Il precedente non è più valido.');
    }
}
