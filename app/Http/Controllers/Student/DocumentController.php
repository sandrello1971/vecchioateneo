<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $student = $this->student();

        $query = StudentDocument::ownedBy($student->id)
            ->with(['course', 'module']);

        if ($request->course_id) {
            $query->where('course_id', $request->course_id);
        }
        if ($request->visibility && in_array($request->visibility, array_keys(StudentDocument::VISIBILITY), true)) {
            $query->where('visibility', $request->visibility);
        }

        $documents = $query->latest()->paginate(20)->withQueryString();

        $courses = $student->auto_enroll_all_courses
            ? Course::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])
            : $student->courses()->wherePivot('is_active', true)->orderBy('name')->get(['courses.id', 'courses.name', 'courses.slug']);

        $modulesByCourse = Module::whereIn('course_id', $courses->pluck('id'))
            ->orderBy('sort_order')
            ->get(['id', 'course_id', 'sort_order', 'title'])
            ->groupBy('course_id');

        return view('student.documents.index', [
            'documents'       => $documents,
            'courses'         => $courses,
            'modulesByCourse' => $modulesByCourse,
            'visibilities'    => StudentDocument::VISIBILITY,
            'filters'         => $request->only(['course_id', 'visibility']),
            'isDemo'          => (bool) $student->is_demo,
        ]);
    }

    public function store(Request $request)
    {
        $student = $this->student();

        if ($student->is_demo) {
            abort(403, 'Funzione non disponibile in modalità demo');
        }

        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'course_id'   => 'nullable|uuid|exists:courses,id',
            'module_id'   => 'nullable|uuid|exists:modules,id',
            'visibility'  => 'required|in:private,instructors',
            'file'        => 'required|file|max:20480|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,md,png,jpg,jpeg,webp,zip',
        ]);

        if (!empty($data['course_id'])) {
            $this->ensureEnrolled($student, $data['course_id']);

            if (!empty($data['module_id'])) {
                $belongs = Module::where('id', $data['module_id'])
                    ->where('course_id', $data['course_id'])
                    ->exists();
                abort_unless($belongs, 422, 'Il modulo selezionato non appartiene al corso scelto');
            }
        } else {
            $data['module_id'] = null;
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $filename = (string) Str::uuid() . ($ext ? ('.' . $ext) : '');
        $directory = "student-documents/{$student->id}";

        Storage::disk('local')->putFileAs($directory, $file, $filename);

        StudentDocument::create([
            'student_id'        => $student->id,
            'course_id'         => $data['course_id'] ?? null,
            'module_id'         => $data['module_id'] ?? null,
            'title'             => $data['title'],
            'description'       => $data['description'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'file_path'         => "{$directory}/{$filename}",
            'file_type'         => $ext ?: null,
            'file_size'         => $file->getSize(),
            'visibility'        => $data['visibility'],
        ]);

        return redirect()->route('student.documents.index')
            ->with('success', 'Documento caricato.');
    }

    public function download(StudentDocument $document)
    {
        $studentId = session('student_id');
        abort_unless($studentId && $document->student_id === $studentId, 403);

        abort_unless(
            $document->file_path && Storage::disk('local')->exists($document->file_path),
            404
        );

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $document->original_filename
        );
    }

    public function update(Request $request, StudentDocument $document)
    {
        $student = $this->student();
        abort_unless($document->student_id === $student->id, 403);

        if ($student->is_demo) {
            abort(403, 'Funzione non disponibile in modalità demo');
        }

        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'course_id'   => 'nullable|uuid|exists:courses,id',
            'module_id'   => 'nullable|uuid|exists:modules,id',
            'visibility'  => 'required|in:private,instructors',
        ]);

        if (!empty($data['course_id'])) {
            $this->ensureEnrolled($student, $data['course_id']);

            if (!empty($data['module_id'])) {
                $belongs = Module::where('id', $data['module_id'])
                    ->where('course_id', $data['course_id'])
                    ->exists();
                abort_unless($belongs, 422, 'Il modulo selezionato non appartiene al corso scelto');
            }
        } else {
            $data['module_id'] = null;
        }

        $document->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'course_id'   => $data['course_id'] ?? null,
            'module_id'   => $data['module_id'] ?? null,
            'visibility'  => $data['visibility'],
        ]);

        return redirect()->route('student.documents.index')
            ->with('success', 'Documento aggiornato.');
    }

    public function destroy(StudentDocument $document)
    {
        $student = $this->student();
        abort_unless($document->student_id === $student->id, 403);

        if ($student->is_demo) {
            abort(403, 'Funzione non disponibile in modalità demo');
        }

        $document->delete();

        return redirect()->route('student.documents.index')
            ->with('success', 'Documento eliminato.');
    }

    private function student(): Student
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);
        return Student::findOrFail($studentId);
    }

    private function ensureEnrolled(Student $student, string $courseId): void
    {
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
