<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentCourse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'totalStudents' => Student::count(),
            'activeStudents' => Student::where('is_active', true)->count(),
            'totalCourses' => Course::count(),
            'enrollments' => StudentCourse::where('is_active', true)->count(),
            'quizAttempts' => QuizAttempt::whereNotNull('completed_at')->count(),
            'passedAttempts' => QuizAttempt::where('passed', true)->count(),
            'recentStudents' => Student::latest()->limit(5)->get(),
            'courses' => Course::withCount('modules')->orderBy('sort_order')->get(),
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $path = $request->file('image')->store('module-images', 'public');
        return response()->json(['url' => '/storage/' . $path]);
    }
}
