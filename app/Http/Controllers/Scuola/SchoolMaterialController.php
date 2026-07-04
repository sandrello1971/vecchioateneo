<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\Subject;
use App\Models\TeachingDocument;
use App\Services\Schola\ArtifactRagIngestor;
use App\Services\Schola\TeachingDocumentUploader;
use App\Support\VideoAiConsent;
use Illuminate\Http\Request;

// Gestione contenuti a livello scuola (segreteria estesa): vede TUTTI i materiali
// grezzi dei docenti della scuola, carica materiale di scuola (→ Biblioteca), e li
// elimina. Tutto tenant-scoped su school_id via ResolvesSchoolAccess.
class SchoolMaterialController extends Controller
{
    use ResolvesSchoolAccess;

    public function index(Request $request)
    {
        $schoolId = $this->currentSchoolId();

        $query = TeachingDocument::inSchool($schoolId)->with(['teacher:id,name', 'subject:id,name']);
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->input('teacher_id'));
        }
        if ($request->boolean('school_only')) {
            $query->where('is_school_material', true);
        }

        $documents = $query->orderByDesc('created_at')->get();
        $teachers = $this->currentSchool()->teachers()->orderBy('name')->get(['id', 'name']);

        return view('scuola.materiali.index', compact('documents', 'teachers'));
    }

    public function create()
    {
        $admin = $this->currentSchoolAdmin();
        $subjects = Subject::orderBy('name')->get();
        $videoAiDpaMissing = VideoAiConsent::dpaMissing($admin);
        $externalTypes = VideoAiConsent::externalSourceTypes();

        return view('scuola.materiali.create', compact('subjects', 'videoAiDpaMissing', 'externalTypes'));
    }

    public function store(Request $request, TeachingDocumentUploader $uploader)
    {
        $admin = $this->currentSchoolAdmin();

        $base = $request->validate([
            'title' => 'required|string|max:255',
            'source_type' => 'required|in:audio,youtube,photos,pdf,docx,text',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'tags' => 'nullable|string|max:500',
        ]);

        if (VideoAiConsent::blocked($admin, $base['source_type'])) {
            return back()->withInput()->with('error', VideoAiConsent::MESSAGE);
        }

        $uploader->handle($request, $admin, [
            'title' => $base['title'],
            'source_type' => $base['source_type'],
            'subject_id' => $base['subject_id'] ?? null,
            'tags' => $request->input('tags'),
            'school_id' => $this->currentSchoolId(),
            'is_school_material' => true,   // → Biblioteca di scuola
            'share_scope' => 'all',         // visibile a tutta la scuola
        ]);

        return redirect()->route('scuola.materiali.index')
            ->with('success', 'Materiale di scuola caricato. Estrazione del testo in corso…');
    }

    public function destroy(TeachingDocument $document, ArtifactRagIngestor $ingestor)
    {
        // Deve appartenere alla scuola dell'admin (tenancy).
        $this->assertSameSchool($document);

        $ingestor->purgeDocument($document); // rimuove i chunk RAG (shared + privati)
        $document->delete();                 // soft-delete

        return back()->with('success', 'Materiale eliminato.');
    }
}
