<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function start(Request $request)
    {
        $demo = $this->resolveDemoStudent();
        if (!$demo) {
            return redirect('/')->with('error', 'Demo non disponibile al momento.');
        }

        session([
            'student_id' => $demo->id,
            'student_name' => 'Visitatore Demo',
            'student_email' => 'demo@atheneum.noscite.it',
            'demo_mode' => true,
        ]);

        return redirect()->route('student.dashboard');
    }

    private function resolveDemoStudent(): ?Student
    {
        $demo = Student::where('is_demo', true)->where('is_active', true)->first();
        if (!$demo) return null;

        $primus = Course::where('slug', 'primus')->first();
        if ($primus && !$demo->courses()->where('courses.id', $primus->id)->exists()) {
            $demo->courses()->attach($primus->id, [
                'enrolled_at' => now(),
                'is_active' => true,
            ]);
        }

        return $demo;
    }
}
