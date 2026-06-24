<?php

namespace App\Services;

use App\Models\BrandProfile;
use App\Models\ModuleDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * P29 Fase 1 — genera il documento PDF di un MODULO di corso Officina.
 *
 * Sorgente = SOLO module.content (HTML semantico); renderer = CourseSourcePdfBuilder
 * (TCPDF, brand GLITCH di piattaforma — i corsi Officina non hanno scuola). Il file
 * vive in storage PRIVATO. Registra content_hash per lo STALE (isStale() lo
 * confronta con l'hash corrente): NON si rigenera in automatico, solo si marca obsoleto.
 *
 * Gemello concettuale di LessonPresentationService::buildForModule (P28), ma per il PDF.
 */
class ModuleDocumentService
{
    public function __construct(private CourseSourcePdfBuilder $builder) {}

    /**
     * P29 Fase 3 — generazione ON-ACCESS lato studente/instructor: garantisce un
     * documento pronto e ALLINEATO al contenuto, generandolo al volo se manca,
     * non è ready o è stale. Un Cache::lock atomico serializza la generazione: il
     * primo accesso genera per tutti (cache condivisa), gli altri attendono e
     * trovano il file pronto — niente doppia generazione (anti-race). Il PDF è
     * in-process e veloce (TCPDF, no LLM) → sincrono, niente polling.
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException se il lock non si acquisisce in tempo
     * @return ModuleDocument pronto (status=ready, file presente)
     */
    public function ensureReadyAndFresh(ModuleDocument $md): ModuleDocument
    {
        // Fast-path: già pronto e allineato → nessun lavoro, nessun lock.
        if ($md->status === 'ready' && !$md->isStale()) {
            return $md;
        }

        // Lock per-modulo (TTL ampio: il build è breve). block(N) = attende fino a N s.
        return Cache::lock("module-document:{$md->module_id}", 60)->block(30, function () use ($md) {
            $md->refresh();
            // Ricontrollo dentro il lock: un altro accesso può aver già generato.
            if ($md->status === 'ready' && !$md->isStale()) {
                return $md;
            }

            return $this->buildDocumentForModule($md);
        });
    }

    /**
     * Costruisce il PDF del modulo, lo salva in storage privato e aggiorna il
     * ModuleDocument (file_path + content_hash + status ready). Content vuoto →
     * RuntimeException pulito, nessun file scritto, modello intatto.
     *
     * @return ModuleDocument il documento aggiornato (fresh)
     */
    public function buildDocumentForModule(ModuleDocument $md): ModuleDocument
    {
        $module = $md->module()->with('course')->first();
        if (!$module) {
            throw new RuntimeException('Modulo del documento non trovato.');
        }

        $content = trim((string) $module->content);
        if ($content === '') {
            // Gate: niente corpo → errore pulito PRIMA di toccare modello/storage.
            throw new RuntimeException('Il modulo non ha un corpo da trasformare in documento.');
        }

        $oldPath = $md->file_path;
        $md->update(['status' => 'generating']);

        // Corsi Officina: nessuna scuola → brand di piattaforma (GLITCH). Stessa
        // fonte di tema delle slide P27/P28 (resolvedTheme). Il giorno di Schola un
        // service analogo passerà BrandProfile::forSchool($lesson->teacher->school):
        // il renderer è agnostico, eredita il tema senza modifiche.
        $theme = BrandProfile::forPlatform()->resolvedTheme();
        $pdfBytes = $this->builder->buildFromHtml($content, [
            'title' => (string) $module->title,
            'subtitle' => (string) ($module->course?->name ?? ''),
        ], $theme);

        $path = "module-documents/{$module->id}/{$md->id}.pdf";
        Storage::disk('local')->put($path, $pdfBytes);

        // Rigenerazione: rimuove il file precedente se sostituito.
        if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        $md->update([
            'file_path' => $path,
            'status' => 'ready',
            'content_hash' => $module->currentContentHash(), // segnale di stale
            'generation_meta' => [
                'blocks' => $this->builder->lastRenderedBlocks,
                'pages' => $this->builder->lastPageCount,
                'filename' => Str::slug($module->title) . '.pdf',
            ],
        ]);

        return $md->fresh();
    }
}
