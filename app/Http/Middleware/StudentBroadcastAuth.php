<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;

/**
 * Officina auth è session-based (session('student_id')) e non usa Laravel guards.
 * Il broadcast auth endpoint /broadcasting/auth pero richiede $request->user()
 * non-null per autorizzare i channel privati. Risolviamo qui: leggiamo lo
 * studente dalla session e lo settiamo come user resolver scoped a questa
 * Request. Cosi i channel callbacks in routes/channels.php ricevono lo Student
 * come primo argomento.
 *
 * NB: NON usiamo Auth::setUser perche pollutes l'Auth manager → StartSession
 * salverebbe Auth::id() (UUID) nella colonna sessions.user_id che e' bigint,
 * causando SQL exception. setUserResolver e' scoped al singolo Request.
 */
class StudentBroadcastAuth
{
    public function handle(Request $request, Closure $next)
    {
        $studentId = $request->session()->get('student_id');
        if (!$studentId) {
            abort(401, 'Broadcasting auth requires logged-in student session.');
        }

        $student = Student::find($studentId);
        if (!$student) {
            abort(401, 'Student session valid but record missing.');
        }

        $request->setUserResolver(fn () => $student);

        return $next($request);
    }
}
