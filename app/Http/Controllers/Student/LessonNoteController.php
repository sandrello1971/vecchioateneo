<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ResolvesScholaAccess;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\StudentLessonNote;
use Illuminate\Http\Request;

// Appunti personali dello studente per paragrafo di una lezione (P20b). Stesso
// pattern di NoteController (mondo corsi) ma su student_lesson_notes: anchor =
// paragrafo, content vuoto = toggle-off. Difeso da enrollment + pubblicazione.
class LessonNoteController extends Controller
{
    use ResolvesScholaAccess;

    private function assertAccess(SchoolClass $class, Lesson $lesson, string $studentId): void
    {
        $this->assertActiveEnrollment($class, $studentId);
        abort_unless(
            LessonPublication::where('lesson_id', $lesson->id)
                ->where('school_class_id', $class->id)->exists(),
            403
        );
    }

    public function save(Request $request, SchoolClass $class, Lesson $lesson)
    {
        $student = $this->currentStudent();
        $this->assertAccess($class, $lesson, $student->id);

        if ($student->is_demo) {
            return response()->json(['success' => true, 'demo' => true]);
        }

        $data = $request->validate([
            'content' => 'nullable|string|max:5000',
            'anchor' => 'nullable|string|max:50',
        ]);

        $anchor = empty($data['anchor']) ? null : $data['anchor'];
        $content = $data['content'] ?? '';

        $query = StudentLessonNote::where('student_id', $student->id)
            ->where('lesson_id', $lesson->id);
        $anchor === null ? $query->whereNull('anchor') : $query->where('anchor', $anchor);

        // Content vuoto → toggle-off (cancella la nota).
        if (trim($content) === '') {
            $query->delete();
            return response()->json(['success' => true, 'deleted' => true]);
        }

        $note = $query->first();
        if ($note) {
            $note->update(['content' => $content]);
        } else {
            $note = StudentLessonNote::create([
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
                'anchor' => $anchor,
                'content' => $content,
            ]);
        }

        return response()->json(['success' => true, 'note' => [
            'id' => $note->id, 'anchor' => $note->anchor, 'content' => $note->content,
        ]]);
    }

    public function list(SchoolClass $class, Lesson $lesson)
    {
        $student = $this->currentStudent();
        $this->assertAccess($class, $lesson, $student->id);

        $notes = StudentLessonNote::where('student_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->orderByRaw('CASE WHEN anchor IS NULL THEN 0 ELSE 1 END')
            ->orderBy('anchor')
            ->get(['id', 'anchor', 'content', 'updated_at']);

        return response()->json(['notes' => $notes->map(fn ($n) => [
            'id' => $n->id, 'anchor' => $n->anchor, 'content' => $n->content,
            'updated_at' => $n->updated_at?->toIso8601String(),
        ])]);
    }

    public function delete(StudentLessonNote $note)
    {
        $studentId = session('student_id');
        abort_unless($studentId && $note->student_id === $studentId, 403);

        $note->delete();
        return response()->json(['success' => true]);
    }
}
