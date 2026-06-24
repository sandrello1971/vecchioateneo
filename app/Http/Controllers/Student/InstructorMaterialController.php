<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\Material;
use App\Models\Student;
use App\Services\InstructorManualSplitterService;
use Illuminate\Support\Facades\Storage;

class InstructorMaterialController extends Controller
{
    use DeterminesTeachingMode;

    public function show(Course $course, Material $material, InstructorManualSplitterService $splitter)
    {
        $this->authorizeAccess($course, $material);

        $material->content_html = $splitter->injectAnchorsIntoMainHtml($material);

        $student = Student::findOrFail(session('student_id'));

        $sectionsWithNotes = [];
        if ($student->isInstructor()) {
            $counts = \App\Models\InstructorNote::visibleTo($student->id)
                ->where('course_id', $course->id)
                ->whereNotNull('instructor_manual_section_id')
                ->selectRaw('instructor_manual_section_id, COUNT(*) as n')
                ->groupBy('instructor_manual_section_id')
                ->get();
            foreach ($counts as $c) {
                $sectionsWithNotes[$c->instructor_manual_section_id] = $c->n;
            }
        }

        return view('student.instructor.material', [
            'course' => $course,
            'material' => $material,
            'sectionsWithNotes' => $sectionsWithNotes,
        ]);
    }

    public function download(Course $course, Material $material)
    {
        $this->authorizeAccess($course, $material);

        if (!$material->file_path || !Storage::disk('local')->exists($material->file_path)) {
            abort(404, 'File non trovato');
        }

        return response()->download(
            Storage::disk('local')->path($material->file_path),
            ($material->title ?? 'manuale') . '.docx'
        );
    }

    private function authorizeAccess(Course $course, Material $material): void
    {
        $student = Student::findOrFail(session('student_id'));

        if (!$student->isInstructor()) {
            abort(403, 'Accesso riservato ai docenti.');
        }

        $enrolled = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (!$enrolled
            && !$student->auto_enroll_all_courses
            && !$this->teaches($student, $course)) {
            abort(403, 'Non insegni questo corso.');
        }

        if (!$material->is_instructor_only) {
            abort(404);
        }

        if ($material->course_id !== $course->id) {
            abort(404);
        }
    }
}
