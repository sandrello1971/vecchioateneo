<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractTeachingDocumentJob;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingDocument;
use App\Models\Topic;
use App\Services\Schola\TeachingDocumentUploader;
use App\Support\VideoAiConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeachingDocumentController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    private function authorizeOwner(TeachingDocument $document): void
    {
        abort_unless($document->teacher_id === $this->teacherId(), 403);
    }

    public function index(Request $request)
    {
        $query = TeachingDocument::where('teacher_id', $this->teacherId())->with('subject');

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }

        $documents = $query->orderByDesc('created_at')->get();
        $subjects = Subject::orderBy('name')->get();

        return view('docente.materiali.index', compact('documents', 'subjects'));
    }

    public function create()
    {
        $subjects = Subject::orderBy('name')->get();
        // R5 — il docente è di una scuola senza consenso DPA video-AI? → audio/video/foto bloccati.
        $videoAiDpaMissing = VideoAiConsent::dpaMissing(Student::find($this->teacherId()));
        $externalTypes = VideoAiConsent::externalSourceTypes();

        return view('docente.materiali.create', compact('subjects', 'videoAiDpaMissing', 'externalTypes'));
    }

    public function store(Request $request, TeachingDocumentUploader $uploader)
    {
        $base = $request->validate([
            'title' => 'required|string|max:255',
            'source_type' => 'required|in:audio,youtube,photos,pdf,docx,text',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'tags' => 'nullable|string|max:500',
            // Contesto di upload: dalla Lezione (materiale legato) o dal pool Argomento
            // (materia ereditata). Assenti entrambi = upload autonomo dalla sezione Materiali.
            'lesson_id' => 'nullable|uuid|exists:lessons,id',
            'topic_id' => 'nullable|uuid|exists:topics,id',
        ]);

        // Risoluzione contesto: la materia è ereditata dall'argomento e il redirect
        // torna alla pagina di origine. Verifica sempre la proprietà del docente.
        $lesson = null;
        $topic = null;
        $subjectId = $base['subject_id'] ?? null;
        if (!empty($base['lesson_id'])) {
            $lesson = Lesson::with('topic')->findOrFail($base['lesson_id']);
            abort_unless($lesson->teacher_id === $this->teacherId(), 403);
            $subjectId = $lesson->topic?->subject_id;
        } elseif (!empty($base['topic_id'])) {
            $topic = Topic::findOrFail($base['topic_id']);
            abort_unless($topic->teacher_id === $this->teacherId(), 403);
            $subjectId = $topic->subject_id;
        }

        $owner = Student::find($this->teacherId());

        // R5 — gate DPA: audio/video/foto passano da sub-processori esterni → serve il
        // consenso video-AI della scuola (firmato dalla segreteria).
        if (VideoAiConsent::blocked($owner, $base['source_type'])) {
            return back()->withInput()->with('error', VideoAiConsent::MESSAGE);
        }

        $doc = $uploader->handle($request, $owner, [
            'title' => $base['title'],
            'source_type' => $base['source_type'],
            'subject_id' => $subjectId,
            'lesson_id' => $lesson?->id, // caricato dalla lezione → già classificato; altrimenti pool
            'tags' => $request->input('tags'),
        ]);

        $msg = 'Materiale caricato. Estrazione del testo in corso…';
        if ($lesson) {
            return redirect()->route('docente.lessons.show', $lesson)->with('success', $msg);
        }
        if ($topic) {
            return redirect()->route('docente.topics.show', $topic)->with('success', $msg);
        }

        return redirect()->route('docente.materials.show', $doc)->with('success', $msg);
    }

    public function show(TeachingDocument $document)
    {
        $this->authorizeOwner($document);
        $document->load(['subject', 'artifacts' => fn ($q) => $q->orderBy('created_at')]);
        return view('docente.materiali.show', ['document' => $document]);
    }

    public function update(Request $request, TeachingDocument $document)
    {
        $this->authorizeOwner($document);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'tags' => 'nullable|string|max:500',
            'extracted_text' => 'nullable|string',
        ]);

        $document->update([
            'title' => $data['title'],
            'subject_id' => $data['subject_id'] ?? null,
            'tags' => $this->parseTags($request->input('tags')),
            'extracted_text' => $data['extracted_text'] ?? $document->extracted_text,
        ]);

        return redirect()->route('docente.materials.show', $document)
            ->with('success', 'Materiale aggiornato.');
    }

    public function destroy(TeachingDocument $document)
    {
        $this->authorizeOwner($document);
        $document->delete(); // soft delete

        return redirect()->route('docente.materials.index')
            ->with('success', 'Materiale eliminato.');
    }

    public function downloadSource(TeachingDocument $document, int $index)
    {
        $this->authorizeOwner($document);

        $files = $document->source_files ?? [];
        abort_unless(isset($files[$index]), 404);

        $path = $files[$index];
        abort_unless(Storage::disk('local')->exists($path), 404);

        // SOLO via controller: storage privato, mai URL diretto.
        return response()->download(Storage::disk('local')->path($path));
    }

    public function status(TeachingDocument $document)
    {
        $this->authorizeOwner($document);

        return response()->json([
            'status' => $document->status,
            'failure_reason' => $document->failure_reason,
            'has_text' => !empty($document->extracted_text),
            'method' => $document->extraction_meta['method'] ?? null,
        ]);
    }

    public function retry(TeachingDocument $document)
    {
        $this->authorizeOwner($document);
        abort_unless($document->status === 'failed', 422, 'Solo i materiali falliti possono essere ritentati.');

        $document->update(['status' => 'pending', 'failure_reason' => null]);
        ExtractTeachingDocumentJob::dispatch($document->id)->afterResponse();

        return redirect()->route('docente.materials.show', $document)
            ->with('success', 'Nuovo tentativo di estrazione avviato.');
    }

    private function parseTags(?string $raw): ?array
    {
        if (!$raw) {
            return null;
        }
        $tags = collect(explode(',', $raw))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $tags ?: null;
    }
}
