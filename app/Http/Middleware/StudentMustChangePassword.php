<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentMustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('student_id')) {
            $student = Student::find(session('student_id'));
            if ($student && $student->must_change_password
                && !$request->is('learn/change-password*')
                && !$request->is('learn/logout')) {
                return redirect()->route('student.change-password');
            }
        }

        return $next($request);
    }
}
