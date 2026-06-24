<?php

namespace App\Services\Freshness;

use App\Models\UpdateProposal;
use Illuminate\Support\Facades\Log;

/**
 * P25.B-b.2 — All'approvazione di una proposta FORMATORE, cerca le porzioni discente sullo
 * stesso fatto e crea le candidate COORDINATE (status='matched', niente riscrittura ancora).
 *
 * - Gestisce la molteplicità (una candidate per porzione trovata).
 * - Salva match_confidence e match_trust. `match_trust='low'` per i fatti su PRODOTTO/policy
 *   (reperto probe: lì il matcher può agganciare la menzione sbagliata dello stesso prodotto
 *   → eyeball obbligatorio). Altrimenti 'high'.
 * - NON riscrive (after resta null fino a B-b.3). NON applica nulla.
 * - Idempotente: non duplica una candidate già esistente per (parent, module, before).
 */
class CoordinatedMatchService
{
    public function __construct(private StudentMatchFinder $finder) {}

    /** Categorie "prodotto/policy" → match lower-trust (dal reperto probe). */
    private const LOW_TRUST_CATEGORIES = ['prodotto'];

    /** @return int numero di candidate create */
    public function matchForApprovedProposal(UpdateProposal $proposal): int
    {
        // Solo proposte FORMATORE approvate generano coordinamento.
        if ($proposal->content_source !== 'instructor' || $proposal->status !== 'approved') {
            return 0;
        }

        $course = $proposal->course;
        if (!$course) {
            return 0;
        }

        // Opt-in: il lato studente è contenuto utente-finale. Niente coordinamento se off.
        if (!optional($course->freshnessConfig)->student_proposals_enabled) {
            return 0;
        }

        $modules = $course->modules()->get();
        if ($modules->isEmpty()) {
            return 0;
        }

        $result = $this->finder->find((string) $proposal->before, $modules);

        // Trust derivato dalla categoria del fatto (via il claim formatore).
        $category = optional($proposal->claim)->category;
        $trust = in_array($category, self::LOW_TRUST_CATEGORIES, true) ? 'low' : 'high';

        $created = 0;
        foreach ($result['candidates'] as $cand) {
            $exists = UpdateProposal::where('parent_proposal_id', $proposal->id)
                ->where('module_id', $cand['module_id'])
                ->where('before', $cand['before'])
                ->exists();
            if ($exists) {
                continue; // idempotente
            }

            UpdateProposal::create([
                'course_id' => $course->id,
                'content_source' => 'student',
                'origin' => 'coordinated',
                'parent_proposal_id' => $proposal->id,
                'module_id' => $cand['module_id'],
                'before' => $cand['before'], // porzione discente verbatim
                'after' => null,             // riscrittura in B-b.3
                'reason' => 'Coordinata dall\'aggiornamento formatore: ' . mb_substr((string) $proposal->after, 0, 100),
                'source' => $proposal->source,
                'source_type' => $proposal->source_type,
                'audience' => $proposal->audience, // eredita l'audience del corso/proposta padre
                'status' => 'matched',
                'match_confidence' => $cand['confidence'],
                'match_trust' => $trust,
            ]);
            $created++;
        }

        Log::info('[CoordinatedMatchService] candidate create', [
            'parent_id' => $proposal->id, 'created' => $created, 'none' => $result['none'], 'trust' => $trust,
        ]);

        return $created;
    }

    /**
     * P25.B-b.3 — Orfananza (D1 reattivo): il padre formatore è stato rollbackato/rifiutato.
     * - Figlia ancora matched/pending/approved → 'rejected' + orphaned (auto-scartata, tracciata).
     * - Figlia già 'applied' (LIVE sul materiale studente) → RESTA 'applied' + orphaned
     *   (segnalata in UI, MAI scartata silenziosamente: è già live).
     *
     * @return array{discarded:int, flagged:int}
     */
    public function orphanChildrenOf(UpdateProposal $parent, string $reason): array
    {
        $discarded = 0;
        $flagged = 0;
        $children = UpdateProposal::where('parent_proposal_id', $parent->id)
            ->whereNull('orphaned_at')
            ->get();

        foreach ($children as $child) {
            if ($child->status === 'applied') {
                $child->update(['orphaned_at' => now(), 'orphan_reason' => $reason]); // resta applied (live)
                $flagged++;
            } else {
                $child->update(['status' => 'rejected', 'orphaned_at' => now(), 'orphan_reason' => $reason]);
                $discarded++;
            }
        }

        return ['discarded' => $discarded, 'flagged' => $flagged];
    }
}
