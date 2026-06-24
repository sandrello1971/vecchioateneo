<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('student_id')) {
            return redirect()->route('student.login');
        }

        return $next($request);
    }
}
