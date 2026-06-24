<?php

namespace App\Services;

use App\Models\BrandProfile;
use App\Models\CourseDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * P29 Fase 2 — genera il documento PDF dell'INTERO corso Officina.
 *
 * Sorgente = i module.content dei moduli ORDINATI per sort_order, concatenati in
 * un unico PDF (header corso + una sezione per modulo). Renderer theme-agnostic
 * (CourseSourcePdfBuilder) con brand di piattaforma (GLITCH). content_hash =
 * hash AGGREGATO del corso, per lo stale. Materials esclusi. Gemello di
 * ModuleDocumentService ma a livello corso.
 */
class CourseDocumentService
{
    public function __construct(private CourseSourcePdfBuilder $builder) {}

    /**
     * P29 Fase 3 — generazione ON-ACCESS della dispensa di corso (gemella di
     * ModuleDocumentService::ensureReadyAndFresh). Lock atomico per-corso: il
     * primo accesso genera per tutti, gli altri attendono e trovano il file
     * pronto. Sincrono (TCPDF, no LLM).
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException
     * @return CourseDocument pronto (status=ready, file presente)
     */
    public function ensureReadyAndFresh(CourseDocument $cd): CourseDocument
    {
        if ($cd->status === 'ready' && !$cd->isStale()) {
            return $cd;
        }

        return Cache::lock("course-document:{$cd->course_id}", 60)->block(30, function () use ($cd) {
            $cd->refresh();
            if ($cd->status === 'ready' && !$cd->isStale()) {
                return $cd;
            }

            return $this->buildDocumentForCourse($cd);
        });
    }

    /**
     * Costruisce il PDF del corso, lo salva in storage privato e aggiorna il
     * CourseDocument (file_path + content_hash aggregato + status ready). Nessun
     * modulo con contenuto → RuntimeException pulito, nessun file, modello intatto.
     *
     * @return CourseDocument il documento aggiornato (fresh)
     */
    public function buildDocumentForCourse(CourseDocument $cd): CourseDocument
    {
        $course = $cd->course()->first();
        if (!$course) {
            throw new RuntimeException('Corso del documento non trovato.');
        }

        // Moduli ordinati con contenuto reale → sezioni del PDF.
        $sections = $course->modules()
            ->orderBy('sort_order')
            ->get(['id', 'sort_order', 'title', 'content'])
            ->map(fn ($m) => ['title' => (string) $m->title, 'html' => (string) $m->content])
            ->filter(fn ($s) => trim(strip_tags($s['html'])) !== '')
            ->values()
            ->all();

        if ($sections === []) {
            // Gate: nessun modulo con contenuto → errore pulito PRIMA di toccare modello/storage.
            throw new RuntimeException('Il corso non ha moduli con contenuto da trasformare in documento.');
        }

        $oldPath = $cd->file_path;
        $cd->update(['status' => 'generating']);

        // Corsi Officina: nessuna scuola → brand di piattaforma (GLITCH). Stessa
        // fonte di tema delle slide P27/P28 e del documento-modulo (Fase 1).
        $theme = BrandProfile::forPlatform()->resolvedTheme();
        $pdfBytes = $this->builder->buildFromSections($sections, [
            'title' => (string) $course->name,
            'subtitle' => trim((string) ($course->short_description ?? '')),
        ], $theme);

        $path = "course-documents/{$course->id}/{$cd->id}.pdf";
        Storage::disk('local')->put($path, $pdfBytes);

        if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        $cd->update([
            'file_path' => $path,
            'status' => 'ready',
            'content_hash' => $course->currentContentHash(), // hash aggregato → stale
            'generation_meta' => [
                'blocks' => $this->builder->lastRenderedBlocks,
                'pages' => $this->builder->lastPageCount,
                'modules' => count($sections),
                'filename' => Str::slug($course->name) . '.pdf',
            ],
        ]);

        return $cd->fresh();
    }
}
