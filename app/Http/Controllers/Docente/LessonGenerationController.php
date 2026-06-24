<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLessonJob;
use App\Models\Lesson;
use Illuminate\Http\Request;

// Composizione del CORPO della lezione dai materiali associati (P19). Feedback UX:
// stato immediato (generating) + polling, guard anti-doppio-submit server-side.
// Niente pubblicazione (P20).
class LessonGenerationController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    private function authorizeOwner(Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === $this->teacherId(), 403);
    }

    private function hasReadyMaterials(Lesson $lesson): bool
    {
        return $lesson->teachingDocuments()
            ->where('status', 'ready')
            ->whereNotNull('extracted_text')
            ->exists();
    }

    /**
     * Avvia la composizione: stato "generating" + job asincrono. Idempotente sul
     * doppio submit (se è già in corso, non ridispatcha).
     */
    public function generate(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);

        // Guard anti-doppio-submit (server): già in corso → non duplicare.
        if ($lesson->generation_status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Composizione già in corso.');
        }

        if (!$this->hasReadyMaterials($lesson)) {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('error', 'Assegna almeno un materiale pronto (con testo estratto) prima di comporre la lezione.');
        }

        $lesson->update([
            'generation_status' => 'generating',
            'generation_meta' => array_merge((array) $lesson->generation_meta, [
                'started_at' => now()->toIso8601String(),
            ]),
        ]);

        // afterResponse: la risposta torna subito; il polling porta a ready/failed.
        GenerateLessonJob::dispatch($lesson->id)->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Composizione avviata. La lezione sarà pronta a breve.');
    }

    /**
     * Rigenera il corpo: SOVRASCRIVE il contenuto (conferma esplicita lato UI).
     */
    public function regenerate(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);

        if ($lesson->generation_status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Composizione già in corso.');
        }

        if (!$this->hasReadyMaterials($lesson)) {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('error', 'Nessun materiale pronto da ricomporre.');
        }

        $lesson->update([
            'generation_status' => 'generating',
            'generation_meta' => array_merge((array) $lesson->generation_meta, [
                'started_at' => now()->toIso8601String(),
                'regenerated' => true,
            ]),
        ]);

        GenerateLessonJob::dispatch($lesson->id)->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Rigenerazione avviata: il contenuto verrà sovrascritto.');
    }

    public function status(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);

        return response()->json([
            'status' => $lesson->generation_status,
            'failure_reason' => $lesson->generation_meta['failure_reason'] ?? null,
        ]);
    }
}
