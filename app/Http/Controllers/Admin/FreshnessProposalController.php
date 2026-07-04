<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FindStudentMatchesJob;
use App\Jobs\RewriteStudentProposalJob;
use App\Jobs\RunFreshnessAgentJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseFreshnessConfig;
use App\Models\FreshnessRun;
use App\Models\UpdateProposal;
use App\Services\Freshness\CoordinatedMatchService;
use App\Services\Freshness\ProposalApplicator;
use Illuminate\Http\Request;

/**
 * P25.3b — Coda HITL delle proposte di aggiornamento corsi.
 *
 * ADDITIVO: nuova sezione admin, non tocca CourseController/ModuleController né alcun
 * percorso di modifica manuale del corso.
 *
 * HITL (non negoziabile): qui si VEDE il diff e si cambia SOLO lo status delle proposte
 * (approved/rejected). NESSUN endpoint applica alcunché al contenuto del corso —
 * l'applicazione reale (course_sources/modules) è P25.3c e consuma solo 'approved'.
 */
class FreshnessProposalController extends Controller
{
    /**
     * Coda HITL a DUE TAB per sorgente (P25.B-a): 'instructor' (formatore) e 'student'
     * (materiale studente). Default 'instructor' (flusso esistente, retrocompatibile).
     * Ogni tab mostra solo le proposte PENDING della sua sorgente.
     */
    public function index(Request $request)
    {
        $source = $request->query('source') === 'student' ? 'student' : 'instructor';
        $courseFilter = $request->query('course');

        $query = UpdateProposal::with(['course', 'claim'])
            ->pending()
            ->where('content_source', $source)
            ->orderBy('course_id')
            ->orderByDesc('created_at');

        if ($courseFilter) {
            $query->where('course_id', $courseFilter);
        }

        $proposals = $query->get()->groupBy('course_id');

        // Conteggi pending per tab.
        $pendingCounts = [
            'instructor' => UpdateProposal::pending()->where('content_source', 'instructor')->count(),
            'student' => UpdateProposal::pending()->where('content_source', 'student')->count(),
        ];

        // Corsi attivi (pannello controlli) con conteggio approvate DELLA SORGENTE ATTIVA
        // (apply/rollback sono per-sorgente: mai mescolare i due flussi).
        $allCourses = Course::active()
            ->with('freshnessConfig')
            ->withCount(['updateProposals as approved_count' => fn ($q) => $q
                ->where('status', 'approved')->where('content_source', $source)])
            // P25.3f — proposte 'approved' rimaste BLOCCATE (apply_error): nessun target ha
            // attecchito. Servono all'escape hatch "scarta bloccate".
            ->withCount(['updateProposals as stuck_count' => fn ($q) => $q
                ->where('status', 'approved')->where('content_source', $source)->whereNotNull('apply_error')])
            // P25.3f — manuale formatore DA RIVEDERE a mano: la riscrittura semantica non ha
            // trovato/ancorato il fatto (unmatched) o è andata in errore (failed).
            ->withCount(['updateProposals as manual_attention_count' => fn ($q) => $q
                ->where('content_source', 'instructor')->whereIn('manual_status', ['unmatched', 'failed'])])
            ->orderBy('name')
            ->get();

        // P25.B-b.3 — sul tab Studente: candidate coordinate da confermare (status='matched')
        // + alert per le modifiche già LIVE il cui padre formatore è stato annullato (orfane).
        $candidates = collect();
        $orphanAlerts = collect();
        if ($source === 'student') {
            $candidates = UpdateProposal::with(['course', 'parentProposal'])
                ->where('content_source', 'student')->where('status', 'matched')->whereNull('orphaned_at')
                ->orderByDesc('created_at')->get()->groupBy('course_id');
            $orphanAlerts = UpdateProposal::with('course')
                ->where('content_source', 'student')->where('status', 'applied')->whereNotNull('orphaned_at')
                ->orderByDesc('orphaned_at')->get();
        }

        // P25 — esito degli ultimi controlli (async): l'utente deve vedere a schermo se un run
        // è fallito e PERCHÉ, non solo l'ottimistico "avviato" del dispatch.
        $recentRuns = FreshnessRun::with('course')
            ->whereNull('dismissed_at')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('admin.freshness.proposals', compact('proposals', 'allCourses', 'courseFilter', 'source', 'pendingCounts', 'candidates', 'orphanAlerts', 'recentRuns'));
    }

    /**
     * Stato dei controlli per il polling live: dice se un'analisi è in corso e ri-renderizza
     * lo storico. Permette alla UI di mostrare "Analisi in corso…" e aggiornarsi senza reload.
     */
    public function runsStatus()
    {
        $recentRuns = FreshnessRun::with('course')
            ->whereNull('dismissed_at')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $running = $recentRuns->where('status', 'running');

        return response()->json([
            'running' => $running->isNotEmpty(),
            'banner' => $running->isNotEmpty()
                ? $running->map(fn ($r) => optional($r->course)->name ?? '—')->unique()->implode(', ')
                : null,
            'html' => view('admin.freshness._runs_history', ['recentRuns' => $recentRuns])->render(),
        ]);
    }

    /** Archivia un run dallo storico (soft: non distrugge claim/proposte). Non sui run in corso. */
    public function dismissRun(FreshnessRun $run)
    {
        if ($run->status !== 'running') {
            $run->update(['dismissed_at' => now()]);
        }

        return back();
    }

    /** Pulisce lo storico: archivia tutti i run NON in corso. */
    public function clearRuns()
    {
        FreshnessRun::whereNull('dismissed_at')->where('status', '!=', 'running')->update(['dismissed_at' => now()]);

        return back()->with('success', 'Storico analisi pulito.');
    }

    /**
     * P25.3d — Lancia un controllo (freshness-run) ASINCRONO su un corso. Solo dispatch:
     * il run gira sulla queue (chiamate AI lente). NON applica nulla.
     */
    public function run(Request $request)
    {
        $validated = $request->validate(['course_id' => 'required|uuid|exists:courses,id']);
        $course = Course::find($validated['course_id']);

        RunFreshnessAgentJob::dispatch($course->id);

        return back()->with('success', "Controllo avviato per «{$course->name}». L'estrazione fa chiamate AI e può richiedere qualche minuto: le proposte appariranno qui a breve.");
    }

    /** P25.3d — Imposta la cadenza dello scheduler per un corso. */
    public function setCadence(Request $request, Course $course)
    {
        $validated = $request->validate(['cadence' => 'required|in:off,weekly,monthly,quarterly']);

        CourseFreshnessConfig::updateOrCreate(
            ['course_id' => $course->id],
            ['cadence' => $validated['cadence']]
        );

        return back()->with('success', "Cadenza aggiornata per «{$course->name}»: {$validated['cadence']}.");
    }

    /** P25.3e — Override manuale (autorevole) dell'audience: marca audience_overridden. */
    public function setAudience(Request $request, Course $course)
    {
        $validated = $request->validate(['audience' => 'required|in:adult,minor']);

        CourseFreshnessConfig::updateOrCreate(
            ['course_id' => $course->id],
            ['audience' => $validated['audience'], 'audience_overridden' => true]
        );

        return back()->with('success', "Audience aggiornato per «{$course->name}»: {$validated['audience']} (override manuale).");
    }

    /**
     * P25.3e/B-a — Applica le proposte APPROVED di UNA sorgente. Instrada ad apply()
     * (formatore: course_sources + instructor_manual_sections) o applyStudent()
     * (studente: modules.content) in base a content_source. Mai mescolare i due flussi.
     * Doppio gate MINORI (confirm_minor) su entrambe le sorgenti.
     */
    public function apply(Request $request, Course $course, ProposalApplicator $applicator)
    {
        $source = $this->resolveSource($request);
        $label = $source === 'student' ? 'studente' : 'formatore';
        $audience = optional($course->freshnessConfig)->audience ?? 'adult';
        $confirmed = $request->boolean('confirm_minor');

        // Gate 2 (umano) per i minori: senza conferma esplicita → bloccato, nessuna modifica.
        if ($audience === 'minor' && !$confirmed) {
            return back()->with('error', "⚠ Corso per MINORI: serve la conferma esplicita di applicazione ({$label}). Nessuna modifica applicata a «{$course->name}».");
        }

        $res = $source === 'student'
            ? $applicator->applyStudent($course, minorConfirmed: $confirmed)
            : $applicator->apply($course, minorConfirmed: $confirmed);

        if (($res['blocked'] ?? null) === 'minor_confirmation_required') {
            return back()->with('error', "⚠ Conferma minori richiesta: nessuna modifica applicata a «{$course->name}».");
        }

        $msg = "[{$label}] Applicate {$res['applied']} proposte su «{$course->name}»";
        if ($res['version_to']) {
            $msg .= " (v{$res['version_from']} → v{$res['version_to']})";
        }
        // P25.3f — manuale formatore disaccoppiato: i casi senza match verbatim non bloccano
        // più, vanno in riscrittura semantica async (Livello 2).
        if (!empty($res['queued'])) {
            $msg .= '. ' . count($res['queued']) . ' in riscrittura semantica del manuale (in background)';
        }
        if (!empty($res['failed'])) {
            $msg .= '. ' . count($res['failed']) . ' non applicate (before non trovato né nel sorgente né nel manuale)';
        }

        return back()->with('success', $msg . '.');
    }

    /**
     * P25.B-a — Rollback per-sorgente: formatore → course_sources/instructor_manual_sections;
     * studente → modules.content da student_source_versions. Torna alla versione precedente.
     */
    public function rollback(Request $request, Course $course, ProposalApplicator $applicator, CoordinatedMatchService $coord)
    {
        $source = $this->resolveSource($request);
        $label = $source === 'student' ? 'studente' : 'formatore';

        try {
            $res = $source === 'student'
                ? $applicator->rollbackStudent($course)
                : $applicator->rollback($course);
        } catch (\Throwable $e) {
            return back()->with('error', "Rollback {$label} non possibile su «{$course->name}»: " . $e->getMessage());
        }

        // P25.B-b.3 — orfananza: rollback FORMATORE → le figlie dei padri applicati nella
        // versione annullata vanno orfanate (pending→rejected; applied→flagged).
        if ($source === 'instructor') {
            $parentIds = CourseChangelog::where('course_id', $course->id)
                ->where('content_source', 'instructor')
                ->where('version_to', $res['version_from'])
                ->where('kind', 'apply')
                ->whereNotNull('proposal_id')
                ->pluck('proposal_id');
            UpdateProposal::whereIn('id', $parentIds)->get()
                ->each(fn ($parent) => $coord->orphanChildrenOf($parent, 'Padre formatore rollbackato'));
        }

        return back()->with('success', "[{$label}] Rollback su «{$course->name}»: v{$res['version_from']} → v{$res['version_to']} (ripristino contenuti di v{$res['restored_to']}).");
    }

    private function resolveSource(Request $request): string
    {
        return $request->input('content_source') === 'student' ? 'student' : 'instructor';
    }

    /**
     * Approva una proposta. Se l'admin ha editato l'`after` (campo diverso) → la modifica
     * viene registrata con after_edited_by_human=true. Solo su proposte 'pending'.
     */
    public function approve(Request $request, UpdateProposal $proposal)
    {
        abort_unless($proposal->status === 'pending', 422, 'La proposta non è più in attesa.');

        $data = [
            'status' => 'approved',
            'reviewed_by' => $this->adminId(),
            'reviewed_at' => now(),
        ];

        $newAfter = trim((string) $request->input('after', ''));
        if ($newAfter !== '' && $newAfter !== $proposal->after) {
            $data['after'] = $newAfter;
            $data['after_edited_by_human'] = true;
        }

        $proposal->update($data);

        // P25.B-b — se è una proposta FORMATORE approvata, scatta il matching coordinato
        // (async) sul materiale discente. Non riscrive/applica nulla: crea candidate 'matched'.
        $this->maybeCoordinate($proposal);

        return back()->with('success', 'Proposta approvata. Verrà applicata in fase di applicazione (P25.3c).');
    }

    /**
     * Rifiuta una proposta 'pending', o SCARTA una candidate coordinata 'matched' (B-b.3).
     * Se è una proposta FORMATORE con figlie coordinate → le orfana (D1).
     */
    public function reject(UpdateProposal $proposal, CoordinatedMatchService $coord)
    {
        // P25.3f — escape hatch: oltre a pending/matched si può scartare anche una proposta
        // 'approved' rimasta bloccata (es. before non applicabile né a sorgente né a manuale),
        // così la coda di applicazione non resta inchiodata.
        abort_unless(in_array($proposal->status, ['pending', 'matched', 'approved'], true), 422, 'La proposta non è in uno stato rifiutabile.');

        $proposal->update([
            'status' => 'rejected',
            'reviewed_by' => $this->adminId(),
            'reviewed_at' => now(),
        ]);

        if ($proposal->content_source === 'instructor') {
            $coord->orphanChildrenOf($proposal, 'Padre formatore rifiutato');
        }

        return back()->with('success', $proposal->wasChanged() ? 'Proposta scartata.' : 'Proposta scartata.');
    }

    /**
     * P25.3f — Escape hatch di corso: scarta in blocco le proposte 'approved' rimaste BLOCCATE
     * (apply_error valorizzato: nessun target ha attecchito) della sorgente attiva, così il
     * pulsante "Applica" non resta inchiodato. Le figlie coordinate vengono orfanate.
     */
    public function rejectStuck(Request $request, Course $course, CoordinatedMatchService $coord)
    {
        $source = $this->resolveSource($request);

        $stuck = UpdateProposal::where('course_id', $course->id)
            ->where('content_source', $source)
            ->where('status', 'approved')
            ->whereNotNull('apply_error')
            ->get();

        foreach ($stuck as $p) {
            $p->update(['status' => 'rejected', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);
            if ($p->content_source === 'instructor') {
                $coord->orphanChildrenOf($p, 'Proposta formatore bloccata scartata');
            }
        }

        return back()->with('success', "Scartate {$stuck->count()} proposte bloccate su «{$course->name}».");
    }

    /**
     * P25.B-b.3 — Conferma una candidate coordinata (status='matched'): avvia la riscrittura
     * conservativa (async). NON applica nulla; la proposta diventerà 'pending' con l'after.
     */
    public function confirm(UpdateProposal $proposal)
    {
        abort_unless($proposal->status === 'matched', 422, 'Non è una candidate da confermare.');

        RewriteStudentProposalJob::dispatch($proposal->id);

        return back()->with('success', 'Candidate confermata: riscrittura conservativa in corso. Comparirà tra le proposte da approvare a breve.');
    }

    /** Azione massiva sulle proposte selezionate (solo cambio status, mai applicazione). */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'ids' => 'required|array',
            'ids.*' => 'uuid',
        ]);

        $status = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        $count = UpdateProposal::whereIn('id', $validated['ids'])
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'reviewed_by' => $this->adminId(),
                'reviewed_at' => now(),
            ]);

        // P25.B-b — coordinamento per le approvazioni FORMATORE del batch.
        if ($status === 'approved') {
            UpdateProposal::whereIn('id', $validated['ids'])
                ->where('status', 'approved')
                ->where('content_source', 'instructor')
                ->get()
                ->each(fn ($p) => $this->maybeCoordinate($p));
        }

        return back()->with('success', "{$count} proposte aggiornate ({$status}).");
    }

    /**
     * P25.B-b — Dispatcha il matching coordinato (async) per una proposta FORMATORE
     * approvata, solo se il corso ha attivato il lato studente (student_proposals_enabled).
     */
    private function maybeCoordinate(UpdateProposal $proposal): void
    {
        if ($proposal->content_source !== 'instructor' || $proposal->status !== 'approved') {
            return;
        }
        if (!optional(optional($proposal->course)->freshnessConfig)->student_proposals_enabled) {
            return;
        }
        FindStudentMatchesJob::dispatch($proposal->id);
    }

    /** Admin loggato (sessione custom) → uuid per l'audit. Null se non risolvibile. */
    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
