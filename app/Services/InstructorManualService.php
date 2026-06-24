<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\DocumentRag;
use App\Models\Material;
use App\Models\UpdateProposal;
use App\Services\Freshness\CoordinatedMatchService;
use App\Services\CourseDocumentParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstructorManualService
{
    /**
     * Esito dell'ultima sincronizzazione di course_sources, per il feedback UI (F-c). Forme:
     *  ['status'=>'generated','version'=>'2.0','blocks'=>9,'invalidated'=>N]
     *  ['status'=>'empty']                    — 0 blocchi estratti
     *  ['status'=>'awaiting_confirmation']     — corso con storia, sovrascrittura non confermata
     *  ['status'=>'failed','error'=>'...']     — eccezione (import comunque riuscito)
     * null = estrazione non eseguita in questa chiamata.
     */
    public ?array $lastSourceSync = null;

    public function __construct(
        protected RagService $rag,
        protected InstructorManualSplitterService $splitter,
        protected CourseSourceExtractor $sourceExtractor,
        protected CoordinatedMatchService $coordinatedMatch,
        protected CourseDocumentParser $parser
    ) {}

    public function import(
        string $sourcePath,
        Course $course,
        string $title,
        ?string $description = null,
        ?Material $existing = null,
        bool $confirmOverwrite = false
    ): Material {
        if (!file_exists($sourcePath)) {
            throw new \InvalidArgumentException("File non trovato: {$sourcePath}");
        }

        // Dispatcher per estensione (docx→pandoc docx, md→pandoc gfm): riusa il
        // convertitore del parser, deduplicando il vecchio convertDocxToHtml privato.
        $html = $this->parser->convertManualToHtml($sourcePath);
        if (trim($html) === '') {
            throw new \RuntimeException('Conversione pandoc fallita (HTML vuoto)');
        }

        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'docx');
        $filename = Str::slug($title) . '-' . time() . '.' . $ext;
        $storedPath = "instructor-manuals/{$course->slug}/{$filename}";

        Storage::disk('local')->put($storedPath, file_get_contents($sourcePath));
        $fileSize = filesize($sourcePath);

        $oldPath = $existing?->file_path;

        $data = [
            'course_id'           => $course->id,
            'module_id'           => null,
            'title'               => $title,
            'description'         => $description ?? "Manuale riservato ai docenti del corso {$course->name}",
            'file_path'           => $storedPath,
            'file_type'           => $ext,
            'file_size'           => $fileSize,
            'content_html'        => $html,
            'sort_order'          => $existing->sort_order ?? 0,
            'is_downloadable'     => true,
            'is_instructor_only'  => true,
        ];

        if ($existing) {
            $existing->update($data);
            $material = $existing->fresh();
        } else {
            $material = Material::create($data);
        }

        if ($oldPath && $oldPath !== $storedPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->reindexInRag($material);
        $this->splitter->split($material);

        // F-a/F-b — il corso diventa "freshness-ready": dallo STESSO docx appena persistito
        // si genera il sorgente strutturato (course_sources), accanto alle sezioni del
        // formatore. Additivo e non bloccante: vedi syncStructuredSource(). Su un corso con
        // storia di apply serve $confirmOverwrite (barriera strutturale, non solo UI).
        $this->syncStructuredSource($course, Storage::disk('local')->path($storedPath), $confirmOverwrite);

        return $material;
    }

    public function regenerateHtml(Material $material, bool $confirmOverwrite = false): Material
    {
        if (!$material->file_path || !Storage::disk('local')->exists($material->file_path)) {
            throw new \RuntimeException('File .docx non presente su disco: ' . $material->file_path);
        }

        $absolutePath = Storage::disk('local')->path($material->file_path);
        $html = $this->parser->convertManualToHtml($absolutePath);
        if (trim($html) === '') {
            throw new \RuntimeException('Conversione pandoc fallita (HTML vuoto)');
        }

        $material->update(['content_html' => $html]);
        $material = $material->fresh();

        $this->reindexInRag($material);
        $this->splitter->split($material);

        // F-a/F-b — ri-genera anche il sorgente strutturato dallo stesso .docx esistente.
        $this->syncStructuredSource($material->course, $absolutePath, $confirmOverwrite);

        return $material;
    }

    public function delete(Material $material): void
    {
        DocumentRag::where('course_id', $material->course_id)
            ->where('is_instructor_only', true)
            ->where('title', $material->title)
            ->delete();

        if ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }
        $material->delete();
    }

    private function reindexInRag(Material $material): void
    {
        if (!$material->is_instructor_only || empty($material->content_html)) {
            return;
        }

        DocumentRag::where('course_id', $material->course_id)
            ->where('is_instructor_only', true)
            ->where('title', $material->title)
            ->delete();

        $plainText = html_entity_decode(
            strip_tags($material->content_html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $plainText = preg_replace('/\s+/', ' ', $plainText);
        $plainText = trim($plainText);

        if (mb_strlen($plainText) < 200) return;

        $this->rag->indexDocument(
            $plainText,
            $material->title,
            $material->course_id,
            null,
            $material->file_path,
            true
        );
    }

    public function uploadAndImport(
        UploadedFile $file,
        Course $course,
        string $title,
        ?string $description = null,
        ?Material $existing = null,
        bool $confirmOverwrite = false
    ): Material {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['docx', 'doc', 'md', 'markdown'], true)) {
            throw new \InvalidArgumentException('Solo file .docx, .doc, .md o .markdown sono supportati');
        }

        // I file temporanei PHP non hanno estensione: copiamo in un temp CON
        // l'estensione corretta, così il dispatcher per estensione (HTML e
        // course_sources) sceglie il ramo giusto (docx vs markdown).
        $tempPath = tempnam(sys_get_temp_dir(), 'instr_') . '.' . $ext;
        copy($file->getRealPath(), $tempPath);

        try {
            return $this->import($tempPath, $course, $title, $description, $existing, $confirmOverwrite);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * F-a/F-b — Genera il sorgente strutturato (course_sources) dal .docx del manuale formatore.
     *
     * Append-only e non distruttivo (mai DELETE: vecchie versioni e formatore_snapshots restano):
     *  - corso senza course_sources         → crea v1.0;
     *  - corso PRISTINO (con sorgente, ma   → bump MAGGIORE (es. "1.0"→"2.0", "2.2"→"3.0"):
     *    senza storia di apply dell'agente)    nuova riga che diventa corrente, le vecchie restano.
     *  - corso CON storia di apply          → BARRIERA conferma (F-b): senza $confirmOverwrite
     *    (course_changelog kind=apply,         NON estrae e NON sovrascrive (il manuale si carica
     *    content_source=instructor)            comunque); con conferma, estrae + bump maggiore.
     *
     * Invalidazione ancore (F-a e F-b): ogni volta che la ri-estrazione SOSTITUISCE una versione
     * esistente (bump, non primo v1.0), i block_id cambiano → le proposte instructor aperte
     * (pending/approved) vengono rifiutate, propagando la cascata B-b sulle figlie studente.
     *
     * 0 blocchi (heading non riconosciuti) → non genera il sorgente, segnala soltanto.
     * Additività assoluta: qualsiasi errore è catturato e loggato — l'import del manuale
     * (Material + sezioni) NON deve mai fallire per colpa dell'estrazione.
     */
    private function syncStructuredSource(Course $course, string $docxAbsolutePath, bool $confirmOverwrite = false): void
    {
        $this->lastSourceSync = null;
        try {
            // Storia di apply dell'agente = segnale affidabile (changelog instructor).
            $hasApplyHistory = CourseChangelog::where('course_id', $course->id)
                ->where('kind', 'apply')
                ->where('content_source', 'instructor')
                ->exists();

            // F-b — barriera strutturale: su un corso con storia serve conferma esplicita,
            // perché la nuova versione (dal docx) NON contiene gli aggiornamenti applicati.
            if ($hasApplyHistory && !$confirmOverwrite) {
                $this->lastSourceSync = ['status' => 'awaiting_confirmation'];
                Log::warning('[freshness-ready] corso con aggiornamenti agente: estrazione in attesa di conferma (F-b)', [
                    'course_id' => $course->id,
                ]);
                return;
            }

            // Dispatcher per estensione: il formatore .md genera course_sources
            // ESATTAMENTE come il docx (mapAst condiviso) → la Freshness non si rompe.
            $srcExt = strtolower(pathinfo($docxAbsolutePath, PATHINFO_EXTENSION));
            $result = in_array($srcExt, ['md', 'markdown'], true)
                ? $this->sourceExtractor->extractFromMarkdown($docxAbsolutePath)
                : $this->sourceExtractor->extractFromDocx($docxAbsolutePath);
            $blocks = $result['blocks'] ?? [];
            if (empty($blocks)) {
                $this->lastSourceSync = ['status' => 'empty'];
                Log::warning('[freshness-ready] sorgente strutturato non generato (0 blocchi estratti)', [
                    'course_id' => $course->id,
                ]);
                return;
            }

            // Versione corrente = ultima riga per created_at (tie-break id), come il resto del codice.
            $current = CourseSource::where('course_id', $course->id)
                ->orderByDesc('created_at')->orderByDesc('id')->first();
            $version = $current === null ? '1.0' : $this->nextMajorVersion($current->version);

            CourseSource::create([
                'course_id' => $course->id,
                'version' => $version,
                'blocks' => $blocks,
            ]);

            Log::info('[freshness-ready] course_sources generato dall\'import del manuale', [
                'course_id' => $course->id, 'version' => $version, 'blocks' => count($blocks),
            ]);

            // Invalidazione delle ancore: la ri-estrazione RIGENERA i block_id. Ogni volta che
            // SOSTITUISCE una versione precedente (cioè non al primo v1.0), le proposte instructor
            // ancora aperte sono ancorate a block_id che non esistono più → rifiutate + cascata B-b.
            // Il trigger è il rimpiazzo della versione (block_id nuovi), NON la storia di apply:
            // vale sia per il bump pristino (F-a, 1.0→2.0) sia per la sovrascrittura confermata (F-b).
            // Al primo import ($current === null) non c'erano ancore da invalidare.
            $invalidated = $current !== null
                ? $this->rejectProposalsOrphanedByReextraction($course, $version)
                : 0;

            $this->lastSourceSync = [
                'status' => 'generated', 'version' => $version,
                'blocks' => count($blocks), 'invalidated' => $invalidated,
            ];
        } catch (\Throwable $e) {
            $this->lastSourceSync = ['status' => 'failed', 'error' => $e->getMessage()];
            Log::warning('[freshness-ready] estrazione course_sources fallita (non bloccante per l\'import)', [
                'course_id' => $course->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * F-b — Rifiuta le proposte instructor APERTE (pending/approved) di un corso il cui sorgente
     * è stato ri-estratto dal docx: i nuovi block_id invalidano le ancore. Motivo tracciato in
     * apply_error. Per ogni proposta padre, riusa la gestione orfani B-b (orphanChildrenOf):
     * figlie studente pending/matched → rejected+orphaned; figlie applied → restano+orphaned+segnalate.
     */
    private function rejectProposalsOrphanedByReextraction(Course $course, string $newVersion): int
    {
        $reason = "ancora invalidata da ri-estrazione manuale v{$newVersion}";

        $open = UpdateProposal::where('course_id', $course->id)
            ->where('content_source', 'instructor')
            ->whereIn('status', ['pending', 'approved'])
            ->get();

        foreach ($open as $proposal) {
            $proposal->update([
                'status' => 'rejected',
                'apply_error' => $reason,
                'reviewed_at' => $proposal->reviewed_at ?? now(),
            ]);
            // Cascata B-b: gestione orfani delle figlie studente coordinate (riuso, non duplico).
            $this->coordinatedMatch->orphanChildrenOf($proposal, $reason);
        }

        return $open->count();
    }

    /** Bump MAGGIORE come stringa: "1.0"→"2.0", "2.2"→"3.0", "2"→"3.0". */
    private function nextMajorVersion(string $v): string
    {
        if (preg_match('/^(\d+)(?:\.\d+)?$/', $v, $m)) {
            return ((int) $m[1] + 1) . '.0';
        }
        throw new \RuntimeException("Versione non incrementabile in modo deterministico: {$v}");
    }

}
