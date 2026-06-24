<?php

namespace App\Services\Freshness;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use App\Models\UpdateProposal;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * P25.2/P25.3 — Orchestratore dell'agente.
 *
 * Crea una run → carica l'ULTIMO course_sources del corso (o la versione richiesta) →
 * Fase 1 estrae e PERSISTE i claim → Fase 2 verifica e aggiorna ogni claim → Fase 3
 * (P25.3, disattivabile) genera le proposte per i claim obsoleti → chiude la run.
 * LEGGE il sorgente, non lo modifica MAI. Aggancio per course_id interno.
 *
 * HITL: la Fase 3 scrive solo proposte `pending`; nulla viene applicato (l'applicazione
 * è P25.3c e consuma solo `approved`).
 */
class FreshnessAgent
{
    public function __construct(
        private FreshnessClaimExtractor $extractor,
        private FreshnessVerifier $verifier,
        private FreshnessProposalGenerator $generator,
        private StudentClaimExtractor $studentExtractor,
    ) {}

    public function run(Course $course, ?string $version = null): FreshnessRun
    {
        $run = FreshnessRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $source = $this->loadSource($course, $version);
            $config = $course->freshnessConfig ?? new CourseFreshnessConfig([
                'web_search_enabled' => true,
                'primary_sources' => [],
                'audience' => 'adult',
                'proposals_enabled' => true,
                'student_proposals_enabled' => false,
            ]);

            // ===== Lato FORMATORE (instructor): Fase 1 sul sorgente strutturato. =====
            $extracted = $this->extractor->extract($source->blocks ?? []);

            $obsolete = [];
            foreach ($extracted['claims'] as $c) {
                $claim = FreshnessClaim::create([
                    'run_id' => $run->id,
                    'course_id' => $course->id,
                    'content_source' => 'instructor',
                    'block_id' => $c['block_id'],
                    'sentence_ref' => $c['sentence_ref'],
                    'claim_text' => $c['claim_text'],
                    'category' => $c['category'],
                ]);
                if ($this->verifyClaim($claim, $config)) {
                    $obsolete[] = $claim;
                }
            }

            // ===== Lato STUDENTE (modules.content): solo se opt-in (student_proposals_enabled). =====
            // È contenuto utente-finale: niente analisi finché non è esplicitamente attivata.
            $studentObsolete = [];
            $studentClaimsFound = 0;
            if ($config->student_proposals_enabled) {
                $studentExtracted = $this->studentExtractor->extract($course->modules()->get());
                $studentClaimsFound = count($studentExtracted['claims']);
                foreach ($studentExtracted['claims'] as $c) {
                    $claim = FreshnessClaim::create([
                        'run_id' => $run->id,
                        'course_id' => $course->id,
                        'content_source' => 'student',
                        'module_id' => $c['module_id'],
                        'sentence_ref' => $c['sentence_ref'],
                        'claim_text' => $c['claim_text'],
                        'category' => $c['category'],
                    ]);
                    if ($this->verifyClaim($claim, $config)) {
                        $studentObsolete[] = $claim;
                    }
                }
            }

            // ===== Fase 3 — proposte (D2: formatore e studente indipendenti per toggle). =====
            $proposalsCreated = 0;
            if ($config->proposals_enabled) {
                $proposalsCreated += $this->generateProposals($run, $course, $config, $obsolete);
            }
            if ($config->student_proposals_enabled) {
                $proposalsCreated += $this->generateProposals($run, $course, $config, $studentObsolete);
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'claims_found' => count($extracted['claims']) + $studentClaimsFound,
                'proposals_created' => $proposalsCreated,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $run->refresh();
    }

    /**
     * Fase 3 — per ogni claim obsoleto genera l'`after` e scrive una proposta `pending`.
     * Resiliente: una generazione fallita su un claim non ferma la run. Ritorna il numero
     * di proposte create. NULLA viene applicato qui (solo pending; HITL non negoziabile).
     *
     * @param  list<FreshnessClaim>  $obsoleteClaims
     */
    private function generateProposals(FreshnessRun $run, Course $course, CourseFreshnessConfig $config, array $obsoleteClaims): int
    {
        $created = 0;
        foreach ($obsoleteClaims as $claim) {
            try {
                $gen = $this->generator->generate($claim->claim_text, $claim->category, [
                    'source_url' => $claim->source_url,
                ]);

                UpdateProposal::create([
                    'run_id' => $run->id,
                    'freshness_claim_id' => $claim->id,
                    'course_id' => $course->id,
                    'content_source' => $claim->content_source, // instructor | student
                    'block_id' => $claim->block_id,   // valorizzato solo per instructor
                    'module_id' => $claim->module_id, // valorizzato solo per student
                    'sentence_ref' => $claim->sentence_ref,
                    'before' => $claim->claim_text, // verbatim: ancora del diff
                    'after' => $gen['after'],
                    'reason' => $gen['reason'],
                    'source' => $claim->source_url,
                    'source_type' => $claim->source_type,
                    'confidence' => $claim->confidence,
                    'audience' => $config->audience ?? 'adult',
                    'status' => 'pending',
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::warning('[FreshnessAgent] generazione proposta fallita, claim saltato', [
                    'claim_id' => $claim->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Fase 2 — verifica un claim (formatore o studente, stesso verificatore). Aggiorna il
     * claim col verdetto. Resiliente: un errore non ferma la run. Ritorna true se obsoleto.
     */
    private function verifyClaim(FreshnessClaim $claim, CourseFreshnessConfig $config): bool
    {
        try {
            $v = $this->verifier->verify($claim->claim_text, $claim->category, $config);
            $claim->update([
                'verdict' => $v['verdict'],
                'source_url' => $v['source_url'],
                'source_type' => $v['source_type'],
                'source_date' => $v['source_date'],
                'confidence' => $v['confidence'],
                'verified_at' => now(),
            ]);
            return $v['verdict'] === 'obsoleto';
        } catch (\Throwable $e) {
            Log::warning('[FreshnessAgent] verifica claim fallita, lascio non verificato', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /** Ultimo sorgente (o versione richiesta). Fail pulito se assente. */
    private function loadSource(Course $course, ?string $version): CourseSource
    {
        $query = $course->sources();
        if ($version !== null) {
            $query->where('version', $version);
        }
        $source = $query->first(); // sources() è già orderByDesc(created_at)

        if (!$source) {
            $msg = $version !== null
                ? "Nessun course_sources v{$version} per il corso {$course->id}"
                : "Nessun course_sources per il corso {$course->id}: eseguire prima course:recover-source";
            throw new RuntimeException($msg);
        }

        return $source;
    }
}
