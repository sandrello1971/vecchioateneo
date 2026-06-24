<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ResolvesScholaAccess;
use App\Models\ArtifactPublication;
use App\Models\SchoolClass;
use App\Models\StudentArtifactView;
use App\Models\StudentGeneratedArtifact;
use App\Services\Schola\ScholaUsage;
use Illuminate\Support\Facades\Storage;

class StudentArtifactController extends Controller
{
    use ResolvesScholaAccess;

    public function show(SchoolClass $class, ArtifactPublication $publication)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertPublicationInClass($publication, $class);

        $artifact = $publication->artifact()->with('teachingDocument')->first();
        abort_unless($artifact && $artifact->status === 'ready', 404);

        // Tracking vista (idempotente: upsert su student_artifact_views).
        $this->trackView($publication->id, $student->id);

        $graph = null;
        $quiz = null;
        if ($artifact->type === 'conceptmap' && !empty($artifact->content)) {
            $decoded = json_decode($artifact->content, true);
            $graph = is_array($decoded) ? $decoded : ['nodes' => [], 'edges' => []];
        }
        if ($artifact->type === 'quiz' && $artifact->quiz_id) {
            $quiz = $artifact->quiz()->with('questions')->first();
        }

        $doc = $artifact->teachingDocument;
        $segments = is_array($doc?->extraction_meta['segments'] ?? null) ? $doc->extraction_meta['segments'] : null;
        $hasAudioSource = $doc && $doc->source_type === 'audio' && !empty($doc->source_files);

        // Auto-generati dello studente per questa pubblicazione + stato rate limit.
        $generated = StudentGeneratedArtifact::where('student_id', $student->id)
            ->where('artifact_publication_id', $publication->id)
            ->orderByDesc('created_at')
            ->get();
        $usage = app(ScholaUsage::class)->generationStatus($student->id);

        return view('student.classi.artefatto', compact(
            'class', 'publication', 'artifact', 'graph', 'quiz',
            'segments', 'hasAudioSource', 'generated', 'usage'
        ));
    }

    /**
     * Serve il file sorgente audio dell'artefatto, in streaming con Range
     * (seek del player HTML5). Difeso da enrollment + coerenza pubblicazione.
     * Chiude anche il deep-link delle citazioni audio in chat (?t=N).
     */
    public function source(SchoolClass $class, ArtifactPublication $publication)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertPublicationInClass($publication, $class);

        $doc = $publication->artifact?->teachingDocument;
        abort_unless($doc && $doc->source_type === 'audio', 404);

        $files = $doc->source_files ?? [];
        abort_unless(!empty($files) && Storage::disk('local')->exists($files[0]), 404);

        // response()->file → BinaryFileResponse: gestisce le Range request (seek).
        return response()->file(Storage::disk('local')->path($files[0]));
    }

    private function trackView(string $publicationId, string $studentId): void
    {
        $view = StudentArtifactView::firstOrNew([
            'artifact_publication_id' => $publicationId,
            'student_id' => $studentId,
        ]);

        if (!$view->exists) {
            $view->first_viewed_at = now();
            $view->view_count = 1;
        } else {
            $view->view_count = (int) $view->view_count + 1;
        }
        $view->last_viewed_at = now();
        $view->save();
    }
}
