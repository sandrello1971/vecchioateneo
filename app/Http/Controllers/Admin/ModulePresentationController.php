<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateModulePresentationJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Services\Schola\SlidePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Presentazione .pptx di un MODULO di corso Officina (P28 / Blocco B) lato admin.
// File da storage PRIVATO, mai URL diretto.
//
// BI-VERSIONE (pubblicazione): un modulo può avere DUE record:
// - 1 PUBBLICATA (published_at valorizzato): è ciò che vedono i corsisti;
// - 1 BOZZA (published_at null): su cui l'admin lavora (genera/corregge/carica).
// generate/regenerate/edit/upload operano sulla BOZZA; publish() promuove la bozza
// ed elimina la vecchia pubblicata. Gemello di Docente\LessonPresentationController.
class ModulePresentationController extends Controller
{
    private function ensureInCourse(Course $course, Module $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    /** Bozza corrente (in lavorazione), o null. */
    private function currentDraft(Module $module): ?ModulePresentation
    {
        return $module->presentations()->draft()->latest()->first();
    }

    /** Versione pubblicata corrente (visibile ai corsisti), o null. */
    private function currentPublished(Module $module): ?ModulePresentation
    {
        return $module->presentations()->published()->latest('published_at')->first();
    }

    /** Bozza su cui lavorare: riusa quella esistente o ne crea una nuova. */
    private function draftFor(Module $module): ModulePresentation
    {
        return $this->currentDraft($module)
            ?? $module->presentations()->create(['status' => 'pending']);
    }

    public function generate(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        abort_unless(trim((string) $module->content) !== '', 422,
            'Aggiungi prima il contenuto del modulo: la presentazione si genera dal corpo del modulo.');

        $draft = $this->draftFor($module);

        if ($draft->status === 'generating') {
            return back()->with('success', 'Generazione bozza già in corso.');
        }

        $draft->update(['status' => 'generating']);
        GenerateModulePresentationJob::dispatch($draft->id)->afterResponse();

        return back()->with('success', 'Generazione bozza avviata. Sarà pronta a breve.');
    }

    /** Rigenera la BOZZA (non tocca la pubblicata). */
    public function regenerate(Course $course, Module $module)
    {
        return $this->generate($course, $module);
    }

    /**
     * S2 — correzione via prompt, sulla BOZZA. Se non c'è bozza ma esiste una
     * pubblicata correggibile, ne clona la spec in una nuova bozza.
     */
    public function edit(Request $request, Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $data = $request->validate(['instruction' => 'required|string|max:2000']);

        $draft = $this->editableDraft($module);
        abort_unless($draft !== null, 422,
            'Questa presentazione non è correggibile via prompt: rigenerala dal sistema per abilitarla.');

        if ($draft->status === 'generating') {
            return back()->with('success', 'Correzione già in corso.');
        }

        $draft->update(['status' => 'generating']);
        GenerateModulePresentationJob::dispatch($draft->id, $data['instruction'])->afterResponse();

        return back()->with('success', 'Correzione avviata. Le slide saranno aggiornate a breve.');
    }

    /**
     * Bozza correggibile (deve avere spec): bozza esistente con spec → quella;
     * nessuna bozza ma pubblicata con spec → clona la spec in una nuova bozza;
     * altrimenti null.
     */
    private function editableDraft(Module $module): ?ModulePresentation
    {
        $draft = $this->currentDraft($module);
        if ($draft) {
            return !empty($draft->spec) ? $draft : null;
        }

        $published = $this->currentPublished($module);
        if (!$published || empty($published->spec)) {
            return null;
        }

        $clone = $module->presentations()->create([
            'status' => 'ready',
            'source' => $published->source,
            'spec' => $published->spec,
            'generation_meta' => $published->generation_meta,
        ]);
        $clone->update(['file_path' => "module-presentations/{$module->id}/{$clone->id}.pptx"]);

        return $clone;
    }

    /** S3 — carica una propria versione .pptx come BOZZA. source='uploaded', spec=null. */
    public function upload(Request $request, Course $course, Module $module, SlidePreviewService $preview)
    {
        $this->ensureInCourse($course, $module);
        $request->validate([
            'presentation' => ['required', 'file', 'extensions:pptx',
                'mimetypes:application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip',
                'max:51200'], // 50 MB
        ]);

        $draft = $this->draftFor($module);
        $storagePath = "module-presentations/{$module->id}/{$draft->id}.pptx";

        $preview->forget($draft->file_path ?? $storagePath);
        $request->file('presentation')->storeAs(dirname($storagePath), basename($storagePath), 'local');

        $slides = $this->slideCount($preview, $storagePath);
        $draft->update([
            'file_path' => $storagePath,
            'status' => 'ready',
            'source' => 'uploaded',
            'spec' => null,
            'generation_meta' => [
                'uploaded_by' => session('admin_email'),
                'original_filename' => $request->file('presentation')->getClientOriginalName(),
                'uploaded_at' => now()->toIso8601String(),
                'slides' => $slides,
            ],
        ]);

        return back()->with('success', 'Bozza caricata.');
    }

    /**
     * Pubblica la BOZZA pronta: published_at=now() e rimuove la vecchia pubblicata
     * (record + file + cache PNG), in transazione. Al più una pubblicata per modulo.
     */
    public function publish(Course $course, Module $module, SlidePreviewService $preview)
    {
        $this->ensureInCourse($course, $module);
        $draft = $this->currentDraft($module);
        abort_unless($draft && $draft->status === 'ready', 422, 'Nessuna bozza pronta da pubblicare.');

        $old = $this->currentPublished($module);

        DB::transaction(function () use ($draft, $old) {
            if ($old) {
                $old->update(['published_at' => null]);
            }
            $draft->update(['published_at' => now()]);
        });

        if ($old) {
            if ($old->file_path) {
                $preview->purge($old->file_path);
                Storage::disk('local')->delete($old->file_path);
            }
            $old->delete();
        }

        return back()->with('success', 'Presentazione pubblicata: ora è visibile ai corsisti.');
    }

    /** Ritira la pubblicata: torna bozza (invisibile ai corsisti). */
    public function unpublish(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $published = $this->currentPublished($module);
        abort_unless($published, 404);

        $published->update(['published_at' => null]);

        return back()->with('success', 'Presentazione ritirata: non è più visibile ai corsisti.');
    }

    /** Elimina la BOZZA se presente, altrimenti la pubblicata: record + file + cache. */
    public function destroy(Course $course, Module $module, SlidePreviewService $preview)
    {
        $this->ensureInCourse($course, $module);
        $presentation = $this->currentDraft($module) ?? $this->currentPublished($module);
        abort_unless($presentation, 404);

        if ($presentation->file_path) {
            $preview->purge($presentation->file_path);
            Storage::disk('local')->delete($presentation->file_path);
        }
        // GANCIO feature video: qui andranno eliminati i derivati video/audio.
        $presentation->delete();

        return back()->with('success', 'Presentazione eliminata.');
    }

    /** Conta le slide rendendo l'anteprima; 0 se il render fallisce. */
    private function slideCount(SlidePreviewService $preview, string $storagePath): int
    {
        try {
            return count($preview->imagesFor($storagePath));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Stato della BOZZA in lavorazione (per il polling di generazione/correzione). */
    public function status(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $presentation = $this->currentDraft($module) ?? $this->currentPublished($module);

        return response()->json([
            'status' => $presentation?->status ?? 'none',
            'failure_reason' => $presentation?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    /** Download lato admin: bozza se presente, altrimenti pubblicata. */
    public function download(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $presentation = $this->currentDraft($module) ?? $this->currentPublished($module);

        abort_unless($presentation && $presentation->status === 'ready' && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $filename = $presentation->generation_meta['filename'] ?? (Str::slug($module->title) . '.pptx');

        return response()->download(Storage::disk('local')->path($presentation->file_path), $filename);
    }

    /**
     * S1 — anteprima: serve la slide n (1-based) come PNG. ?version=published|draft
     * (default: bozza se presente, altrimenti pubblicata). Render lazy + cache.
     */
    public function previewImage(Request $request, Course $course, Module $module, int $n, SlidePreviewService $preview)
    {
        $this->ensureInCourse($course, $module);

        $presentation = $request->query('version') === 'published'
            ? $this->currentPublished($module)
            : ($this->currentDraft($module) ?? $this->currentPublished($module));

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
