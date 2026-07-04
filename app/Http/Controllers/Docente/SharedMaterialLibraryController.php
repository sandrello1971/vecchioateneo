<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\IngestArtifactTeacherPrivateJob;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Libreria "Materiali condivisi": i materiali che altri docenti hanno condiviso e
// che sono visibili a questo docente (share_scope='all', oppure 'subject' con stessa
// materia+scuola). Semantica fork: l'import crea una COPIA INDIPENDENTE nel pool del
// richiedente (poi indicizzata come teacher_private → entra nella sua Minerva).
class SharedMaterialLibraryController extends Controller
{
    private function teacher(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    private function assertVisible(Student $teacher, TeachingDocument $document): void
    {
        abort_unless(
            TeachingDocument::visibleAsSharedTo($teacher)->whereKey($document->getKey())->exists(),
            403
        );
    }

    public function index(Request $request)
    {
        $teacher = $this->teacher();

        $query = TeachingDocument::visibleAsSharedTo($teacher)
            ->with(['teacher:id,name', 'subject:id,name']);

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }
        if ($request->filled('q')) {
            $query->where('title', 'ILIKE', '%' . $request->input('q') . '%');
        }

        $documents = $query->orderByDesc('created_at')->get();
        $subjects = Subject::orderBy('name')->get();

        return view('docente.materiali-condivisi.index', compact('documents', 'subjects'));
    }

    public function show(TeachingDocument $document)
    {
        $teacher = $this->teacher();
        $this->assertVisible($teacher, $document);
        $document->load(['teacher:id,name', 'subject:id,name']);

        return view('docente.materiali-condivisi.show', ['document' => $document]);
    }

    public function download(TeachingDocument $document, int $index)
    {
        $teacher = $this->teacher();
        $this->assertVisible($teacher, $document);

        $files = $document->source_files ?? [];
        abort_unless(isset($files[$index]), 404);
        $path = $files[$index];
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path));
    }

    /**
     * Importa (fork) il materiale nel proprio pool: copia indipendente con
     * lesson_id/share_scope azzerati, file sorgente copiati nella propria dir, e
     * trascrizione ricreata + indicizzata teacher_private (entra nella propria Minerva).
     */
    public function import(TeachingDocument $document)
    {
        $teacher = $this->teacher();
        $this->assertVisible($teacher, $document);

        [$copy, $transcript] = DB::transaction(function () use ($teacher, $document) {
            $copy = TeachingDocument::create([
                'teacher_id' => $teacher->id,
                'lesson_id' => null,            // entra nel pool "da organizzare"
                'title' => $document->title,
                'source_type' => $document->source_type,
                'source_url' => $document->source_url,
                'subject_id' => $document->subject_id,
                'tags' => $document->tags,
                'status' => $document->status,
                'extracted_text' => $document->extracted_text,
                'extraction_meta' => $document->extraction_meta,
                'share_scope' => null,          // la copia nasce privata
                'shared_school_id' => null,
            ]);

            // Copia dei file sorgente nella dir privata del nuovo proprietario.
            $newFiles = [];
            foreach (($document->source_files ?? []) as $rel) {
                $dest = "teaching-documents/{$teacher->id}/{$copy->id}/" . basename($rel);
                if (Storage::disk('local')->exists($rel)) {
                    Storage::disk('local')->copy($rel, $dest);
                    $newFiles[] = $dest;
                }
            }
            $copy->update(['source_files' => $newFiles ?: null]);

            // Ricrea la trascrizione (se il materiale è pronto) per la Minerva del docente.
            $transcript = null;
            if ($copy->status === 'ready' && trim((string) $copy->extracted_text) !== '') {
                $transcript = TeachingArtifact::create([
                    'teaching_document_id' => $copy->id,
                    'teacher_id' => $teacher->id,
                    'type' => 'transcript',
                    'title' => 'Trascrizione — ' . $copy->title,
                    'content' => $copy->extracted_text,
                    'subject_id' => $copy->subject_id,
                    'status' => 'ready',
                    'generation_meta' => ['source' => 'import', 'imported_from' => $document->id],
                ]);
            }

            return [$copy, $transcript];
        });

        if ($transcript) {
            IngestArtifactTeacherPrivateJob::dispatch($transcript->id)->afterResponse();
        }

        return redirect()->route('docente.materials.show', $copy)
            ->with('success', 'Materiale importato nel tuo pool: ora è tuo. Puoi assegnarlo a una lezione o rielaborarlo.');
    }
}
