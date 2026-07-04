<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\TeachingDocument;
use App\Models\Topic;
use App\Support\VideoAiConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Fase 3 (P18) — Lezioni dentro un argomento + classificazione materiali.
// NIENTE generazione del corpo lezione (P19): qui solo struttura/organizzazione.
class LessonController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    private function authorizeTopic(Topic $topic): void
    {
        abort_unless($topic->teacher_id === $this->teacherId(), 403);
    }

    private function authorizeLesson(Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === $this->teacherId(), 403);
    }

    public function show(Lesson $lesson)
    {
        $this->authorizeLesson($lesson);

        $lesson->load(['topic.subject', 'publications.schoolClass']);
        $materials = $lesson->teachingDocuments()->with('subject')->orderBy('created_at')->get();
        $artifacts = $lesson->teachingArtifacts()->orderByDesc('created_at')->get();

        // Anteprima con anchor per paragrafo + note docente esistenti (per-paragrafo).
        $bodyHtml = $lesson->content
            ? app(\App\Services\NoteAnchorInjector::class)->inject(schola_markdown($lesson->content))
            : null;
        $teacherNotes = \App\Models\LessonTeacherNote::where('lesson_id', $lesson->id)
            ->get(['anchor', 'content'])->keyBy('anchor');

        // Classi pubblicabili: libere proprie + classi di scuola con cattedra (P15).
        $teacherClasses = app(\App\Services\Schola\TeacherClassAccess::class)
            ->classesQuery($lesson->teacher_id)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();
        $publishedClassIds = $lesson->publications->pluck('school_class_id')->all();

        // Presentazione .pptx (P21) — bi-versione: la BOZZA in lavorazione e la
        // versione PUBBLICATA (ciò che vedono gli studenti) sono record distinti.
        $draft = $lesson->presentations()->whereNull('published_at')->latest()->first();
        $published = $lesson->presentations()->whereNotNull('published_at')->latest('published_at')->first();

        // Upload materiale direttamente dalla lezione (gate DPA come nella sezione Materiali).
        $videoAiDpaMissing = VideoAiConsent::dpaMissing(Student::find($this->teacherId()));
        $externalTypes = VideoAiConsent::externalSourceTypes();

        return view('docente.lezioni.show', compact(
            'lesson', 'materials', 'artifacts', 'teacherClasses', 'publishedClassIds',
            'bodyHtml', 'teacherNotes', 'draft', 'published',
            'videoAiDpaMissing', 'externalTypes'
        ));
    }

    // Editing del corpo lezione: SEMPRE modificabile dal docente dopo la generazione.
    public function updateContent(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);

        $data = $request->validate([
            'content' => 'nullable|string',
        ]);

        $lesson->update(['content' => $data['content'] ?? null]);

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Lezione aggiornata.');
    }

    public function store(Request $request, Topic $topic)
    {
        $this->authorizeTopic($topic);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $position = (int) Lesson::where('topic_id', $topic->id)->max('position') + 1;

        Lesson::create([
            'topic_id' => $topic->id,
            'teacher_id' => $topic->teacher_id,
            'title' => $data['title'],
            'position' => $position,
            'generation_status' => 'draft',
        ]);

        return back()->with('success', 'Lezione creata.');
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $lesson->update(['title' => $data['title']]);

        return back()->with('success', 'Lezione aggiornata.');
    }

    public function destroy(Lesson $lesson)
    {
        $this->authorizeLesson($lesson);

        DB::transaction(function () use ($lesson) {
            // I materiali della lezione tornano nel pool "da organizzare".
            $lesson->teachingDocuments()->update(['lesson_id' => null]);
            $lesson->delete();
        });

        return back()->with('success', 'Lezione eliminata. I materiali sono tornati nel pool.');
    }

    // Riordino lezioni dentro l'argomento.
    public function reorder(Request $request, Topic $topic)
    {
        $this->authorizeTopic($topic);

        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'uuid',
        ]);

        DB::transaction(function () use ($data, $topic) {
            foreach ($data['order'] as $position => $id) {
                Lesson::where('id', $id)
                    ->where('topic_id', $topic->id)
                    ->update(['position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }

    // Classificazione: assegna un materiale del docente a questa lezione.
    public function assignMaterial(Request $request, Lesson $lesson)
    {
        $this->authorizeLesson($lesson);

        $data = $request->validate([
            'document_id' => 'required|uuid',
        ]);

        // Il materiale deve appartenere al docente (defense in depth).
        $document = TeachingDocument::where('id', $data['document_id'])
            ->where('teacher_id', $lesson->teacher_id)
            ->firstOrFail();

        $document->update(['lesson_id' => $lesson->id]);

        return back()->with('success', 'Materiale classificato nella lezione.');
    }

    // Rimuove un materiale dalla lezione → torna nel pool.
    public function unassignMaterial(Lesson $lesson, TeachingDocument $document)
    {
        $this->authorizeLesson($lesson);
        abort_unless($document->lesson_id === $lesson->id, 404);
        abort_unless($document->teacher_id === $lesson->teacher_id, 403);

        $document->update(['lesson_id' => null]);

        return back()->with('success', 'Materiale rimandato al pool.');
    }
}
