<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Module;
use App\Models\Material;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ridistribuisce i canvas gia' importati sui moduli tematici corretti,
 * e rimuove dai corsi i canvas non pertinenti (record Material; il file su disco resta).
 *
 * Idempotente: se un canvas e' gia' sul modulo giusto non fa nulla.
 * Transazione unica, --dry-run (rollback), logging [canvas-remap].
 *
 * Mappa MOVE:   slug corso => [ titolo canvas => titolo modulo di destinazione ]
 * Mappa REMOVE: slug corso => [ titoli canvas da rimuovere da quel corso ]
 */
class RemapCanvasScuola extends Command
{
    protected $signature = 'scuola:remap-canvas {--dry-run : Mostra le operazioni senza scrivere}';
    protected $description = 'Ridistribuisce i canvas sui moduli tematici e rimuove i non pertinenti.';

    private const LICEI   = 'umanesimo-digitale-e-ai-licei';
    private const TECG    = 'schola-umanesimo-digitale-e-ai-istituti-tecnici';
    private const TECI    = 'schola-umanesimo-digitale-e-ai-ist-tech-informatici';
    private const DOCENTI = 'schola-ai-per-dirigenti-scolastici-e-docenti';
    private const GOVDIR  = 'schola-governance-dellia-per-il-dirigente-scolastico';
    private const GOVAMM  = 'schola-governance-dellia-per-il-personale-amministrativo';

    /** slug => [ titolo canvas => titolo modulo destinazione ] */
    private function moveMap(): array
    {
        // NB: i titoli modulo usano il trattino tipografico (—) e l'apostrofo tipografico (') come nel DB.
        return [
            self::LICEI => [
                'Lo specchio digitale'              => 'MODULO L3 — La dimensione psicologica: identità, emozioni e relazioni',
                'La bussola etica'                  => 'MODULO L2 — L’AI come tema filosofico, sociale, civico',
                'Dialogo socratico con l\'AI'       => 'MODULO L1 — AI come strumento di studio e ricerca',
                'Riconoscere un deepfake'           => 'MODULO 2 — Usare l’AI in sicurezza',
                'Prompt safety per studenti'        => 'MODULO 2 — Usare l’AI in sicurezza',
                'Fact-check delle fonti AI'         => 'MODULO 2 — Usare l’AI in sicurezza',
                'Patto d\'uso personale dell\'AI'   => 'MODULO 3 — AI e studio: il patto educativo',
                'Dialogo strutturato con il docente'=> 'MODULO 3 — AI e studio: il patto educativo',
                'Decision tree etico'               => 'MODULO 4 — Diritti, responsabilità e quadro normativo',
            ],
            self::TECG => [
                'Dialogo con l\'AI nel lavoro'       => 'MODULO T1 — AI nel mondo del lavoro',
                'Il mio mestiere e l\'AI'            => 'MODULO T2 — AI, lavoro e futuro',
                'Riconoscere un deepfake'            => 'MODULO 2 — Usare l’AI in sicurezza',
                'Prompt safety per studenti'         => 'MODULO 2 — Usare l’AI in sicurezza',
                'Fact-check delle fonti AI'          => 'MODULO 2 — Usare l’AI in sicurezza',
                'Patto d\'uso personale dell\'AI'    => 'MODULO 3 — AI e studio: il patto educativo',
                'Dialogo strutturato con il docente' => 'MODULO 3 — AI e studio: il patto educativo',
                'Decision tree etico'                => 'MODULO 4 — Diritti, responsabilità e quadro normativo',
            ],
            self::TECI => [
                'Dialogo con l\'AI nel lavoro'       => 'MODULO T1 — AI nel mondo del lavoro',
                'Il mio mestiere e l\'AI'            => 'MODULO T2 — AI, lavoro e futuro',
                'Checklist di sicurezza del codice'  => 'MODULO T3 — Approfondimento tecnico: automazioni, agenti e sicurezza',
                'Riconoscere un deepfake'            => 'MODULO 2 — Usare l’AI in sicurezza',
                'Prompt safety per studenti'         => 'MODULO 2 — Usare l’AI in sicurezza',
                'Fact-check delle fonti AI'          => 'MODULO 2 — Usare l’AI in sicurezza',
                'Patto d\'uso personale dell\'AI'    => 'MODULO 3 — AI e studio: il patto educativo',
                'Dialogo strutturato con il docente' => 'MODULO 3 — AI e studio: il patto educativo',
                'Decision tree etico'                => 'MODULO 4 — Diritti, responsabilità e quadro normativo',
            ],
            self::DOCENTI => [
                'Progettare un\'attività AI-aware'   => 'CAPITOLO 2 — AI nella didattica',
                'Differenziare un materiale per BES/DSA' => 'CAPITOLO 2 — AI nella didattica',
                'Costruire verifiche AI-resistant'   => 'CAPITOLO 3 — Valutazione, plagio e proctoring AI',
                'Rubrica di valutazione del processo'=> 'CAPITOLO 3 — Valutazione, plagio e proctoring AI',
                'Dialogo strutturato sul plagio'     => 'CAPITOLO 3 — Valutazione, plagio e proctoring AI',
                'Curriculum di AI literacy'          => 'CAPITOLO 4 — AI literacy degli studenti',
                'Protocollo di valutazione AI-aware' => 'CAPITOLO 8 — Workshop L2 Blocco B: Patto educativo AI',
                'AI Inventory d\'istituto'           => 'CAPITOLO 7 — Workshop L2 Blocco A: Audit dei sistemi AI nella scuola',
                'Checklist di audit annuale'         => 'CAPITOLO 7 — Workshop L2 Blocco A: Audit dei sistemi AI nella scuola',
                'Decision tree — Allegato III AI Act'=> 'CAPITOLO 1 — Il framework AI Act per la scuola',
                'Patto educativo sull\'IA'           => 'CAPITOLO 8 — Workshop L2 Blocco B: Patto educativo AI',
                'Politica sull\'IA generativa'       => 'CAPITOLO 9 — Workshop L2 Blocco C: Curriculum + politica + protocollo',
                'Informativa per i minori'           => 'CAPITOLO 5 — Governance scolastica dell’AI',
            ],
            self::GOVDIR => [
                'Test di supervisione umana sostanziale' => 'MODULO 1 — I due pilastri',
                'Decision tree — Allegato III AI Act'    => 'MODULO 2 — Il quadro normativo essenziale',
                'Scheda di adozione in dieci step'       => 'MODULO 6 — Il workflow di adozione in dieci step',
                'Patto educativo sull\'IA'               => 'MODULO 7 — Organi collegiali e Patto educativo',
                'AI Inventory d\'istituto'               => 'MODULO 8 — Workshop: l\'AI Inventory d\'istituto',
                'Matrice dei dati d\'istituto'           => 'MODULO 8 — Workshop: l\'AI Inventory d\'istituto',
                'Checklist di audit annuale'             => 'MODULO 9 — Chiusura: i documenti vivi',
            ],
            self::GOVAMM => [
                'Test di supervisione umana sostanziale' => 'MODULO 1 — I due pilastri',
                'Decision tree — Allegato III AI Act'    => 'MODULO 2 — Il quadro normativo essenziale',
                'Verifica del fornitore EdTech'          => 'MODULO 5 — Contratti con i fornitori e due diligence',
                'Matrice dei dati d\'istituto'           => 'MODULO 6 — L\'accordo sul trattamento (DPA) e i trattamenti',
                'Informativa AI-aware per le famiglie'   => 'MODULO 7 — Informative e gestione documentale',
                'Informativa per i minori'               => 'MODULO 7 — Informative e gestione documentale',
                'AI Inventory d\'istituto'               => 'MODULO 8 — Workshop: l\'AI Inventory d\'istituto',
                'Checklist di audit annuale'             => 'MODULO 9 — Chiusura: i documenti vivi',
            ],
        ];
    }

    /** slug => [ titoli canvas da RIMUOVERE da quel corso ] */
    private function removeMap(): array
    {
        return [
            self::GOVDIR => [
                'Curriculum di AI literacy',
                'Protocollo di valutazione AI-aware',
                'Dialogo strutturato sul plagio',
                'Verifica del fornitore EdTech',
                'Informativa AI-aware per le famiglie',
                'Informativa per i minori',
                'Politica sull\'IA generativa',
            ],
            self::GOVAMM => [
                'Scheda di adozione in dieci step',
                'Patto educativo sull\'IA',
                'Curriculum di AI literacy',
                'Protocollo di valutazione AI-aware',
                'Dialogo strutturato sul plagio',
                'Politica sull\'IA generativa',
            ],
        ];
    }

    private bool $dry = false;
    private int $moved = 0, $already = 0, $removed = 0, $warn = 0;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $this->info('=== Remap canvas scuola ===');
        $this->line('Mode: ' . ($this->dry ? 'DRY RUN (nessuna scrittura)' : 'LIVE'));
        $this->newLine();

        DB::beginTransaction();
        try {
            // 1) RIMOZIONI
            foreach ($this->removeMap() as $slug => $titoli) {
                $course = Course::where('slug', $slug)->first();
                if (!$course) { $this->warn("corso $slug non trovato"); continue; }
                foreach ($titoli as $t) {
                    $mat = Material::where('course_id', $course->id)
                        ->where('file_type', 'canvas')->where('title', $t)->first();
                    if (!$mat) { continue; }
                    if ($this->dry) {
                        $this->line("WOULD DELETE [$slug] $t");
                    } else {
                        $mat->delete();
                        Log::info("[canvas-remap] deleted \"$t\" from $slug");
                        $this->line("DELETED [$slug] $t");
                    }
                    $this->removed++;
                }
            }
            $this->newLine();

            // 2) SPOSTAMENTI
            foreach ($this->moveMap() as $slug => $map) {
                $course = Course::where('slug', $slug)->first();
                if (!$course) { $this->warn("corso $slug non trovato"); continue; }
                foreach ($map as $canvasTitle => $moduleTitle) {
                    $mat = Material::where('course_id', $course->id)
                        ->where('file_type', 'canvas')->where('title', $canvasTitle)->first();
                    if (!$mat) { continue; } // magari rimosso o non presente
                    $module = Module::where('course_id', $course->id)->where('title', $moduleTitle)->first();
                    if (!$module) {
                        $this->error("  [$slug] modulo non trovato: \"$moduleTitle\" (canvas: $canvasTitle)");
                        $this->warn++;
                        continue;
                    }
                    if ((string)$mat->module_id === (string)$module->id) {
                        $this->already++;
                        continue;
                    }
                    if ($this->dry) {
                        $this->line("WOULD MOVE [$slug] $canvasTitle  ->  $moduleTitle");
                    } else {
                        $mat->module_id = $module->id;
                        $mat->save();
                        Log::info("[canvas-remap] moved \"$canvasTitle\" to \"$moduleTitle\" in $slug");
                        $this->line("MOVED [$slug] $canvasTitle  ->  $moduleTitle");
                    }
                    $this->moved++;
                }
            }

            if ($this->dry) {
                DB::rollBack();
                $this->newLine();
                $this->warn("DRY RUN. Da spostare: {$this->moved}, gia' a posto: {$this->already}, da rimuovere: {$this->removed}, moduli non trovati: {$this->warn}.");
                return 0;
            }
            DB::commit();
            $this->newLine();
            $this->info("Completato. Spostati: {$this->moved}, gia' a posto: {$this->already}, rimossi: {$this->removed}, moduli non trovati: {$this->warn}.");
            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Errore: ' . $e->getMessage());
            Log::error('[canvas-remap] failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
