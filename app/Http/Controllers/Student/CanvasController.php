<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Material;
use App\Models\Student;
use App\Models\StudentCanvasData;
use Illuminate\Http\Request;

class CanvasController extends Controller
{
    public function getData(Material $material)
    {
        $student = $this->authStudent();
        $this->ensureCanvasMaterial($material);
        $this->ensureEnrolledInMaterial($student, $material);

        $row = StudentCanvasData::where('student_id', $student->id)
            ->where('material_id', $material->id)
            ->first();

        return response()->json([
            'data' => $row?->data ?? new \stdClass(),
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ]);
    }

    public function saveData(Request $request, Material $material)
    {
        $student = $this->authStudent();
        $this->ensureCanvasMaterial($material);

        if ($student->is_demo) {
            return response()->json(['success' => true, 'demo' => true]);
        }

        $this->ensureEnrolledInMaterial($student, $material);

        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $row = StudentCanvasData::updateOrCreate(
            ['student_id' => $student->id, 'material_id' => $material->id],
            ['data' => $validated['data']]
        );

        return response()->json([
            'success' => true,
            'updated_at' => $row->updated_at->toIso8601String(),
        ]);
    }

    private function authStudent(): Student
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);
        return Student::findOrFail($studentId);
    }

    private function ensureCanvasMaterial(Material $material): void
    {
        abort_unless($material->file_type === 'canvas', 404, 'Non è un canvas');
        abort_if($material->is_instructor_only, 403);
    }

    private function ensureEnrolledInMaterial(Student $student, Material $material): void
    {
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
}
