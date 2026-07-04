<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingDocument;
use App\Models\Topic;
use App\Support\VideoAiConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Fase 3 (P18) — Argomenti del docente. Solo organizzazione: niente generazione
// del corpo lezione (P19) né pubblicazione (P20).
class TopicController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    private function teacher(): Student
    {
        return Student::findOrFail($this->teacherId());
    }

    private function authorizeOwner(Topic $topic): void
    {
        abort_unless($topic->teacher_id === $this->teacherId(), 403);
    }

    // Materie su cui il docente può creare argomenti: docente libero → tutte;
    // docente di scuola → solo le sue competenze (professor_subjects).
    private function allowedSubjects(Student $teacher)
    {
        if ($teacher->school_id === null) {
            return Subject::orderBy('name')->get();
        }

        return $teacher->teachableSubjects()->orderBy('name')->get();
    }

    public function index()
    {
        $teacher = $this->teacher();

        $topics = Topic::where('teacher_id', $teacher->id)
            ->with(['subject', 'lessons'])
            ->withCount('lessons')
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();

        // Pool "da organizzare": materiali pronti del docente non ancora in una lezione.
        $unclassifiedCount = TeachingDocument::where('teacher_id', $teacher->id)
            ->whereNull('lesson_id')
            ->count();

        $subjects = $this->allowedSubjects($teacher);

        return view('docente.argomenti.index', compact('topics', 'subjects', 'unclassifiedCount'));
    }

    public function store(Request $request)
    {
        $teacher = $this->teacher();
        $allowed = $this->allowedSubjects($teacher)->pluck('id')->all();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
        ]);

        abort_unless(in_array($data['subject_id'], $allowed, true), 403,
            'Materia non tra le tue competenze.');

        $position = (int) Topic::where('teacher_id', $teacher->id)->max('position') + 1;

        Topic::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $data['subject_id'],
            'school_id' => $teacher->school_id, // null per docente libero
            'name' => $data['name'],
            'position' => $position,
        ]);

        return redirect()->route('docente.topics.index')
            ->with('success', 'Argomento creato.');
    }

    public function show(Topic $topic)
    {
        $this->authorizeOwner($topic);
        $teacher = $this->teacher();

        $topic->load(['subject', 'lessons' => fn ($q) => $q->withCount('teachingDocuments')->orderBy('position')]);

        // Materiali già classificati nelle lezioni di questo argomento.
        $lessonIds = $topic->lessons->pluck('id');
        $classified = TeachingDocument::whereIn('lesson_id', $lessonIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('lesson_id');

        // Pool "da organizzare": materiali del docente senza lezione. Si privilegiano
        // quelli della stessa materia dell'argomento, ma restano disponibili tutti.
        $pool = TeachingDocument::where('teacher_id', $teacher->id)
            ->whereNull('lesson_id')
            ->with('subject')
            ->orderByRaw('CASE WHEN subject_id = ? THEN 0 ELSE 1 END', [$topic->subject_id])
            ->orderByDesc('created_at')
            ->get();

        // Upload materiale nel pool direttamente da qui (gate DPA come nella sezione Materiali).
        $videoAiDpaMissing = VideoAiConsent::dpaMissing($teacher);
        $externalTypes = VideoAiConsent::externalSourceTypes();

        return view('docente.argomenti.show', compact('topic', 'classified', 'pool', 'videoAiDpaMissing', 'externalTypes'));
    }

    public function update(Request $request, Topic $topic)
    {
        $this->authorizeOwner($topic);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $topic->update(['name' => $data['name']]);

        return back()->with('success', 'Argomento aggiornato.');
    }

    public function destroy(Topic $topic)
    {
        $this->authorizeOwner($topic);

        DB::transaction(function () use ($topic) {
            $lessonIds = $topic->lessons()->pluck('id');

            // I materiali classificati tornano nel pool "da organizzare".
            TeachingDocument::whereIn('lesson_id', $lessonIds)->update(['lesson_id' => null]);

            // Le lezioni dell'argomento vengono soft-deleted con esso.
            $topic->lessons()->delete();
            $topic->delete();
        });

        return redirect()->route('docente.topics.index')
            ->with('success', 'Argomento eliminato. I materiali sono tornati nel pool.');
    }

    // Riordino argomenti (drag&drop → lista di id nell'ordine voluto).
    public function reorder(Request $request)
    {
        $teacher = $this->teacher();

        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'uuid',
        ]);

        DB::transaction(function () use ($data, $teacher) {
            foreach ($data['order'] as $position => $id) {
                Topic::where('id', $id)
                    ->where('teacher_id', $teacher->id)
                    ->update(['position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
