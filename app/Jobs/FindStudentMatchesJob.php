<?php

namespace App\Jobs;

use App\Models\UpdateProposal;
use App\Services\Freshness\CoordinatedMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P25.B-b.2 — All'approvazione di una proposta formatore, cerca ASINCRONO le porzioni
 * discente sullo stesso fatto e crea le candidate coordinate (status='matched'). Una
 * chiamata LLM: non blocca l'azione di approvazione. NON riscrive, NON applica.
 */
class FindStudentMatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public string $proposalId) {}

    public function handle(CoordinatedMatchService $service): void
    {
        $proposal = UpdateProposal::find($this->proposalId);
        if (!$proposal) {
            return;
        }

        try {
            $service->matchForApprovedProposal($proposal);
        } catch (\Throwable $e) {
            Log::warning('[FindStudentMatchesJob] matching fallito', [
                'proposal_id' => $this->proposalId, 'error' => $e->getMessage(),
            ]);
        }
    }
}
