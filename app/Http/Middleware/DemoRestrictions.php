<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoRestrictions
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Student::find(session('student_id'));
        if ($student && $student->is_demo) {
            if ($request->routeIs('student.material.download') ||
                $request->routeIs('student.material.canvas') ||
                $request->routeIs('student.module.document.download') ||
                $request->routeIs('student.course.document.download') ||
                $request->is('storage/materials/*') ||
                $request->is('learn/certificate/*')) {
                return response()->json(['error' => 'Funzione non disponibile in modalità demo'], 403);
            }
        }
        return $next($request);
    }
}
