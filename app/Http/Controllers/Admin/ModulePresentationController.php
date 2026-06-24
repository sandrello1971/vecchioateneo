<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateModulePresentationJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Services\Schola\SlidePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Presentazione .pptx di un MODULO di corso Officina (P28): generazione/
// rigenerazione/stato/download lato admin. Gemello di Docente\LessonPresentationController.
// File da storage PRIVATO (mai URL diretto). Una presentazione per modulo
// (rigenera = sovrascrive). Auth: gruppo admin (admin.auth).
class ModulePresentationController extends Controller
{
    private function ensureInCourse(Course $course, Module $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    /** Riga presentazione del modulo (singola, riusata su rigenerazione). */
    private function presentationFor(Module $module): ModulePresentation
    {
        return ModulePresentation::firstOrCreate(
            ['module_id' => $module->id],
            ['status' => 'pending']
        );
    }

    public function generate(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        abort_unless(trim((string) $module->content) !== '', 422,
            'Aggiungi prima il contenuto del modulo: la presentazione si genera dal corpo del modulo.');

        $presentation = $this->presentationFor($module);

        // Anti-doppio-submit (server): già in corso → non ridispatcha.
        if ($presentation->status === 'generating') {
            return back()->with('success', 'Generazione presentazione già in corso.');
        }

        $presentation->update(['status' => 'generating']);
        GenerateModulePresentationJob::dispatch($presentation->id)->afterResponse();

        return back()->with('success', 'Generazione presentazione avviata. Sarà pronta a breve.');
    }

    /** Rigenera: sovrascrive la presentazione esistente (conferma lato UI). */
    public function regenerate(Course $course, Module $module)
    {
        return $this->generate($course, $module);
    }

    /**
     * S2 — correzione via prompt. Solo presentazioni con spec persistita
     * (generate dal sistema). Async: dispatcha il job con l'istruzione.
     */
    public function edit(Request $request, Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $data = $request->validate(['instruction' => 'required|string|max:2000']);

        $presentation = $module->presentation()->first();
        abort_unless($presentation && $presentation->status === 'ready' && !empty($presentation->spec), 422,
            'Questa presentazione non è correggibile via prompt: rigenerala dal sistema per abilitarla.');

        // Anti-doppio-submit (server): già in corso → non ridispatcha.
        if ($presentation->status === 'generating') {
            return back()->with('success', 'Correzione già in corso.');
        }

        $presentation->update(['status' => 'generating']);
        GenerateModulePresentationJob::dispatch($presentation->id, $data['instruction'])->afterResponse();

        return back()->with('success', 'Correzione avviata. Le slide saranno aggiornate a breve.');
    }

    public function status(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $presentation = $module->presentation()->first();

        return response()->json([
            'status' => $presentation?->status ?? 'none',
            'failure_reason' => $presentation?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    public function download(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $presentation = $module->presentation()->where('status', 'ready')->first();

        abort_unless($presentation && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $filename = $presentation->generation_meta['filename'] ?? (Str::slug($module->title) . '.pptx');

        // SOLO via controller: storage privato, mai URL diretto.
        return response()->download(Storage::disk('local')->path($presentation->file_path), $filename);
    }

    /**
     * S1 — anteprima: serve la slide n (1-based) come PNG. Render lazy + cache
     * (SlidePreviewService). Storage privato, mai URL diretto.
     */
    public function previewImage(Course $course, Module $module, int $n, SlidePreviewService $preview)
    {
        $this->ensureInCourse($course, $module);
        $presentation = $module->presentation()->where('status', 'ready')->first();

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
