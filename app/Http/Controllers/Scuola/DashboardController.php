<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\ImportBatch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\View\View;

// Dashboard segreteria: riepilogo della PROPRIA scuola. Tutto scoped su
// school_id via ResolvesSchoolAccess (§2 isolamento non negoziabile).
class DashboardController extends Controller
{
    use ResolvesSchoolAccess;

    public function index(): View
    {
        $school = $this->currentSchool();
        $sid = $school->id;

        $counts = [
            'teachers' => Student::where('school_id', $sid)->where('role', 'professor')->count(),
            'students' => Student::where('school_id', $sid)->where('role', 'student')->count(),
            'classes' => SchoolClass::forSchool($sid)->count(),
            'assignments' => TeachingAssignment::forSchool($sid)->count(),
        ];

        $recentImports = ImportBatch::forSchool($sid)
            ->with('creator:id,name')
            ->latest()
            ->limit(5)
            ->get();

        return view('scuola.dashboard', compact('school', 'counts', 'recentImports'));
    }
}
