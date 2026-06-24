<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// Flusso "Unisciti a una classe". Pubblico: gestisce sia lo studente già loggato
// (solo codice) sia la registrazione di un nuovo studente via codice classe
// (birth_date obbligatoria). Messaggi di errore NON enumerabili: stesso messaggio
// per codice inesistente, disattivato o classe archiviata.
class ClassJoinController extends Controller
{
    private const GENERIC_CODE_ERROR = 'Codice non valido o non più attivo.';

    public function create()
    {
        $loggedIn = (bool) session('student_id');
        return view('student.classi.join', compact('loggedIn'));
    }

    public function store(Request $request)
    {
        $request->validate(['invite_code' => 'required|string|max:16']);
        $code = strtoupper(trim($request->input('invite_code')));

        $class = SchoolClass::where('invite_code', $code)
            ->where('invite_enabled', true)
            ->where('is_archived', false)
            ->first();

        if (!$class) {
            return back()->withErrors(['invite_code' => self::GENERIC_CODE_ERROR])->withInput();
        }

        $studentId = session('student_id');

        // Guest → registrazione con codice (birth_date obbligatoria qui).
        if (!$studentId) {
            $reg = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:students,email',
                'password' => 'required|string|min:8|confirmed',
                'birth_date' => 'required|date|before:today',
            ], [
                'birth_date.required' => 'La data di nascita è obbligatoria per iscriversi a una classe.',
            ]);

            $student = Student::create([
                'name' => $reg['name'],
                'email' => strtolower($reg['email']),
                'password' => Hash::make($reg['password']),
                'role' => 'student',
                'birth_date' => $reg['birth_date'],
                'is_active' => true,
                'must_change_password' => false,
            ]);

            session([
                'student_id' => $student->id,
                'student_name' => $student->name,
                'student_email' => $student->email,
            ]);
            $studentId = $student->id;
        }

        return $this->enroll($class, $studentId);
    }

    private function enroll(SchoolClass $class, string $studentId)
    {
        $enrollment = ClassStudent::where('school_class_id', $class->id)
            ->where('student_id', $studentId)
            ->first();

        if ($enrollment && in_array($enrollment->status, ['pending', 'active'], true)) {
            return redirect()->route('student.classes.index')
                ->with('success', $enrollment->status === 'active'
                    ? 'Sei già iscritto a questa classe.'
                    : 'La tua richiesta è già in attesa di approvazione.');
        }

        $status = $class->requires_approval ? 'pending' : 'active';
        $payload = ['status' => $status, 'approved_at' => $status === 'active' ? now() : null];

        if ($enrollment) {
            $enrollment->update($payload);
        } else {
            ClassStudent::create(array_merge([
                'school_class_id' => $class->id,
                'student_id' => $studentId,
            ], $payload));
        }

        $msg = $status === 'active'
            ? "Iscrizione completata alla classe «{$class->name}»."
            : "Richiesta inviata: l'iscrizione alla classe «{$class->name}» è in attesa di approvazione dal docente.";

        return redirect()->route('student.classes.index')->with('success', $msg);
    }
}
