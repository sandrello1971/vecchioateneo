<?php

namespace App\Services\Freshness;

use App\Jobs\RewriteInstructorManualJob;
use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\FormatoreSnapshot;
use App\Models\InstructorManualSection;
use App\Models\Module;
use App\Models\StudentSourceVersion;
use App\Models\UpdateProposal;
use App\Services\CourseSourcePdfBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * P25.3c — Applicazione delle proposte APPROVED al lato formatore, con versioning e
 * rollback. Variante A: tocca SOLO course_sources + instructor_manual_sections (+ PDF).
 * `modules.content` (studente) NON viene toccato (sotto-fase B).
 *
 * HITL: consuma SOLO proposte status='approved'. Nessuna pending raggiunge il contenuto.
 * Verbatim o niente: ogni proposta si applica solo se il `before` è trovato ESATTAMENTE
 * una volta sia nel blocco del sorgente sia nel contenuto formatore live; altrimenti
 * fallisce in modo pulito (apply_error) e NON viene applicata.
 *
 * Additività: non interferisce con le vie di modifica manuale di instructor_manual_sections.
 */
class ProposalApplicator
{
    public function __construct(private CourseSourcePdfBuilder $pdfBuilder) {}

    /**
     * Applica le proposte approved del corso. Crea una nuova versione di course_sources,
     * aggiorna il formatore live (con backup), scrive il changelog, rigenera il PDF.
     *
     * @return array{applied:int, failed:array<int,array{id:string,error:string}>, version_from:?string, version_to:?string}
     */
    public function apply(Course $course, bool $minorConfirmed = false): array
    {
        $result = DB::transaction(function () use ($course, $minorConfirmed) {
            // GATE SCHOLA/MINORI (P25.3e): barriera in PIÙ, non sostituisce l'HITL. Su un
            // corso audience=minor l'applicazione richiede una conferma esplicita aggiuntiva
            // (gate 2, umano) oltre alle proposte già approvate (gate 1, umano). Senza
            // conferma → bloccata in modo pulito, NESSUNA modifica.
            $audience = optional($course->freshnessConfig)->audience ?? 'adult';
            if ($audience === 'minor' && !$minorConfirmed) {
                return [
                    'applied' => 0, 'failed' => [], 'queued' => [], 'blocked' => 'minor_confirmation_required',
                    'version_from' => optional($this->latestSource($course))->version, 'version_to' => null,
                ];
            }

            $current = $this->latestSource($course);
            if (!$current) {
                throw new RuntimeException("Nessun course_sources per il corso {$course->id}: niente da applicare.");
            }

            // Solo proposte FORMATORE: lo studente ha un percorso dedicato (applyStudent),
            // così i due lati restano indipendenti (D2).
            $approved = UpdateProposal::where('course_id', $course->id)
                ->where('content_source', 'instructor')
                ->where('status', 'approved')
                ->orderBy('created_at')
                ->get();

            if ($approved->isEmpty()) {
                return ['applied' => 0, 'failed' => [], 'queued' => [], 'version_from' => $current->version, 'version_to' => null, 'blocked' => null];
            }

            $blocks = $current->blocks ?? [];
            // indice block_id → posizione
            $blockIndex = [];
            foreach ($blocks as $i => $b) {
                if (isset($b['id'])) {
                    $blockIndex[$b['id']] = $i;
                }
            }

            $sections = InstructorManualSection::where('course_id', $course->id)->get();
            $liveContent = [];   // section_id → html di lavoro (progressivamente modificato)
            $preSnapshot = [];   // section_id → html PRE-batch (per rollback), al primo tocco
            foreach ($sections as $s) {
                $liveContent[$s->id] = $s->content_html;
            }

            $appliedProposals = [];
            $failed = [];
            $queuedIds = []; // proposte da riscrivere semanticamente sul manuale (async)

            foreach ($approved as $p) {
                // P25.3f — DISACCOPPIAMENTO: sorgente e manuale formatore divergono (il sorgente
                // è una ristrutturazione LLM). Si applicano in modo INDIPENDENTE: la proposta è
                // "applicata" se ALMENO un target attecchisce. Il manuale che non combacia verbatim
                // NON blocca più: viene accodato alla riscrittura semantica (Livello 2).

                // 1) Sorgente strutturato: replace verbatim sul blocco per block_id (text o items).
                $srcOk = false;
                if (array_key_exists($p->block_id, $blockIndex)) {
                    $idx = $blockIndex[$p->block_id];
                    $srcRes = $this->replaceInSourceBlock($blocks[$idx], $p->before, $p->after);
                    if ($srcRes['ok']) {
                        $blocks[$idx] = $srcRes['block'];
                        $srcOk = true;
                    }
                }

                // 2) Manuale formatore live: il before deve essere verbatim e UNICO su tutte le sezioni.
                $manualOk = false;
                $hitSection = null;
                $totalHits = 0;
                foreach ($liveContent as $sid => $html) {
                    $c = VerbatimReplacer::countOccurrences($html, $p->before);
                    if ($c > 0) {
                        $totalHits += $c;
                        $hitSection = $sid;
                    }
                }
                if ($totalHits === 1) {
                    $liveRes = VerbatimReplacer::replaceUnique($liveContent[$hitSection], $p->before, $p->after);
                    if ($liveRes['ok']) {
                        if (!array_key_exists($hitSection, $preSnapshot)) {
                            $preSnapshot[$hitSection] = $sections->firstWhere('id', $hitSection)->content_html; // PRE-batch
                        }
                        $liveContent[$hitSection] = $liveRes['result'];
                        $manualOk = true;
                    }
                }

                // 3) Decisione: applicata se sorgente OPPURE manuale hanno attecchito.
                if (!$srcOk && !$manualOk) {
                    // Entrambi falliti → fallimento pulito (resta 'approved', scartabile a mano).
                    $this->fail($p, $totalHits === 0
                        ? 'sorgente e manuale: before non trovato (nessuna occorrenza verbatim)'
                        : "manuale: before non univoco ({$totalHits} occorrenze) e sorgente non applicabile");
                    $failed[] = ['id' => $p->id, 'error' => $p->apply_error];
                    continue;
                }

                // Stato manuale: verbatim ok / da riscrivere a parte (queued) / nessuna sezione (null).
                $manualStatus = $manualOk ? 'verbatim' : ($sections->isNotEmpty() ? 'queued' : null);
                $appliedProposals[] = [
                    'proposal' => $p,
                    'manual_status' => $manualStatus,
                    'manual_before' => $manualOk ? $p->before : null,
                    'manual_after' => $manualOk ? $p->after : null,
                    'src_ok' => $srcOk,
                ];
                if ($manualStatus === 'queued') {
                    $queuedIds[] = $p->id;
                }
            }

            if (empty($appliedProposals)) {
                // Tutte fallite: apply_error già persistito, nessun bump di versione.
                return ['applied' => 0, 'failed' => $failed, 'queued' => [], 'version_from' => $current->version, 'version_to' => null, 'blocked' => null];
            }

            // 4) Nuova versione del sorgente (la precedente resta intatta).
            $newVersion = $this->nextVersion($current->version);
            $newSource = CourseSource::create([
                'course_id' => $course->id,
                'version' => $newVersion,
                'blocks' => array_values($blocks),
            ]);

            // 5) Backup live (pre-batch) + scrittura del formatore live aggiornato (sezioni verbatim).
            foreach ($preSnapshot as $sid => $preHtml) {
                FormatoreSnapshot::create([
                    'course_id' => $course->id,
                    'course_source_id' => $newSource->id,
                    'version' => $newVersion,
                    'instructor_manual_section_id' => $sid,
                    'content_html' => $preHtml,
                ]);
                InstructorManualSection::where('id', $sid)->update(['content_html' => $liveContent[$sid]]);
            }

            // 6) Proposte → applied + changelog (audit per proposta) + esito manuale.
            foreach ($appliedProposals as $ap) {
                $p = $ap['proposal'];
                $p->update([
                    'status' => 'applied', 'applied_at' => now(), 'apply_error' => null,
                    'manual_status' => $ap['manual_status'],
                    'manual_before' => $ap['manual_before'],
                    'manual_after' => $ap['manual_after'],
                ]);
                $manualNote = $ap['manual_status'] === 'queued' ? ' [manuale: riscrittura semantica in corso]' : '';
                CourseChangelog::create([
                    'course_id' => $course->id,
                    'proposal_id' => $p->id,
                    'version_from' => $current->version,
                    'version_to' => $newVersion,
                    'kind' => 'apply',
                    'summary' => mb_substr($p->before, 0, 120) . ' → ' . mb_substr($p->after, 0, 120) . $manualNote,
                    'approved_by' => $p->reviewed_by,
                    'approved_at' => $p->reviewed_at,
                ]);
            }

            $this->regeneratePdf($course, $newSource, $newVersion);

            return ['applied' => count($appliedProposals), 'failed' => $failed, 'queued' => $queuedIds, 'version_from' => $current->version, 'version_to' => $newVersion, 'blocked' => null];
        });

        // Fuori dalla transazione (le chiamate AI sono lente e NON devono tenere il lock DB):
        // accoda la riscrittura semantica del manuale per le proposte applicate al solo sorgente.
        foreach ($result['queued'] ?? [] as $proposalId) {
            RewriteInstructorManualJob::dispatch($proposalId);
        }

        return $result;
    }

    /**
     * P25.3f — Applica al manuale formatore una sostituzione (manualBefore→manualAfter) prodotta
     * dalla riscrittura semantica async (RewriteInstructorManualJob), per i casi in cui il `before`
     * del sorgente NON era verbatim nel manuale. Verbatim o niente sulla sezione; snapshot pre-patch
     * ancorato alla versione corrente (così il rollback la ripristina). NON crea una nuova versione
     * del sorgente né un changelog 'apply' (eviterebbe collisioni con la logica di rollback):
     * l'audit della riscrittura vive su update_proposals.manual_*.
     *
     * @return array{ok:bool, reason:?string}
     */
    public function applyManualPatch(Course $course, string $sectionId, string $manualBefore, string $manualAfter): array
    {
        return DB::transaction(function () use ($course, $sectionId, $manualBefore, $manualAfter) {
            $section = InstructorManualSection::where('course_id', $course->id)->where('id', $sectionId)->first();
            if (!$section) {
                return ['ok' => false, 'reason' => 'sezione manuale non trovata'];
            }

            $res = VerbatimReplacer::replaceUnique($section->content_html, $manualBefore, $manualAfter);
            if (!$res['ok']) {
                return ['ok' => false, 'reason' => $res['reason']];
            }

            // Snapshot pre-patch ancorato alla versione corrente, SOLO se non già presente: una
            // eventuale snapshot pre-batch della stessa versione tiene già l'originale (il rollback
            // restaura dalle snapshot di quella versione → la nostra patch viene annullata con essa).
            $current = $this->latestSource($course);
            if ($current) {
                $exists = FormatoreSnapshot::where('course_id', $course->id)
                    ->where('version', $current->version)
                    ->where('instructor_manual_section_id', $section->id)
                    ->exists();
                if (!$exists) {
                    FormatoreSnapshot::create([
                        'course_id' => $course->id,
                        'course_source_id' => $current->id,
                        'version' => $current->version,
                        'instructor_manual_section_id' => $section->id,
                        'content_html' => $section->content_html, // pre-patch
                    ]);
                }
            }

            $section->update(['content_html' => $res['result']]);

            return ['ok' => true, 'reason' => null];
        });
    }

    /**
     * Rollback dell'ultima applicazione: ripristina il sorgente (nuova versione = copia
     * della versione precedente) E il contenuto formatore live (dai backup).
     *
     * @return array{rolled_back:bool, version_from:?string, version_to:?string, restored_to:?string}
     */
    public function rollback(Course $course): array
    {
        return DB::transaction(function () use ($course) {
            $latest = $this->latestSource($course);
            if (!$latest) {
                throw new RuntimeException('Nessun sorgente da cui fare rollback.');
            }

            // Changelog dell'applicazione che ha PRODOTTO la versione corrente.
            $entry = CourseChangelog::where('course_id', $course->id)
                ->where('version_to', $latest->version)
                ->where('kind', 'apply')
                ->orderByDesc('created_at')
                ->first();
            if (!$entry) {
                throw new RuntimeException("Nessuna applicazione da annullare per la versione {$latest->version}.");
            }

            $preVersion = $entry->version_from;
            $preSource = $course->sources()->where('version', $preVersion)->first();
            if (!$preSource) {
                throw new RuntimeException("Versione precedente {$preVersion} non più disponibile per il rollback.");
            }

            // Sorgente: nuova versione = copia di quella precedente (append-only).
            $newVersion = $this->nextVersion($latest->version);
            $newSource = CourseSource::create([
                'course_id' => $course->id,
                'version' => $newVersion,
                'blocks' => $preSource->blocks,
            ]);

            // Formatore live: ripristina dalle snapshot pre-applicazione.
            $snapshots = FormatoreSnapshot::where('course_id', $course->id)
                ->where('version', $latest->version)
                ->get();
            foreach ($snapshots as $snap) {
                InstructorManualSection::where('id', $snap->instructor_manual_section_id)
                    ->update(['content_html' => $snap->content_html]);
            }

            CourseChangelog::create([
                'course_id' => $course->id,
                'proposal_id' => null,
                'version_from' => $latest->version,
                'version_to' => $newVersion,
                'kind' => 'rollback',
                'summary' => "Rollback dalla versione {$latest->version} (ripristino dei contenuti di {$preVersion})",
                'approved_by' => null,
                'approved_at' => now(),
            ]);

            $this->regeneratePdf($course, $newSource, $newVersion);

            return ['rolled_back' => true, 'version_from' => $latest->version, 'version_to' => $newVersion, 'restored_to' => $preVersion];
        });
    }

    // ===================== LATO STUDENTE (P25.B-a.3) =====================
    //
    // NOTA: approccio verbatim-su-stringa che assume HTML PULITO, senza markup inline né
    // entità DENTRO le frasi (verificato sul contenuto reale). Se in futuro comparissero,
    // passare a parsing DOM (sostituzione sul text-node, non sulla stringa grezza).

    /**
     * Applica le proposte approved con content_source='student' al contenuto live degli
     * studenti (modules.content), in-place. Replace verbatim "unico o niente" sulla stringa
     * HTML del modulo. Versioning su student_source_versions (snapshot completo), rollback
     * possibile. Tocca SOLO modules.content: mai materials, mai student_canvas_data, mai
     * DELETE di moduli. Gate minori e HITL (solo approved) come il lato formatore.
     *
     * @return array{applied:int, failed:array, version_from:?string, version_to:?string, blocked:?string}
     */
    public function applyStudent(Course $course, bool $minorConfirmed = false): array
    {
        return DB::transaction(function () use ($course, $minorConfirmed) {
            $audience = optional($course->freshnessConfig)->audience ?? 'adult';
            if ($audience === 'minor' && !$minorConfirmed) {
                return ['applied' => 0, 'failed' => [], 'blocked' => 'minor_confirmation_required',
                    'version_from' => optional($this->latestStudentVersion($course))->version, 'version_to' => null];
            }

            $approved = UpdateProposal::where('course_id', $course->id)
                ->where('content_source', 'student')
                ->where('status', 'approved')
                ->orderBy('created_at')
                ->get();

            $currentVersion = optional($this->latestStudentVersion($course))->version;
            if ($approved->isEmpty()) {
                return ['applied' => 0, 'failed' => [], 'version_from' => $currentVersion, 'version_to' => null, 'blocked' => null];
            }

            $modules = $course->modules()->get()->keyBy('id');
            $original = $modules->map(fn ($m) => (string) $m->content)->all(); // id → HTML pre-applicazione
            $live = $original; // copia di lavoro

            $appliedProposals = [];
            $failed = [];
            foreach ($approved as $p) {
                $mid = $p->module_id;
                if ($mid === null || !array_key_exists($mid, $live)) {
                    $this->fail($p, 'studente: modulo non trovato nel corso');
                    $failed[] = ['id' => $p->id, 'error' => $p->apply_error];
                    continue;
                }
                $res = VerbatimReplacer::replaceUnique($live[$mid], $p->before, $p->after);
                if (!$res['ok']) {
                    $this->fail($p, 'studente: ' . $res['reason']);
                    $failed[] = ['id' => $p->id, 'error' => $p->apply_error];
                    continue;
                }
                $live[$mid] = $res['result'];
                $appliedProposals[] = $p;
            }

            if (empty($appliedProposals)) {
                return ['applied' => 0, 'failed' => $failed, 'version_from' => $currentVersion, 'version_to' => null, 'blocked' => null];
            }

            // Baseline: se non esiste ancora una versione studente, la live attuale è "1.0".
            $current = $this->latestStudentVersion($course);
            if ($current === null) {
                $current = StudentSourceVersion::create([
                    'course_id' => $course->id, 'version' => '1.0', 'content' => $this->studentSnapshot($original),
                ]);
            }
            $versionFrom = $current->version;
            $newVersion = $this->nextVersion($versionFrom);

            // Nuova versione = snapshot COMPLETO post-applicazione (la precedente resta intatta).
            StudentSourceVersion::create([
                'course_id' => $course->id, 'version' => $newVersion, 'content' => $this->studentSnapshot($live),
            ]);

            // UPDATE IN-PLACE dei soli moduli modificati. MAI delete.
            foreach ($live as $mid => $html) {
                if ($html !== $original[$mid]) {
                    Module::where('id', $mid)->update(['content' => $html]);
                }
            }

            foreach ($appliedProposals as $p) {
                $p->update(['status' => 'applied', 'applied_at' => now(), 'apply_error' => null]);
                CourseChangelog::create([
                    'course_id' => $course->id, 'proposal_id' => $p->id, 'content_source' => 'student',
                    // P25.B-b — tracciabilità: la modifica discente coordinata nasce dalla
                    // proposta formatore parent_proposal_id.
                    'parent_proposal_id' => $p->parent_proposal_id,
                    'version_from' => $versionFrom, 'version_to' => $newVersion, 'kind' => 'apply',
                    'summary' => mb_substr($p->before, 0, 120) . ' → ' . mb_substr((string) $p->after, 0, 120),
                    'approved_by' => $p->reviewed_by, 'approved_at' => $p->reviewed_at,
                ]);
            }

            return ['applied' => count($appliedProposals), 'failed' => $failed, 'version_from' => $versionFrom, 'version_to' => $newVersion, 'blocked' => null];
        });
    }

    /**
     * Rollback dell'ultima applicazione studente: ripristina modules.content (in-place
     * UPDATE, mai delete) dalla versione precedente; nuova versione = copia della precedente.
     *
     * @return array{rolled_back:bool, version_from:?string, version_to:?string, restored_to:?string}
     */
    public function rollbackStudent(Course $course): array
    {
        return DB::transaction(function () use ($course) {
            $latest = $this->latestStudentVersion($course);
            if (!$latest) {
                throw new RuntimeException('Nessuna versione studente da cui fare rollback.');
            }

            $entry = CourseChangelog::where('course_id', $course->id)
                ->where('content_source', 'student')
                ->where('version_to', $latest->version)
                ->where('kind', 'apply')
                ->orderByDesc('created_at')
                ->first();
            if (!$entry) {
                throw new RuntimeException("Nessuna applicazione studente da annullare per la versione {$latest->version}.");
            }

            $preVersion = $entry->version_from;
            $pre = StudentSourceVersion::where('course_id', $course->id)->where('version', $preVersion)->first();
            if (!$pre) {
                throw new RuntimeException("Versione studente precedente {$preVersion} non disponibile per il rollback.");
            }

            // Ripristina i moduli in-place dalla versione precedente.
            foreach ($pre->content as $item) {
                Module::where('id', $item['module_id'])->update(['content' => $item['content_html']]);
            }

            $newVersion = $this->nextVersion($latest->version);
            StudentSourceVersion::create([
                'course_id' => $course->id, 'version' => $newVersion, 'content' => $pre->content,
            ]);
            CourseChangelog::create([
                'course_id' => $course->id, 'proposal_id' => null, 'content_source' => 'student',
                'version_from' => $latest->version, 'version_to' => $newVersion, 'kind' => 'rollback',
                'summary' => "Rollback studente dalla versione {$latest->version} (ripristino contenuti di {$preVersion})",
                'approved_by' => null, 'approved_at' => now(),
            ]);

            return ['rolled_back' => true, 'version_from' => $latest->version, 'version_to' => $newVersion, 'restored_to' => $preVersion];
        });
    }

    /** Versione studente più recente (deterministica: created_at desc, id desc). */
    private function latestStudentVersion(Course $course): ?StudentSourceVersion
    {
        return StudentSourceVersion::where('course_id', $course->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    /** @param array<string,string> $contentById  @return list<array{module_id:string, content_html:string}> */
    private function studentSnapshot(array $contentById): array
    {
        $out = [];
        foreach ($contentById as $moduleId => $html) {
            $out[] = ['module_id' => $moduleId, 'content_html' => $html];
        }
        return $out;
    }

    /** Registra il fallimento pulito di una proposta (resta 'approved', non applicata). */
    private function fail(UpdateProposal $proposal, string $reason): void
    {
        $proposal->update(['apply_error' => $reason]);
        Log::warning('[ProposalApplicator] proposta non applicata (verbatim)', [
            'proposal_id' => $proposal->id, 'reason' => $reason,
        ]);
    }

    /**
     * Replace verbatim "unico o niente" su un blocco del sorgente strutturato, gestendo
     * SIA i blocchi con `text` SIA le liste (BUL/NUM) con `items` — dove `text` è vuoto e il
     * contenuto vive negli item. È il complemento speculare di searchableText (Fase 1, che già
     * legge gli item delle liste): senza questo, le proposte nate da un blocco lista falliscono
     * sempre il match sorgente ("before non trovato").
     *
     * Per le liste il `before` deve comparire in ESATTAMENTE un item su tutta la lista (stesso
     * criterio "unico o niente"). Ritorna ['ok'=>bool, 'block'=>array, 'reason'=>?string]: `block`
     * è il blocco con la sostituzione già applicata (assegnato alle copie di lavoro solo se ok).
     *
     * @param  array<string,mixed>  $block
     * @return array{ok:bool, block:array<string,mixed>, reason:?string}
     */
    private function replaceInSourceBlock(array $block, string $before, string $after): array
    {
        // Blocco testuale (P/H*/BOX/EX/ESE…): replace diretto su `text`.
        if (isset($block['text']) && is_string($block['text']) && $block['text'] !== '') {
            $res = VerbatimReplacer::replaceUnique($block['text'], $before, $after);
            if ($res['ok']) {
                $block['text'] = $res['result'];
            }
            return ['ok' => $res['ok'], 'block' => $block, 'reason' => $res['reason']];
        }

        // Lista (BUL/NUM): il before deve essere in ESATTAMENTE un item.
        if (isset($block['items']) && is_array($block['items'])) {
            $items = $block['items'];
            $totalHits = 0;
            $hitIndex = null;
            foreach ($items as $i => $item) {
                $count = VerbatimReplacer::countOccurrences((string) $item, $before);
                if ($count > 0) {
                    $totalHits += $count;
                    $hitIndex = $i;
                }
            }
            if ($totalHits !== 1) {
                return ['ok' => false, 'block' => $block,
                    'reason' => "before non trovato o non unico negli item della lista ({$totalHits} occorrenze)"];
            }
            $res = VerbatimReplacer::replaceUnique((string) $items[$hitIndex], $before, $after);
            if (!$res['ok']) {
                return ['ok' => false, 'block' => $block, 'reason' => $res['reason']];
            }
            $items[$hitIndex] = $res['result'];
            $block['items'] = $items;
            return ['ok' => true, 'block' => $block, 'reason' => null];
        }

        return ['ok' => false, 'block' => $block, 'reason' => 'blocco senza text né items'];
    }

    /**
     * Versione più recente del sorgente, in modo DETERMINISTICO: created_at è al secondo
     * (versioni create nello stesso secondo darebbero ordine ambiguo); gli id sono
     * orderedUuid (time-sortable), quindi rompono il pareggio nell'ordine corretto.
     */
    private function latestSource(Course $course): ?CourseSource
    {
        return CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /** Incremento della versione come STRINGA (mai float): "2.0" → "2.1". */
    private function nextVersion(string $v): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $v, $m)) {
            return $m[1] . '.' . ((int) $m[2] + 1);
        }
        if (preg_match('/^\d+$/', $v)) {
            return $v . '.1';
        }
        throw new RuntimeException("Versione non incrementabile in modo deterministico: {$v}");
    }

    /** Rigenera il PDF dal nuovo sorgente. Best-effort: un errore PDF non annulla l'applicazione. */
    private function regeneratePdf(Course $course, CourseSource $source, string $version): void
    {
        try {
            $bytes = $this->pdfBuilder->build($source->blocks, ['title' => "{$course->name} — sorgente v{$version}"]);
            Storage::disk('local')->put("course-sources/{$course->id}/v{$version}.pdf", $bytes);
        } catch (\Throwable $e) {
            Log::warning('[ProposalApplicator] rigenerazione PDF fallita (non bloccante)', [
                'course_id' => $course->id, 'version' => $version, 'error' => $e->getMessage(),
            ]);
        }
    }
}
