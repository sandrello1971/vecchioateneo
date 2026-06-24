<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractTeachingDocumentJob;
use App\Models\Subject;
use App\Models\TeachingDocument;
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
        return view('docente.materiali.create', compact('subjects'));
    }

    public function store(Request $request)
    {
        $base = $request->validate([
            'title' => 'required|string|max:255',
            'source_type' => 'required|in:audio,youtube,photos,pdf,docx,text',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'tags' => 'nullable|string|max:500',
        ]);

        // Validazioni specifiche per tipo sorgente.
        match ($base['source_type']) {
            'audio' => $request->validate([
                // Audio E video: si trascrive la traccia audio (Whisper gestisce i
                // contenitori video via ffmpeg). Gli m4a sono contenitori MP4 e PHP
                // li rileva come audio/mp4 o video/mp4: niente regola `mimes` (che
                // mappa l'estensione a un set rigido), ma estensione esplicita +
                // lista mime esplicita (audio e video). Limite invariato 200 MB.
                'file' => [
                    'required', 'file', 'max:204800',
                    'extensions:mp3,m4a,wav,ogg,mp4,mov,mpeg,avi,webm',
                    'mimetypes:'
                        . 'audio/mpeg,audio/mp3,audio/x-mp3,'
                        . 'audio/mp4,audio/x-m4a,audio/m4a,'
                        . 'audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,'
                        . 'audio/ogg,application/ogg,video/ogg,'
                        . 'audio/webm,'
                        . 'video/mp4,video/quicktime,video/mpeg,video/x-msvideo,video/avi,video/msvideo,video/webm',
                ],
            ], [], ['file' => 'file audio o video']),
            'pdf' => $request->validate([
                'file' => 'required|file|mimes:pdf|max:51200', // 50 MB
            ]),
            'docx' => $request->validate([
                'file' => 'required|file|mimes:docx,doc|max:51200',
            ]),
            'photos' => $request->validate([
                'photos' => 'required|array|min:1|max:20',
                'photos.*' => 'image|mimes:jpg,jpeg,png|max:10240', // 10 MB/foto
            ], [], ['photos' => 'foto']),
            'youtube' => $request->validate([
                'source_url' => ['required', 'url', 'max:500', 'regex:#(youtube\.com|youtu\.be)#i'],
            ], ['source_url.regex' => 'Inserisci un URL YouTube valido.']),
            'text' => $request->validate([
                'text_content' => 'required|string',
            ]),
        };

        $doc = TeachingDocument::create([
            'teacher_id' => $this->teacherId(),
            'title' => $base['title'],
            'source_type' => $base['source_type'],
            'subject_id' => $base['subject_id'] ?? null,
            'tags' => $this->parseTags($request->input('tags')),
            'status' => 'pending',
        ]);

        $dir = "teaching-documents/{$doc->teacher_id}/{$doc->id}";
        $sourceFiles = [];
        $sourceUrl = null;

        switch ($base['source_type']) {
            case 'audio':
            case 'pdf':
            case 'docx':
                $ext = $request->file('file')->getClientOriginalExtension() ?: $base['source_type'];
                $sourceFiles[] = $request->file('file')->storeAs($dir, "source.{$ext}", 'local');
                break;

            case 'photos':
                foreach (array_values($request->file('photos')) as $i => $photo) {
                    $ext = $photo->getClientOriginalExtension() ?: 'jpg';
                    $name = sprintf('photo_%02d.%s', $i, $ext); // ordine preservato dall'invio
                    $sourceFiles[] = $photo->storeAs($dir, $name, 'local');
                }
                break;

            case 'youtube':
                $sourceUrl = $request->input('source_url');
                break;

            case 'text':
                $path = "{$dir}/source.md";
                Storage::disk('local')->put($path, $request->input('text_content'));
                $sourceFiles[] = $path;
                break;
        }

        $doc->update(['source_files' => $sourceFiles ?: null, 'source_url' => $sourceUrl]);

        ExtractTeachingDocumentJob::dispatch($doc->id)->afterResponse();

        return redirect()->route('docente.materials.show', $doc)
            ->with('success', 'Materiale caricato. Estrazione del testo in corso…');
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
