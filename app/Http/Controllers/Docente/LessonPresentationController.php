<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLessonPresentationJob;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Services\Schola\SlidePreviewService;
use Illuminate\Http\Request;
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

    /**
     * S2 — correzione via prompt. Solo presentazioni con spec persistita
     * (generate dal sistema). Async: dispatcha il job con l'istruzione.
     */
    public function edit(Request $request, Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $data = $request->validate(['instruction' => 'required|string|max:2000']);

        $presentation = $lesson->presentations()->latest()->first();
        abort_unless($presentation && $presentation->status === 'ready' && !empty($presentation->spec), 422,
            'Questa presentazione non è correggibile via prompt: rigenerala dal sistema per abilitarla.');

        // Anti-doppio-submit (server): già in corso → non ridispatcha.
        if ($presentation->status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Correzione già in corso.');
        }

        $presentation->update(['status' => 'generating']);
        GenerateLessonPresentationJob::dispatch($presentation->id, $data['instruction'])->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Correzione avviata. Le slide saranno aggiornate a breve.');
    }

    /**
     * S3 — carica una propria versione .pptx (sostituisce quella corrente).
     * source='uploaded', spec=null (niente correzione via prompt). Render
     * immediato per contare le slide e pre-scaldare l'anteprima; invalida i derivati.
     */
    public function upload(Request $request, Lesson $lesson, SlidePreviewService $preview)
    {
        $this->authorizeOwner($lesson);
        $request->validate([
            'presentation' => ['required', 'file', 'extensions:pptx',
                'mimetypes:application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip',
                'max:51200'], // 50 MB
        ]);

        $presentation = $this->presentationFor($lesson);
        $storagePath = "lesson-presentations/{$lesson->id}/{$presentation->id}.pptx";

        // Sostituzione: via la vecchia anteprima e l'eventuale vecchio file.
        $preview->forget($presentation->file_path ?? $storagePath);
        $request->file('presentation')->storeAs(dirname($storagePath), basename($storagePath), 'local');

        $slides = $this->slideCount($preview, $storagePath);
        $presentation->update([
            'file_path' => $storagePath,
            'status' => 'ready',
            'source' => 'uploaded',
            'spec' => null, // caricata: non correggibile via prompt
            'generation_meta' => [
                'uploaded_by' => session('student_id'),
                'original_filename' => $request->file('presentation')->getClientOriginalName(),
                'uploaded_at' => now()->toIso8601String(),
                'slides' => $slides,
            ],
        ]);

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Presentazione caricata.');
    }

    /** S3 — elimina la presentazione: record + .pptx + cache anteprima + derivati. */
    public function destroy(Lesson $lesson, SlidePreviewService $preview)
    {
        $this->authorizeOwner($lesson);
        $presentation = $lesson->presentations()->latest()->first();
        abort_unless($presentation, 404);

        if ($presentation->file_path) {
            $preview->purge($presentation->file_path);
            Storage::disk('local')->delete($presentation->file_path);
        }
        // GANCIO feature video: qui andranno eliminati i derivati video/audio.
        $presentation->delete();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Presentazione eliminata.');
    }

    /** Conta le slide rendendo l'anteprima; 0 se il render fallisce (download resta ok). */
    private function slideCount(SlidePreviewService $preview, string $storagePath): int
    {
        try {
            return count($preview->imagesFor($storagePath));
        } catch (\Throwable $e) {
            return 0;
        }
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

    /**
     * S1 — anteprima: serve la slide n (1-based) come PNG. Render lazy + cache
     * (SlidePreviewService). Storage privato, mai URL diretto.
     */
    public function previewImage(Lesson $lesson, int $n, SlidePreviewService $preview)
    {
        $this->authorizeOwner($lesson);
        $presentation = $lesson->presentations()->where('status', 'ready')->latest()->first();

        abort_unless($presentation && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $images = $preview->imagesFor($presentation->file_path);
        $relPath = $images[$n - 1] ?? abort(404);

        return response()->file(Storage::disk('local')->path($relPath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
