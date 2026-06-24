<?php

namespace App\Jobs;

use App\Models\UpdateProposal;
use App\Services\Freshness\StudentRewriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P25.B-b.3 — Confermata una candidate coordinata (status='matched'), genera la riscrittura
 * conservativa (after) dal fatto aggiornato del padre formatore e porta la proposta a
 * 'pending'. Diventa una proposta discente standard, applicabile con applyStudent (B-a.3).
 *
 * NON applica nulla. Se la riscrittura è "stravolgente" → match_trust='low' (eyeball).
 */
class RewriteStudentProposalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public string $proposalId) {}

    public function handle(StudentRewriter $rewriter): void
    {
        $proposal = UpdateProposal::find($this->proposalId);
        // Solo candidate confermate e non orfane.
        if (!$proposal || $proposal->status !== 'matched' || $proposal->orphaned_at !== null) {
            return;
        }

        $parent = $proposal->parentProposal;
        if (!$parent) {
            Log::warning('[RewriteStudentProposalJob] padre assente, candidate lasciata matched', ['id' => $proposal->id]);
            return;
        }

        try {
            $r = $rewriter->rewrite((string) $proposal->before, (string) $parent->before, (string) $parent->after);

            $proposal->update([
                'after' => $r['after'],
                'status' => 'pending', // ora in coda applicabile
                'reason' => 'Riscrittura coordinata dall\'aggiornamento formatore'
                    . ($r['divergent'] ? ' (riscrittura ampia: verificare a mano)' : '')
                    . ($r['reason'] ? ' — ' . $r['reason'] : ''),
                // Segnale "guarda con attenzione" se la riscrittura ha stravolto la frase.
                'match_trust' => $r['divergent'] ? 'low' : $proposal->match_trust,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[RewriteStudentProposalJob] riscrittura fallita, candidate resta matched', [
                'id' => $proposal->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
