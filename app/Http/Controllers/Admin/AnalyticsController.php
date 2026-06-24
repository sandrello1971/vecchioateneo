<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AnalyticsController extends Controller
{
    public function index()
    {
        $inactiveStudents = Student::where('is_active', true)
            ->where(function ($q) {
                $q->where('last_login_at', '<', now()->subDays(7))
                  ->orWhereNull('last_login_at');
            })
            ->orderBy('last_login_at', 'asc')
            ->get();

        $courseStats = Course::with(['modules'])->orderBy('sort_order')->get()->map(function ($course) {
            $totalStudents = DB::table('student_course')
                ->where('course_id', $course->id)
                ->where('is_active', true)->count();

            $moduleIds = $course->modules->pluck('id');
            $totalModulesCount = $course->modules->count();

            $completedStudents = 0;
            if ($totalModulesCount > 0) {
                $completedStudents = DB::table('student_course as sc')
                    ->where('sc.course_id', $course->id)
                    ->where('sc.is_active', true)
                    ->whereIn('sc.student_id', function ($q) use ($moduleIds, $totalModulesCount) {
                        $q->select('student_id')
                          ->from('student_module_progress')
                          ->whereIn('module_id', $moduleIds)
                          ->where('status', 'completed')
                          ->groupBy('student_id')
                          ->havingRaw('COUNT(*) >= ?', [$totalModulesCount]);
                    })->count();
            }

            $quizStats = QuizAttempt::whereHas('quiz', fn($q) => $q->where('course_id', $course->id))
                ->whereNotNull('completed_at')
                ->selectRaw('AVG(score) as avg_score, COUNT(*) as total, SUM(CASE WHEN passed THEN 1 ELSE 0 END) as passed')
                ->first();

            $course->total_students = $totalStudents;
            $course->completed_students = $completedStudents;
            $course->avg_score = round($quizStats->avg_score ?? 0);
            $course->quiz_attempts = $quizStats->total ?? 0;
            $course->quiz_passed = $quizStats->passed ?? 0;
            return $course;
        });

        $topStudents = Student::with('courses')
            ->where('is_active', true)
            ->get()
            ->map(function ($student) {
                $completed = StudentModuleProgress::where('student_id', $student->id)
                    ->where('status', 'completed')->count();
                $quizPassed = QuizAttempt::where('student_id', $student->id)
                    ->where('passed', true)->count();
                $student->modules_completed = $completed;
                $student->quizzes_passed = $quizPassed;
                return $student;
            })
            ->sortByDesc('modules_completed')
            ->take(10);

        $hardestQuizzes = QuizAttempt::whereNotNull('completed_at')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->selectRaw('quizzes.title, AVG(quiz_attempts.score) as avg_score, COUNT(*) as attempts, SUM(CASE WHEN quiz_attempts.passed THEN 1 ELSE 0 END) as passed_count')
            ->groupBy('quizzes.id', 'quizzes.title')
            ->having(DB::raw('COUNT(*)'), '>=', 1)
            ->orderBy('avg_score', 'asc')
            ->limit(5)
            ->get();

        $dailyActivity = StudentModuleProgress::where('updated_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.analytics', compact(
            'inactiveStudents', 'courseStats', 'topStudents',
            'hardestQuizzes', 'dailyActivity'
        ));
    }

    public function sendReminder(Student $student)
    {
        try {
            Mail::to($student->email)->send(new \App\Mail\StudentReminderMail($student, request()->getSchemeAndHttpHost()));
            return back()->with('success', "Reminder inviato a {$student->name}");
        } catch (\Exception $e) {
            return back()->with('error', 'Errore invio: ' . $e->getMessage());
        }
    }

    public function sendReminders()
    {
        $inactive = Student::where('is_active', true)
            ->where(function ($q) {
                $q->where('last_login_at', '<', now()->subDays(7))
                  ->orWhereNull('last_login_at');
            })->get();

        $baseUrl = request()->getSchemeAndHttpHost();

        $sent = 0;
        foreach ($inactive as $student) {
            try {
                Mail::to($student->email)->send(new \App\Mail\StudentReminderMail($student, $baseUrl));
                $sent++;
            } catch (\Exception $e) {
                Log::error("Reminder fallito per {$student->email}: " . $e->getMessage());
            }
        }
        return back()->with('success', "Reminder inviati a {$sent} studenti");
    }
}
