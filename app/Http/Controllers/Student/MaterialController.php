<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    public function download(Material $material)
    {
        $this->authorizeAccess($material);
        $this->ensureFileExists($material);

        return response()->download(
            Storage::disk('local')->path($material->file_path),
            basename($material->file_path)
        );
    }

    public function canvas(Material $material)
    {
        $this->authorizeAccess($material);
        abort_unless($material->file_type === 'canvas', 404);
        $this->ensureFileExists($material);

        return response()->file(
            Storage::disk('local')->path($material->file_path),
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    private function authorizeAccess(Material $material): void
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $student = Student::findOrFail($studentId);

        // Demo bloccato anche dal middleware DemoRestrictions; check applicativo
        // mantenuto come difesa in profondità indipendente dalla configurazione middleware.
        if ($student->is_demo) {
            abort(403, 'Funzione non disponibile in modalità demo');
        }

        // I materiali instructor-only sono serviti da InstructorMaterialController, non da qui.
        abort_if($material->is_instructor_only, 403);

        $courseId = $material->course_id ?? $material->module?->course_id;
        abort_unless($courseId, 404);

        if ($student->auto_enroll_all_courses) {
            abort_unless(
                Course::where('id', $courseId)->where('is_active', true)->exists(),
                403
            );
            return;
        }

        abort_unless(
            $student->courses()
                ->wherePivot('is_active', true)
                ->where('courses.id', $courseId)
                ->exists(),
            403
        );
    }

    private function ensureFileExists(Material $material): void
    {
        abort_unless(
            $material->file_path && Storage::disk('local')->exists($material->file_path),
            404
        );
    }
}
