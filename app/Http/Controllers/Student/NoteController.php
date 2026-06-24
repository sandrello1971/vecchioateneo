<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentNote;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function save(Request $request, Module $module)
    {
        $data = $request->validate([
            'content' => 'nullable|string|max:5000',
            'anchor'  => 'nullable|string|max:50',
        ]);

        $student = Student::find(session('student_id'));
        if ($student && $student->is_demo) {
            return response()->json(['success' => true, 'demo' => true]);
        }

        abort_unless($student, 403);
        $this->ensureEnrolledInModule($student, $module);

        $anchor = empty($data['anchor']) ? null : $data['anchor'];
        $content = $data['content'] ?? '';

        // Se content vuoto e anchor presente → cancella la nota ancorata (toggle off)
        if ($anchor !== null && trim($content) === '') {
            StudentNote::where('student_id', $student->id)
                ->where('module_id', $module->id)
                ->where('anchor', $anchor)
                ->delete();
            return response()->json(['success' => true, 'deleted' => true]);
        }

        $query = StudentNote::where('student_id', $student->id)
            ->where('module_id', $module->id);
        if ($anchor === null) {
            $query->whereNull('anchor');
        } else {
            $query->where('anchor', $anchor);
        }
        $note = $query->first();

        if ($note) {
            $note->update(['content' => $content]);
        } else {
            $note = StudentNote::create([
                'student_id' => $student->id,
                'module_id'  => $module->id,
                'anchor'     => $anchor,
                'content'    => $content,
            ]);
        }

        return response()->json([
            'success' => true,
            'note' => [
                'id'      => $note->id,
                'anchor'  => $note->anchor,
                'content' => $note->content,
            ],
        ]);
    }

    public function list(Module $module)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);
        $student = Student::findOrFail($studentId);

        $this->ensureEnrolledInModule($student, $module);

        $notes = StudentNote::where('student_id', $studentId)
            ->where('module_id', $module->id)
            ->orderByRaw('CASE WHEN anchor IS NULL THEN 0 ELSE 1 END')
            ->orderBy('anchor')
            ->get(['id', 'anchor', 'content', 'updated_at']);

        return response()->json([
            'notes' => $notes->map(fn($n) => [
                'id'         => $n->id,
                'anchor'     => $n->anchor,
                'content'    => $n->content,
                'updated_at' => $n->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function delete(StudentNote $note)
    {
        $studentId = session('student_id');
        abort_unless($studentId && $note->student_id === $studentId, 403);

        $student = Student::findOrFail($studentId);
        $module = $note->module;
        abort_unless($module, 404);

        $this->ensureEnrolledInModule($student, $module);

        $note->delete();
        return response()->json(['success' => true]);
    }

    private function ensureEnrolledInModule(Student $student, Module $module): void
    {
        $courseId = $module->course_id;
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
