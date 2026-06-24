<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Mail\TeacherInviteMail;
use App\Models\ProfessorSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Schola\TeacherImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

// Elenco + inserimento singolo docenti della PROPRIA scuola (tenancy via
// ResolvesSchoolAccess). L'inserimento singolo riusa TeacherImportService.
class TeacherController extends Controller
{
    use ResolvesSchoolAccess;

    public function index(): View
    {
        $school = $this->currentSchool();

        $teachers = Student::where('school_id', $school->id)
            ->where('role', 'professor')
            ->with('teachableSubjects:id,name')
            ->orderBy('name')
            ->get();

        return view('scuola.docenti.index', compact('teachers'));
    }

    public function create(): View
    {
        $this->currentSchool();
        return view('scuola.docenti.create', ['subjects' => Subject::orderBy('name')->get()]);
    }

    public function store(Request $request, TeacherImportService $service)
    {
        $school = $this->currentSchool();

        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'cognome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'materie' => 'sometimes|array',
            'materie.*' => 'uuid|exists:subjects,id',
        ]);

        $subjectNames = Subject::whereIn('id', $data['materie'] ?? [])->pluck('name')->all();

        $out = $service->commitSingle([
            'nome' => $data['nome'], 'cognome' => $data['cognome'],
            'email' => $data['email'], 'materie' => $subjectNames,
        ], $school);

        return $this->feedback($out, route('scuola.docenti.index'), 'Docente');
    }

    public function edit(Student $teacher): View
    {
        $teacher = $this->ownTeacher($teacher);

        return view('scuola.docenti.edit', [
            'teacher'  => $teacher,
            'subjects' => Subject::orderBy('name')->get(),
            'selected' => $teacher->teachableSubjects->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Student $teacher)
    {
        $school  = $this->currentSchool();
        $teacher = $this->ownTeacher($teacher);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:students,email,' . $teacher->id,
            'materie'   => 'sometimes|array',
            'materie.*' => 'uuid|exists:subjects,id',
        ]);

        $teacher->update([
            'name'  => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
        ]);

        // Sync materie insegnabili (pivot professor_subjects, scoped sulla scuola).
        $selected = array_values(array_unique($data['materie'] ?? []));
        ProfessorSubject::where('teacher_id', $teacher->id)
            ->whereNotIn('subject_id', $selected)
            ->delete();
        foreach ($selected as $subjectId) {
            ProfessorSubject::firstOrCreate([
                'teacher_id' => $teacher->id,
                'subject_id' => $subjectId,
                'school_id'  => $school->id,
            ]);
        }

        return redirect()->route('scuola.docenti.index')->with('success', 'Docente aggiornato.');
    }

    /** Reimposta la password e reinvia l'invito (riusa TeacherInviteMail). */
    public function resetPassword(Student $teacher)
    {
        $school  = $this->currentSchool();
        $teacher = $this->ownTeacher($teacher);

        $temp = 'Nsc' . now()->format('y') . '!' . Str::upper(Str::random(5));
        $teacher->update([
            'password'             => $temp,
            'must_change_password' => true,
            'is_active'            => true,
        ]);

        try {
            Mail::to($teacher->email)->queue(new TeacherInviteMail($teacher, $temp, $school));
        } catch (\Throwable $e) {
            return back()->with('error', 'Password reimpostata, ma invio email fallito: ' . $e->getMessage());
        }

        return back()->with('success', "Password reimpostata e invito reinviato a {$teacher->email}.");
    }

    /** Attiva/disattiva l'accesso del docente (resta nella scuola). */
    public function toggleActive(Student $teacher)
    {
        $this->currentSchool();
        $teacher = $this->ownTeacher($teacher);

        $teacher->update(['is_active' => ! $teacher->is_active]);

        return back()->with('success', $teacher->is_active ? 'Docente riattivato.' : 'Docente disattivato.');
    }

    /** Tenancy: il docente deve essere un professore della PROPRIA scuola. */
    private function ownTeacher(Student $teacher): Student
    {
        $this->assertSameSchool($teacher);
        abort_unless($teacher->role === 'professor', 404, 'Non è un docente di questa scuola.');

        return $teacher;
    }

    /** Traduce l'esito del commit singolo in messaggi UX coerenti. */
    private function feedback(array $out, string $back, string $label)
    {
        $row = $out['row'] ?? null;
        $result = $out['result'] ?? [];
        $status = $row['status'] ?? 'error';

        if ($status === 'conflict') {
            return redirect()->back()->with('error', "$label non aggiunto: l'email appartiene già a un account di un'altra scuola.");
        }
        if ($status === 'error') {
            return redirect()->back()->with('error', "$label non aggiunto: " . ($row['message'] ?? 'dati non validi.'));
        }
        if ($status === 'attach') {
            return redirect($back)->with('success', "$label agganciato all'account esistente (eventuali corsi/ruoli preservati).");
        }
        if (($result['updated'] ?? 0) > 0) {
            return redirect($back)->with('success', "$label già presente: aggiornato.");
        }

        return redirect($back)->with('success', "$label aggiunto. Invito inviato via email.");
    }
}
