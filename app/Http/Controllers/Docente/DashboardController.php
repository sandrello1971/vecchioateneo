<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Schola\ClassSignalsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private ClassSignalsService $signals) {}

    public function index(): View
    {
        $teacher = Student::findOrFail(session('student_id'));

        return view('docente.dashboard', $this->signals->teacherDashboard($teacher));
    }
}
