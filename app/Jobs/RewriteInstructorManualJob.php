<?php

namespace App\Jobs;

use App\Models\InstructorManualSection;
use App\Models\UpdateProposal;
use App\Services\Freshness\ProposalApplicator;
use App\Services\Freshness\StudentMatchFinder;
use App\Services\Freshness\StudentRewriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P25.3f (Livello 2) — Riscrittura semantica del manuale formatore quando il `before` del
 * sorgente NON era verbatim nel manuale (manual_status='queued' lasciato da ProposalApplicator::apply).
 *
 * Riusa la macchina collaudata del lato studente:
 *  - StudentMatchFinder: localizza nel manuale la frase che parla dello STESSO fatto (verbatim+unica).
 *  - StudentRewriter: riscrive quella frase aggiornando SOLO il dato (vecchio→nuovo), tono preservato.
 *  - ProposalApplicator::applyManualPatch: sostituzione verbatim sulla sezione, con snapshot per il rollback.
 *
 * Conservativo: se il fatto non si ritrova/ancora nel manuale → manual_status='unmatched' (da rivedere
 * a mano, niente patch fantasma). Se match/riscrittura/patch vanno in errore → 'failed'. Le chiamate AI
 * girano qui (async), MAI dentro la transazione di apply.
 */
class RewriteInstructorManualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public string $proposalId) {}

    public function handle(StudentMatchFinder $finder, StudentRewriter $rewriter, ProposalApplicator $applicator): void
    {
        $p = UpdateProposal::find($this->proposalId);
        // Solo proposte formatore lasciate in coda di riscrittura manuale.
        if (!$p || $p->content_source !== 'instructor' || $p->manual_status !== 'queued') {
            return;
        }

        $course = $p->course;
        if (!$course) {
            return;
        }

        $sections = InstructorManualSection::where('course_id', $course->id)->get();
        if ($sections->isEmpty()) {
            $p->update(['manual_status' => 'unmatched']);
            return;
        }

        // Adapter: il finder legge ->id e ->content. Qui il "materiale" è il manuale formatore.
        $adapters = $sections->map(fn ($s) => (object) ['id' => $s->id, 'content' => (string) $s->content_html]);

        try {
            $found = $finder->find((string) $p->before, $adapters);
        } catch (\Throwable $e) {
            Log::warning('[RewriteInstructorManualJob] match manuale fallito', ['id' => $p->id, 'error' => $e->getMessage()]);
            $p->update(['manual_status' => 'failed']);
            return;
        }

        $candidates = $found['candidates'] ?? [];
        if (empty($candidates)) {
            // Il fatto non è trattato/ancorabile nel manuale: niente patch fantasma.
            $p->update(['manual_status' => 'unmatched']);
            Log::info('[RewriteInstructorManualJob] nessuna ancora nel manuale', ['id' => $p->id]);
            return;
        }

        $appliedAny = false;
        $firstBefore = null;
        $firstAfter = null;

        foreach ($candidates as $cand) {
            try {
                $r = $rewriter->rewrite((string) $cand['before'], (string) $p->before, (string) $p->after);
            } catch (\Throwable $e) {
                Log::warning('[RewriteInstructorManualJob] riscrittura fallita', ['id' => $p->id, 'error' => $e->getMessage()]);
                continue;
            }

            $patch = $applicator->applyManualPatch($course, (string) $cand['module_id'], (string) $cand['before'], $r['after']);
            if ($patch['ok']) {
                $appliedAny = true;
                $firstBefore ??= (string) $cand['before'];
                $firstAfter ??= $r['after'];
            } else {
                Log::warning('[RewriteInstructorManualJob] patch manuale non applicata', ['id' => $p->id, 'reason' => $patch['reason']]);
            }
        }

        $p->update($appliedAny
            ? ['manual_status' => 'rewritten', 'manual_before' => $firstBefore, 'manual_after' => $firstAfter]
            : ['manual_status' => 'failed']);

        Log::info('[RewriteInstructorManualJob] esito', [
            'id' => $p->id, 'status' => $appliedAny ? 'rewritten' : 'failed', 'candidates' => count($candidates),
        ]);
    }
}
