<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLessonPresentationJob;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Services\Schola\SlidePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Presentazione .pptx di una lezione (P21) lato docente. Solo il proprietario.
// File da storage PRIVATO, mai URL diretto.
//
// BI-VERSIONE (pubblicazione): una lezione può avere DUE record:
// - 1 PUBBLICATA (published_at valorizzato): è ciò che vedono gli studenti;
// - 1 BOZZA (published_at null): su cui il formatore lavora (genera/corregge/carica).
// generate/regenerate/edit/upload operano sempre sulla BOZZA, senza toccare la
// pubblicata. publish() promuove la bozza ed elimina la vecchia pubblicata.
class LessonPresentationController extends Controller
{
    private function authorizeOwner(Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === session('student_id'), 403);
    }

    /** Bozza corrente (in lavorazione), o null. */
    private function currentDraft(Lesson $lesson): ?LessonPresentation
    {
        return $lesson->presentations()->draft()->latest()->first();
    }

    /** Versione pubblicata corrente (visibile agli studenti), o null. */
    private function currentPublished(Lesson $lesson): ?LessonPresentation
    {
        return $lesson->presentations()->published()->latest('published_at')->first();
    }

    /** Bozza su cui lavorare: riusa quella esistente o ne crea una nuova. */
    private function draftFor(Lesson $lesson): LessonPresentation
    {
        return $this->currentDraft($lesson)
            ?? $lesson->presentations()->create(['status' => 'pending']);
    }

    public function generate(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        abort_unless($lesson->generation_status === 'ready' && !empty($lesson->content), 422,
            'Componi prima il corpo della lezione: la presentazione si genera da una lezione pronta.');

        $draft = $this->draftFor($lesson);

        // Anti-doppio-submit (server): già in corso → non ridispatcha.
        if ($draft->status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Generazione presentazione già in corso.');
        }

        $draft->update(['status' => 'generating']);
        GenerateLessonPresentationJob::dispatch($draft->id)->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Generazione bozza avviata. Sarà pronta a breve.');
    }

    /** Rigenera la BOZZA (non tocca la pubblicata). */
    public function regenerate(Lesson $lesson)
    {
        return $this->generate($lesson);
    }

    /**
     * S2 — correzione via prompt, sulla BOZZA. Se non c'è bozza ma esiste una
     * pubblicata correggibile, ne clona la spec in una nuova bozza (lo studente
     * continua a vedere la pubblicata finché la bozza corretta non viene pubblicata).
     */
    public function edit(Request $request, Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $data = $request->validate(['instruction' => 'required|string|max:2000']);

        $draft = $this->editableDraft($lesson);
        abort_unless($draft !== null, 422,
            'Questa presentazione non è correggibile via prompt: rigenerala dal sistema per abilitarla.');

        if ($draft->status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Correzione già in corso.');
        }

        $draft->update(['status' => 'generating']);
        GenerateLessonPresentationJob::dispatch($draft->id, $data['instruction'])->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Correzione avviata. Le slide saranno aggiornate a breve.');
    }

    /**
     * Bozza correggibile via prompt (deve avere spec):
     * - bozza esistente con spec → quella;
     * - nessuna bozza ma pubblicata con spec → clona la spec in una nuova bozza;
     * - altrimenti null (non correggibile).
     */
    private function editableDraft(Lesson $lesson): ?LessonPresentation
    {
        $draft = $this->currentDraft($lesson);
        if ($draft) {
            return !empty($draft->spec) ? $draft : null;
        }

        $published = $this->currentPublished($lesson);
        if (!$published || empty($published->spec)) {
            return null;
        }

        // Clona la spec della pubblicata in una nuova bozza; il file verrà
        // (ri)renderizzato da editSpec nel job sul path della bozza.
        $clone = $lesson->presentations()->create([
            'status' => 'ready',
            'source' => $published->source,
            'spec' => $published->spec,
            'generation_meta' => $published->generation_meta,
        ]);
        $clone->update(['file_path' => "lesson-presentations/{$lesson->id}/{$clone->id}.pptx"]);

        return $clone;
    }

    /**
     * S3 — carica una propria versione .pptx come BOZZA. source='uploaded',
     * spec=null (niente correzione via prompt). Render immediato per contare le slide.
     */
    public function upload(Request $request, Lesson $lesson, SlidePreviewService $preview)
    {
        $this->authorizeOwner($lesson);
        $request->validate([
            'presentation' => ['required', 'file', 'extensions:pptx',
                'mimetypes:application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip',
                'max:51200'], // 50 MB
        ]);

        $draft = $this->draftFor($lesson);
        $storagePath = "lesson-presentations/{$lesson->id}/{$draft->id}.pptx";

        // Sostituzione bozza: via la vecchia anteprima e l'eventuale vecchio file.
        $preview->forget($draft->file_path ?? $storagePath);
        $request->file('presentation')->storeAs(dirname($storagePath), basename($storagePath), 'local');

        $slides = $this->slideCount($preview, $storagePath);
        $draft->update([
            'file_path' => $storagePath,
            'status' => 'ready',
            'source' => 'uploaded',
            'spec' => null,
            'generation_meta' => [
                'uploaded_by' => session('student_id'),
                'original_filename' => $request->file('presentation')->getClientOriginalName(),
                'uploaded_at' => now()->toIso8601String(),
                'slides' => $slides,
            ],
        ]);

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Bozza caricata.');
    }

    /**
     * Pubblica la BOZZA pronta: published_at=now() e rimuove la vecchia pubblicata
     * (file + cache PNG + record), in transazione. Al più una pubblicata per lezione.
     */
    public function publish(Lesson $lesson, SlidePreviewService $preview)
    {
        $this->authorizeOwner($lesson);
        $draft = $this->currentDraft($lesson);
        abort_unless($draft && $draft->status === 'ready', 422, 'Nessuna bozza pronta da pubblicare.');

        $old = $this->currentPublished($lesson);

        DB::transaction(function () use ($draft, $old) {
            if ($old) {
                $old->update(['published_at' => null]); // declassa prima di eliminare (coerenza)
            }
            $draft->update(['published_at' => now()]);
        });

        // Pulizia file/cache della vecchia pubblicata FUORI dalla transazione DB.
        if ($old) {
            if ($old->file_path) {
                $preview->purge($old->file_path);
                Storage::disk('local')->delete($old->file_path);
            }
            $old->delete();
        }

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Presentazione pubblicata: ora è visibile agli studenti.');
    }

    /** Ritira la pubblicata: torna bozza (invisibile agli studenti). */
    public function unpublish(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $published = $this->currentPublished($lesson);
        abort_unless($published, 404);

        $published->update(['published_at' => null]);

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Presentazione ritirata: non è più visibile agli studenti.');
    }

    /** Elimina la BOZZA se presente, altrimenti la pubblicata: record + file + cache. */
    public function destroy(Lesson $lesson, SlidePreviewService $preview)
    {
        $this->authorizeOwner($lesson);
        $presentation = $this->currentDraft($lesson) ?? $this->currentPublished($lesson);
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

    /** Stato della BOZZA in lavorazione (per il polling di generazione/correzione). */
    public function status(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $presentation = $this->currentDraft($lesson) ?? $this->currentPublished($lesson);

        return response()->json([
            'status' => $presentation?->status ?? 'none',
            'failure_reason' => $presentation?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    /** Download lato docente: bozza se presente, altrimenti pubblicata. */
    public function download(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $presentation = $this->currentDraft($lesson) ?? $this->currentPublished($lesson);

        abort_unless($presentation && $presentation->status === 'ready' && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $filename = $presentation->generation_meta['filename'] ?? (Str::slug($lesson->title) . '.pptx');

        // SOLO via controller: storage privato, mai URL diretto.
        return response()->download(Storage::disk('local')->path($presentation->file_path), $filename);
    }

    /**
     * S1 — anteprima: serve la slide n (1-based) come PNG. ?version=published|draft
     * (default: bozza se presente, altrimenti pubblicata). Render lazy + cache.
     */
    public function previewImage(Request $request, Lesson $lesson, int $n, SlidePreviewService $preview)
    {
        $this->authorizeOwner($lesson);

        $presentation = $request->query('version') === 'published'
            ? $this->currentPublished($lesson)
            : ($this->currentDraft($lesson) ?? $this->currentPublished($lesson));

        abort_unless($presentation && $presentation->status === 'ready' && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $images = $preview->imagesFor($presentation->file_path);
        $relPath = $images[$n - 1] ?? abort(404);

        return response()->file(Storage::disk('local')->path($relPath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
