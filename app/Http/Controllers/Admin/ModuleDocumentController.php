<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateModuleDocumentJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Documento PDF di un MODULO di corso Officina (P29): generazione/rigenerazione/
// stato/download lato admin. Gemello di ModulePresentationController (P28). File
// da storage PRIVATO (mai URL diretto). Un documento per modulo (rigenera =
// sovrascrive). Auth: gruppo admin (admin.auth).
class ModuleDocumentController extends Controller
{
    private function ensureInCourse(Course $course, Module $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    /** Riga documento del modulo (singola, riusata su rigenerazione). */
    private function documentFor(Module $module): ModuleDocument
    {
        return ModuleDocument::firstOrCreate(
            ['module_id' => $module->id],
            ['status' => 'pending']
        );
    }

    public function generate(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        abort_unless(trim((string) $module->content) !== '', 422,
            'Aggiungi prima il contenuto del modulo: il documento si genera dal corpo del modulo.');

        $document = $this->documentFor($module);

        // Anti-doppio-submit (server): già in corso → non ridispatcha.
        if ($document->status === 'generating') {
            return back()->with('success', 'Generazione documento già in corso.');
        }

        $document->update(['status' => 'generating']);
        GenerateModuleDocumentJob::dispatch($document->id)->afterResponse();

        return back()->with('success', 'Generazione documento avviata. Sarà pronto a breve.');
    }

    /** Rigenera: sovrascrive il documento esistente (conferma lato UI). */
    public function regenerate(Course $course, Module $module)
    {
        return $this->generate($course, $module);
    }

    public function status(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $document = $module->document()->first();

        return response()->json([
            'status' => $document?->status ?? 'none',
            'failure_reason' => $document?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    public function download(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $document = $module->document()->where('status', 'ready')->first();

        abort_unless($document && $document->file_path
            && Storage::disk('local')->exists($document->file_path), 404);

        $filename = $document->generation_meta['filename'] ?? (Str::slug($module->title) . '.pdf');

        // SOLO via controller: storage privato, mai URL diretto.
        return response()->download(Storage::disk('local')->path($document->file_path), $filename);
    }
}
