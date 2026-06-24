<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Gate area segreteria /scuola (fase 2). Simmetrico a ProfessorAuth: auth via
// sessione student_id, ma richiede role='school_admin' CON school_id valorizzato
// (la tenancy è inutile senza una scuola). NON concede /docente né /learn.
class SchoolAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $studentId = session('student_id');
        if (!$studentId) {
            return redirect()->route('student.login');
        }

        $student = Student::find($studentId);
        if (!$student || !$student->isSchoolAdmin() || !$student->school_id) {
            abort(403, 'Area riservata alla segreteria scolastica.');
        }

        return $next($request);
    }
}
