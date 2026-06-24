<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Schola\StudentImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Elenco + inserimento singolo studenti della PROPRIA scuola. L'inserimento
// singolo riusa StudentImportService (credenziali duali incluse).
class StudentController extends Controller
{
    use ResolvesSchoolAccess;

    public function index(): View
    {
        $school = $this->currentSchool();

        $students = Student::where('school_id', $school->id)
            ->where('role', 'student')
            ->with(['classEnrollments' => fn ($q) => $q->where('status', 'active')->with('schoolClass:id,name')])
            ->orderBy('name')
            ->get();

        return view('scuola.studenti.index', compact('students'));
    }

    public function create(): View
    {
        $school = $this->currentSchool();
        $classes = SchoolClass::forSchool($school->id)->where('is_archived', false)->orderBy('name')->get(['id', 'name']);

        return view('scuola.studenti.create', compact('classes'));
    }

    public function store(Request $request, StudentImportService $service)
    {
        $school = $this->currentSchool();

        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'cognome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'data_nascita' => 'required|date_format:Y-m-d',
            'class_id' => 'required|uuid',
            'consent' => 'sometimes|boolean',
        ]);

        // La classe deve appartenere alla scuola (tenancy).
        $class = SchoolClass::where('id', $data['class_id'])->where('school_id', $school->id)->first();
        abort_unless($class, 403, 'Classe non appartenente alla tua scuola.');

        $out = $service->commitSingle([
            'nome' => $data['nome'], 'cognome' => $data['cognome'],
            'email' => $data['email'] ?? '', 'birth_date' => $data['data_nascita'],
            'classe' => $class->name, 'consent' => $request->boolean('consent'),
        ], $school);

        $row = $out['row'] ?? null;
        $result = $out['result'] ?? [];
        $status = $row['status'] ?? 'error';

        if ($status === 'conflict') {
            return back()->with('error', "Studente non aggiunto: l'email appartiene già a un account di un'altra scuola.");
        }
        if ($status === 'error') {
            return back()->with('error', 'Studente non aggiunto: ' . ($row['message'] ?? 'dati non validi.'));
        }

        $redirect = redirect()->route('scuola.studenti.index');

        if ($status === 'attach') {
            return $redirect->with('success', 'Studente agganciato all\'account esistente e iscritto (corsi preservati).');
        }

        // Credenziali generate (studente senza email): mostrate UNA volta.
        if (!empty($result['generated'])) {
            $redirect->with('single_credentials', $result['generated']);
        }
        if (($result['updated'] ?? 0) > 0) {
            return $redirect->with('success', 'Studente già presente: iscrizione/dati aggiornati.');
        }
        if (!empty($result['generated'])) {
            return $redirect->with('success', 'Studente aggiunto. Credenziali generate qui sotto (annotale, una sola volta).');
        }

        return $redirect->with('success', 'Studente aggiunto. Invito inviato via email.');
    }
}
