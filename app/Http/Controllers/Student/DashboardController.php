<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use App\Support\StudentCourseAccess;

class DashboardController extends Controller
{
    public function __construct(private StudentCourseAccess $courseAccess)
    {
    }

    public function index()
    {
        $student = Student::findOrFail(session('student_id'));

        // Schola: classi a cui lo studente è iscritto (escluse le rimosse).
        $myClasses = $student->schoolClasses()
            ->with('subject')
            ->wherePivot('status', '!=', 'removed')
            ->orderBy('name')
            ->get();

        $courses = $this->courseAccess->navigableCourses($student)
            ->loadMissing('modules')
            ->map(function ($course) use ($student) {
                $totalModules = $course->modules->count();
                $course->modules_total = $totalModules;
                $course->is_teaching = ($course->access_kind ?? 'enrolled') === 'teaching';

                if ($course->is_teaching) {
                    $course->progress_pct = null;
                    $course->modules_done = null;
                    return $course;
                }

                $completedModules = StudentModuleProgress::where('student_id', $student->id)
                    ->whereIn('module_id', $course->modules->pluck('id'))
                    ->where('status', 'completed')
                    ->count();
                $course->progress_pct = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;
                $course->modules_done = $completedModules;
                return $course;
            });

        // Statistiche aggregate calcolate SOLO sulle iscrizioni (un formatore non
        // "completa" i corsi che insegna).
        $enrolledCourses = $courses->where('is_teaching', false);

        $modulesCompleted = StudentModuleProgress::where('student_id', $student->id)
            ->where('status', 'completed')
            ->count();
        $quizzesPassed = QuizAttempt::where('student_id', $student->id)
            ->where('passed', true)
            ->count();
        $overallProgress = $enrolledCourses->avg('progress_pct') ?? 0;

        $stats = [
            'courses' => $courses->count(),
            'modules_completed' => $modulesCompleted,
            'quizzes_passed' => $quizzesPassed,
            'overall_progress' => round($overallProgress),
        ];

        $lastProgress = StudentModuleProgress::where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->with('module.course')
            ->latest('updated_at')
            ->first();

        $lastModule = null;
        if ($lastProgress && $lastProgress->module && $lastProgress->module->course) {
            $lastModule = [
                'module_id' => $lastProgress->module_id,
                'title' => $lastProgress->module->title,
                'course_slug' => $lastProgress->module->course->slug,
            ];
        }

        return view('student.dashboard', compact('student', 'courses', 'stats', 'lastModule', 'myClasses'));
    }
}
