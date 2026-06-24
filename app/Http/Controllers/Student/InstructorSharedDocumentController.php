<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstructorSharedDocumentController extends Controller
{
    private function instructor(): Student
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $s = Student::findOrFail($studentId);
        abort_unless($s->isInstructor(), 403, 'Riservato ai formatori');
        return $s;
    }

    public function index(Request $request)
    {
        $this->instructor();

        $query = StudentDocument::sharedWithInstructors()
            ->with(['student', 'course', 'module']);

        if ($request->course_id) {
            $query->where('course_id', $request->course_id);
        }

        if ($q = trim((string) $request->q)) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ILIKE', "%$q%")
                  ->orWhere('description', 'ILIKE', "%$q%")
                  ->orWhereHas('student', function ($s) use ($q) {
                      $s->where('name', 'ILIKE', "%$q%");
                  });
            });
        }

        $documents = $query->latest()->paginate(20)->withQueryString();
        $courses = Course::orderBy('name')->get(['id', 'name', 'slug']);

        return view('student.instructor_documents.index', [
            'documents' => $documents,
            'courses'   => $courses,
            'filters'   => $request->only(['course_id', 'q']),
        ]);
    }

    public function download(StudentDocument $document)
    {
        $this->instructor();

        abort_unless($document->visibility === 'instructors', 403);

        abort_unless(
            $document->file_path && Storage::disk('local')->exists($document->file_path),
            404
        );

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $document->original_filename
        );
    }
}
