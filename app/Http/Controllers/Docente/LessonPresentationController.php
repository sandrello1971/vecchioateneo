<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLessonPresentationJob;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Presentazione .pptx di una lezione (P21): generazione/rigenerazione/stato/
// download lato docente. Solo il proprietario della lezione. File da storage
// PRIVATO, mai URL diretto. Una presentazione per lezione (rigenera = sovrascrive).
class LessonPresentationController extends Controller
{
    private function authorizeOwner(Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === session('student_id'), 403);
    }

    /** Riga presentazione della lezione (singola, riusata su rigenerazione). */
    private function presentationFor(Lesson $lesson): LessonPresentation
    {
        return LessonPresentation::firstOrCreate(
            ['lesson_id' => $lesson->id],
            ['status' => 'pending']
        );
    }

    public function generate(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        abort_unless($lesson->generation_status === 'ready' && !empty($lesson->content), 422,
            'Componi prima il corpo della lezione: la presentazione si genera da una lezione pronta.');

        $presentation = $this->presentationFor($lesson);

        // Anti-doppio-submit (server): già in corso → non ridispatcha.
        if ($presentation->status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Generazione presentazione già in corso.');
        }

        $presentation->update(['status' => 'generating']);
        GenerateLessonPresentationJob::dispatch($presentation->id)->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Generazione presentazione avviata. Sarà pronta a breve.');
    }

    /** Rigenera: sovrascrive la presentazione esistente (conferma lato UI). */
    public function regenerate(Lesson $lesson)
    {
        return $this->generate($lesson);
    }

    public function status(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $presentation = $lesson->presentations()->latest()->first();

        return response()->json([
            'status' => $presentation?->status ?? 'none',
            'failure_reason' => $presentation?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    public function download(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $presentation = $lesson->presentations()->where('status', 'ready')->latest()->first();

        abort_unless($presentation && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $filename = $presentation->generation_meta['filename'] ?? (Str::slug($lesson->title) . '.pptx');

        // SOLO via controller: storage privato, mai URL diretto.
        return response()->download(Storage::disk('local')->path($presentation->file_path), $filename);
    }
}
