<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\TeachingArtifact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Visualizzazione, editing manuale, eliminazione (soft) di un teaching_artifact.
// Rendering per tipo: markdown (summary/outline/transcript), markmap (mindmap),
// vis-network (conceptmap), domande (quiz). La rigenerazione è in
// ArtifactGenerationController@regenerate.
class ArtifactController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    private function authorizeOwner(TeachingArtifact $artifact): void
    {
        abort_unless($artifact->teacher_id === $this->teacherId(), 403);
    }

    public function show(TeachingArtifact $artifact)
    {
        $this->authorizeOwner($artifact);
        $artifact->load(['teachingDocument', 'subject', 'publications.schoolClass', 'teacher:id,name,library_rights_ack_at']);

        $graph = null;
        $quiz = null;

        if ($artifact->type === 'conceptmap' && !empty($artifact->content)) {
            $decoded = json_decode($artifact->content, true);
            $graph = is_array($decoded) ? $decoded : ['nodes' => [], 'edges' => []];
        }

        if ($artifact->type === 'quiz' && $artifact->quiz_id) {
            $quiz = $artifact->quiz()->with('questions')->first();
        }

        // Classi del docente pubblicabili (transcript escluso: vedi nota copyright
        // SPEC §6; in 6a teniamo la pubblicazione su tutti i tipi tranne transcript
        // da foto/pdf — guardrail completo arriva con la Biblioteca).
        // Selettore di pubblicazione: classi su cui il docente può pubblicare —
        // libere proprie + classi di scuola dove ha cattedra (P15).
        $teacherClasses = app(\App\Services\Schola\TeacherClassAccess::class)
            ->classesQuery($artifact->teacher_id)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();
        $publishedClassIds = $artifact->publications->pluck('school_class_id')->all();

        // Biblioteca (pacchetto 9): stato condivisione.
        $sharingBlocked = \App\Http\Controllers\Docente\ArtifactSharingController::isCopyrightBlocked($artifact);
        $rightsAcked = (bool) ($artifact->teacher?->library_rights_ack_at);

        return view('docente.artefatti.show', compact(
            'artifact', 'graph', 'quiz', 'teacherClasses', 'publishedClassIds',
            'sharingBlocked', 'rightsAcked'
        ));
    }

    /**
     * Editing manuale del contenuto. Per la mappa concettuale il JSON deve essere
     * valido (nodes/edges); per il quiz si edita solo il titolo (le domande
     * vivono su quiz_questions).
     */
    public function update(Request $request, TeachingArtifact $artifact)
    {
        $this->authorizeOwner($artifact);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        $update = ['title' => $data['title']];

        if ($artifact->type !== 'quiz') {
            $content = $data['content'] ?? null;

            if ($artifact->type === 'conceptmap' && !empty($content)) {
                $decoded = json_decode($content, true);
                $ok = Validator::make(
                    ['graph' => $decoded],
                    ['graph' => 'array', 'graph.nodes' => 'present|array', 'graph.edges' => 'present|array']
                )->passes();

                if (!$ok) {
                    return back()
                        ->withErrors(['content' => 'La mappa concettuale deve essere JSON valido con "nodes" ed "edges".'])
                        ->withInput();
                }
            }

            $update['content'] = $content;
        }

        $artifact->update($update);

        return redirect()->route('docente.artifacts.show', $artifact)
            ->with('success', 'Artefatto aggiornato.');
    }

    public function destroy(TeachingArtifact $artifact)
    {
        $this->authorizeOwner($artifact);

        $documentId = $artifact->teaching_document_id;
        $artifact->delete(); // soft delete

        if ($documentId) {
            return redirect()->route('docente.materials.show', $documentId)
                ->with('success', 'Artefatto eliminato.');
        }

        return redirect()->route('docente.materials.index')
            ->with('success', 'Artefatto eliminato.');
    }

    public function status(TeachingArtifact $artifact)
    {
        $this->authorizeOwner($artifact);

        return response()->json([
            'status' => $artifact->status,
            'type' => $artifact->type,
            'failure_reason' => $artifact->generation_meta['failure_reason'] ?? null,
        ]);
    }
}
