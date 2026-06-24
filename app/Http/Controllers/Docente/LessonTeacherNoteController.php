<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonTeacherNote;
use Illuminate\Http\Request;

// Note del DOCENTE per paragrafo di una lezione (P20b): didattiche, visibili agli
// studenti. Solo il proprietario della lezione le gestisce. anchor = paragrafo,
// content vuoto = toggle-off.
class LessonTeacherNoteController extends Controller
{
    private function authorizeOwner(Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === session('student_id'), 403);
    }

    public function save(Request $request, Lesson $lesson)
    {
        $this->authorizeOwner($lesson);

        $data = $request->validate([
            'anchor' => 'required|string|max:50',
            'content' => 'nullable|string|max:5000',
        ]);

        $content = $data['content'] ?? '';
        $query = LessonTeacherNote::where('lesson_id', $lesson->id)->where('anchor', $data['anchor']);

        if (trim($content) === '') {
            $query->delete();
            return response()->json(['success' => true, 'deleted' => true]);
        }

        $note = $query->first();
        if ($note) {
            $note->update(['content' => $content]);
        } else {
            $note = LessonTeacherNote::create([
                'lesson_id' => $lesson->id,
                'teacher_id' => $lesson->teacher_id,
                'anchor' => $data['anchor'],
                'content' => $content,
            ]);
        }

        return response()->json(['success' => true, 'note' => [
            'id' => $note->id, 'anchor' => $note->anchor, 'content' => $note->content,
        ]]);
    }

    public function list(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);

        $notes = LessonTeacherNote::where('lesson_id', $lesson->id)
            ->orderBy('anchor')
            ->get(['id', 'anchor', 'content', 'updated_at']);

        return response()->json(['notes' => $notes->map(fn ($n) => [
            'id' => $n->id, 'anchor' => $n->anchor, 'content' => $n->content,
            'updated_at' => $n->updated_at?->toIso8601String(),
        ])]);
    }
}
