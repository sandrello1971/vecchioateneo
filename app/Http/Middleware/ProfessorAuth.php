<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Gate area docente Schola. Simmetrico a StudentAuth/AdminAuth (auth via
// sessione student_id), ma richiede role='professor'. NON concede né eredita
// gli accessi instructor (formatore corsi): i due ruoli restano distinti.
class ProfessorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $studentId = session('student_id');
        if (!$studentId) {
            return redirect()->route('student.login');
        }

        $student = Student::find($studentId);
        if (!$student || !$student->isProfessor()) {
            abort(403, 'Area riservata ai docenti Schola.');
        }

        return $next($request);
    }
}
