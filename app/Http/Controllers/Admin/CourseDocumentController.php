<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCourseDocumentJob;
use App\Models\Course;
use App\Models\CourseDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Documento PDF dell'INTERO corso Officina (P29 Fase 2): generazione/rigenerazione/
// stato/download lato admin. Gemello di ModuleDocumentController, a livello corso.
// File da storage PRIVATO (mai URL diretto). Un documento per corso (rigenera =
// sovrascrive). Auth: gruppo admin (admin.auth).
class CourseDocumentController extends Controller
{
    /** Riga documento del corso (singola, riusata su rigenerazione). */
    private function documentFor(Course $course): CourseDocument
    {
        return CourseDocument::firstOrCreate(
            ['course_id' => $course->id],
            ['status' => 'pending']
        );
    }

    /** True se almeno un modulo ha contenuto reale (corpo del documento-corso). */
    private function hasContent(Course $course): bool
    {
        return $course->modules()
            ->get(['content'])
            ->contains(fn ($m) => trim(strip_tags((string) $m->content)) !== '');
    }

    public function generate(Course $course)
    {
        abort_unless($this->hasContent($course), 422,
            'Nessun modulo con contenuto: il documento del corso si genera dal corpo dei moduli.');

        $document = $this->documentFor($course);

        // Anti-doppio-submit (server): già in corso → non ridispatcha.
        if ($document->status === 'generating') {
            return back()->with('success', 'Generazione documento del corso già in corso.');
        }

        $document->update(['status' => 'generating']);
        GenerateCourseDocumentJob::dispatch($document->id)->afterResponse();

        return back()->with('success', 'Generazione documento del corso avviata. Sarà pronto a breve.');
    }

    /** Rigenera: sovrascrive il documento esistente (conferma lato UI). */
    public function regenerate(Course $course)
    {
        return $this->generate($course);
    }

    public function status(Course $course)
    {
        $document = $course->document()->first();

        return response()->json([
            'status' => $document?->status ?? 'none',
            'failure_reason' => $document?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    public function download(Course $course)
    {
        $document = $course->document()->where('status', 'ready')->first();

        abort_unless($document && $document->file_path
            && Storage::disk('local')->exists($document->file_path), 404);

        $filename = $document->generation_meta['filename'] ?? (Str::slug($course->name) . '.pdf');

        // SOLO via controller: storage privato, mai URL diretto.
        return response()->download(Storage::disk('local')->path($document->file_path), $filename);
    }
}
