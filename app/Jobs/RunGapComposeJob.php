<?php

namespace App\Jobs;

use App\Models\CoverageGap;
use App\Models\GapDraft;
use App\Services\GapComposer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P26 Fase B — Genera (async) la bozza formatore+studente per un gap ACCETTATO e la persiste in
 * gap_drafts. Una bozza per gap (rigenera = sovrascrive). Osservabilità sullo `status` della
 * bozza: generating → draft (ok) | failed (+ `error` = messaggio reale via AnthropicError).
 *
 * NON inserisce nulla: scrive SOLO gap_drafts. Mai course_sources/modules/instructor_manual_sections.
 */
class RunGapComposeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public string $coverageGapId) {}

    public function handle(GapComposer $composer): void
    {
        $gap = CoverageGap::find($this->coverageGapId);
        if (!$gap || $gap->status !== 'accepted') {
            Log::warning('[RunGapComposeJob] gap inesistente o non accettato', ['gap_id' => $this->coverageGapId]);
            return;
        }

        // Stato "in generazione" subito (per la UI), azzerando un eventuale errore precedente.
        $draft = GapDraft::updateOrCreate(
            ['coverage_gap_id' => $gap->id],
            ['status' => 'generating', 'error' => null]
        );

        try {
            $res = $composer->compose($gap);
            $draft->update([
                'formatore_html' => $res['formatore_html'],
                'studente_html' => $res['studente_html'],
                'note' => $res['note'],
                'status' => 'draft',
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $draft->update(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::warning('[RunGapComposeJob] compose non completato', [
                'gap_id' => $gap->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
