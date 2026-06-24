<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('student_id')) {
            $student = Student::find(session('student_id'));
            if ($student && $student->isSchoolAdmin()) {
                return redirect()->route('scuola.dashboard');
            }
            if ($student && $student->isProfessor()) {
                return redirect()->route('docente.dashboard');
            }
            return redirect()->route('student.dashboard');
        }
        return view('student.auth.login');
    }

    public function login(Request $request)
    {
        // Login DUALE (§8.1): il campo accetta email OPPURE username (studenti
        // di scuola senza email). Niente più vincolo |email per non escludere
        // gli username.
        $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ], [
            'email.required' => 'Inserisci email o username.',
            'password.required' => 'Inserisci la password.',
        ]);

        $login = trim($request->input('email'));
        $student = Student::where('is_active', true)
            ->where(function ($q) use ($login) {
                $q->whereRaw('LOWER(email) = ?', [mb_strtolower($login)])
                  ->orWhereRaw('LOWER(username) = ?', [mb_strtolower($login)]);
            })
            ->first();

        if (!$student || !Hash::check($request->password, $student->password)) {
            \Illuminate\Support\Facades\Log::warning('[student] login failed', [
                'email_attempted' => $request->input('email'),
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 200),
            ]);
            return back()->withErrors(['email' => 'Credenziali non valide.'])->withInput();
        }

        session([
            'student_id' => $student->id,
            'student_name' => $student->name,
            'student_email' => $student->email,
        ]);

        $student->update(['last_login_at' => now()]);

        \Illuminate\Support\Facades\Log::info('[student] login success', [
            'student_id' => $student->id,
            'email' => $student->email,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 200),
        ]);

        if ($student->must_change_password) {
            return redirect()->route('student.change-password');
        }

        // Segreteria scolastica → area dedicata /scuola
        if ($student->isSchoolAdmin()) {
            return redirect()->route('scuola.dashboard');
        }

        // Docente Schola → area dedicata /docente
        if ($student->isProfessor()) {
            return redirect()->route('docente.dashboard');
        }

        return redirect()->route('student.dashboard');
    }

    public function logout(Request $request)
    {
        $studentId = session('student_id');
        $studentEmail = session('student_email');
        session()->forget(['student_id', 'student_name', 'student_email']);
        if ($studentId) {
            \Illuminate\Support\Facades\Log::info('[student] logout', [
                'student_id' => $studentId,
                'email' => $studentEmail,
                'ip' => $request->ip(),
            ]);
        }
        return redirect()->route('student.login');
    }

    public function showChangePassword()
    {
        return view('student.auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ], [
            'password.required' => 'Inserisci la nuova password.',
            'password.confirmed' => 'Le password non coincidono.',
        ]);

        $student = Student::findOrFail(session('student_id'));
        $student->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        \Illuminate\Support\Facades\Log::info('[student] password changed', [
            'student_id' => $student->id,
            'email' => $student->email,
            'ip' => $request->ip(),
        ]);

        if ($student->isSchoolAdmin()) {
            return redirect()->route('scuola.dashboard')->with('success', 'Password aggiornata!');
        }
        if ($student->isProfessor()) {
            return redirect()->route('docente.dashboard')->with('success', 'Password aggiornata!');
        }

        return redirect()->route('student.dashboard')
            ->with('success', 'Password aggiornata!');
    }
}
