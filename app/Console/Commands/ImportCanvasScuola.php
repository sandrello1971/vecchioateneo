<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Module;
use App\Models\Material;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Importa i canvas del Pacchetto Formazione Scuola nei corsi ESISTENTI di Atheneum.
 *
 * Non crea corsi: li trova per slug (cablati sotto, dai corsi reali del DB).
 * Aggancia ogni canvas al primo modulo del corso, come Material file_type='canvas'
 * su disco privato. Idempotente (skip se stesso title gia' presente nel corso).
 *
 * Uso:
 *   php artisan scuola:import-canvas --path=/tmp/PACCHETTO_FORMAZIONE_SCUOLA --dry-run
 *   php artisan scuola:import-canvas --path=/tmp/PACCHETTO_FORMAZIONE_SCUOLA
 *
 * Regole di distribuzione (cartella 02_CANVAS/<gruppo> -> corsi):
 *   studenti_LICEI    -> licei
 *   studenti_TECNICI  -> tecnici generali + informatici (T3 solo informatici)
 *   studenti_TRONCO   -> licei + tecnici generali + informatici
 *   docenti           -> docenti
 *   schola            -> docenti + governance dirigente + governance amministrativo
 *   governance        -> governance dirigente + governance amministrativo
 */
class ImportCanvasScuola extends Command
{
    protected $signature = 'scuola:import-canvas
                            {--path= : Root del pacchetto (contiene 02_CANVAS)}
                            {--dry-run : Mostra le operazioni senza scrivere}';

    protected $description = 'Importa i canvas del Pacchetto Formazione Scuola nei corsi scuola esistenti.';

    // Slug reali dei corsi su Atheneum
    private const SLUG_LICEI          = 'umanesimo-digitale-e-ai-licei';
    private const SLUG_TEC_GENERALI   = 'schola-umanesimo-digitale-e-ai-istituti-tecnici';
    private const SLUG_TEC_INFORMATICI= 'schola-umanesimo-digitale-e-ai-ist-tech-informatici';
    private const SLUG_DOCENTI        = 'schola-ai-per-dirigenti-scolastici-e-docenti';
    private const SLUG_GOV_DIRIGENTE  = 'schola-governance-dellia-per-il-dirigente-scolastico';
    private const SLUG_GOV_AMMIN      = 'schola-governance-dellia-per-il-personale-amministrativo';

    // Canvas che, nella cartella studenti_TECNICI, vanno SOLO su informatici
    private const TECNICI_SOLO_INFORMATICI = [
        'TECNICO_T3_canvas-checklist-sicurezza-codice.html',
    ];

    /** Titoli leggibili per i canvas (nome file -> titolo mostrato). */
    private const CANVAS_TITLES = [
        'DOCENTI_canvas-attivita-ai-aware.html'             => 'Progettare un\'attività AI-aware',
        'DOCENTI_canvas-differenziazione-bes-dsa.html'      => 'Differenziare un materiale per BES/DSA',
        'DOCENTI_canvas-rubrica-processo.html'              => 'Rubrica di valutazione del processo',
        'DOCENTI_canvas-verifiche-ai-resistant.html'        => 'Costruire verifiche AI-resistant',
        'LICEO_L1_canvas-dialogo-socratico-ai.html'         => 'Dialogo socratico con l\'AI',
        'LICEO_L2_canvas-bussola-etica-liceo.html'          => 'La bussola etica',
        'LICEO_L3_canvas-specchio-digitale.html'            => 'Lo specchio digitale',
        'SCHOLAGOV_canvas-adozione-10-step.html'            => 'Scheda di adozione in dieci step',
        'SCHOLAGOV_canvas-due-diligence-fornitore.html'     => 'Verifica del fornitore EdTech',
        'SCHOLAGOV_canvas-informativa-ai-aware.html'        => 'Informativa AI-aware per le famiglie',
        'SCHOLAGOV_canvas-matrice-dati-istituto.html'       => 'Matrice dei dati d\'istituto',
        'SCHOLAGOV_canvas-supervisione-sostanziale.html'    => 'Test di supervisione umana sostanziale',
        'SCHOLA_canvas-ai-inventory-scolastico.html'        => 'AI Inventory d\'istituto',
        'SCHOLA_canvas-audit-annuale-scuola.html'           => 'Checklist di audit annuale',
        'SCHOLA_canvas-curriculum-ai-literacy.html'         => 'Curriculum di AI literacy',
        'SCHOLA_canvas-decision-tree-allegato3-3.html'      => 'Decision tree — Allegato III AI Act',
        'SCHOLA_canvas-dialogo-strutturato-plagio.html'     => 'Dialogo strutturato sul plagio',
        'SCHOLA_canvas-informativa-minori.html'             => 'Informativa per i minori',
        'SCHOLA_canvas-patto-educativo-ai.html'             => 'Patto educativo sull\'IA',
        'SCHOLA_canvas-politica-ai-generativa.html'         => 'Politica sull\'IA generativa',
        'SCHOLA_canvas-protocollo-valutazione.html'         => 'Protocollo di valutazione AI-aware',
        'TECNICO_T1_canvas-dialogo-ai-lavoro.html'          => 'Dialogo con l\'AI nel lavoro',
        'TECNICO_T2_canvas-mestiere-e-ai.html'              => 'Il mio mestiere e l\'AI',
        'TECNICO_T3_canvas-checklist-sicurezza-codice.html' => 'Checklist di sicurezza del codice',
        'TRONCO_canvas-decision-tree-etica-ai.html'         => 'Decision tree etico',
        'TRONCO_canvas-dialogo-strutturato-docente.html'    => 'Dialogo strutturato con il docente',
        'TRONCO_canvas-fact-check-fonti-ai.html'            => 'Fact-check delle fonti AI',
        'TRONCO_canvas-patto-uso-ai-studio.html'            => 'Patto d\'uso personale dell\'AI',
        'TRONCO_canvas-prompt-safety-studenti.html'         => 'Prompt safety per studenti',
        'TRONCO_canvas-riconosci-deepfake.html'             => 'Riconoscere un deepfake',
    ];

    private bool $dry = false;
    private int $created = 0;
    private int $skipped = 0;
    private int $missing = 0;
    private array $courseCache = [];

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $root = rtrim((string) $this->option('path'), '/');
        if ($root === '' || !is_dir("$root/02_CANVAS")) {
            $this->error('Passare --path con la root del pacchetto (che contiene 02_CANVAS).');
            return 1;
        }

        // gruppo-cartella => slug di destinazione
        $map = [
            'studenti_LICEI'   => [self::SLUG_LICEI],
            'studenti_TECNICI' => [self::SLUG_TEC_GENERALI, self::SLUG_TEC_INFORMATICI],
            'studenti_TRONCO'  => [self::SLUG_LICEI, self::SLUG_TEC_GENERALI, self::SLUG_TEC_INFORMATICI],
            'docenti'          => [self::SLUG_DOCENTI],
            'schola'           => [self::SLUG_DOCENTI, self::SLUG_GOV_DIRIGENTE, self::SLUG_GOV_AMMIN],
            'governance'       => [self::SLUG_GOV_DIRIGENTE, self::SLUG_GOV_AMMIN],
        ];

        $this->info('=== Import Canvas Scuola ===');
        $this->line('Path: ' . $root);
        $this->line('Mode: ' . ($this->dry ? 'DRY RUN (nessuna scrittura)' : 'LIVE'));
        $this->newLine();

        DB::beginTransaction();
        try {
            foreach ($map as $folder => $slugs) {
                $dir = "$root/02_CANVAS/$folder";
                if (!is_dir($dir)) { $this->warn("  (manca $dir, salto)"); continue; }

                foreach (glob("$dir/*.html") as $html) {
                    $base = basename($html);

                    foreach ($slugs as $slug) {
                        // regola speciale: nella cartella tecnici, alcuni canvas solo su informatici
                        if ($folder === 'studenti_TECNICI'
                            && in_array($base, self::TECNICI_SOLO_INFORMATICI, true)
                            && $slug !== self::SLUG_TEC_INFORMATICI) {
                            continue; // salta: questo canvas non va sui tecnici generali
                        }

                        $course = $this->course($slug);
                        if (!$course) {
                            $this->error("  Corso '$slug' NON trovato -> salto $base");
                            $this->missing++;
                            continue;
                        }
                        $module = $this->firstModule($course);
                        if (!$module) {
                            $this->error("  Corso '$slug' senza moduli -> salto $base");
                            continue;
                        }
                        $this->importCanvas($html, $course, $module);
                    }
                }
            }

            if ($this->dry) {
                DB::rollBack();
                $this->newLine();
                $this->warn("DRY RUN. Creabili: {$this->created}, gia' presenti: {$this->skipped}, corsi mancanti: {$this->missing}.");
                return 0;
            }
            DB::commit();
            $this->newLine();
            $this->info("Completato. Canvas creati: {$this->created}, saltati: {$this->skipped}, corsi mancanti: {$this->missing}.");
            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Errore: ' . $e->getMessage());
            Log::error('[import-canvas-scuola] failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    private function course(string $slug): ?Course
    {
        if (!array_key_exists($slug, $this->courseCache)) {
            $this->courseCache[$slug] = Course::where('slug', $slug)->first();
        }
        return $this->courseCache[$slug];
    }

    private function firstModule(Course $course): ?Module
    {
        return Module::where('course_id', $course->id)->orderBy('sort_order')->first();
    }

    private function importCanvas(string $htmlPath, Course $course, Module $module): void
    {
        $base = basename($htmlPath);
        $title = self::CANVAS_TITLES[$base] ?? $base;

        $exists = Material::where('course_id', $course->id)
            ->where('file_type', 'canvas')
            ->where('title', $title)
            ->exists();
        if ($exists) {
            $this->line("    skip (gia' presente su {$course->slug}): $title");
            $this->skipped++;
            return;
        }

        $relPath = "materials/{$course->slug}/canvas/" . $base;

        if ($this->dry) {
            $this->line("    WOULD CREATE [{$course->slug}] $title");
            $this->created++;
            return;
        }

        Storage::disk('local')->put($relPath, file_get_contents($htmlPath));
        $sort = (int) Material::where('course_id', $course->id)->max('sort_order') + 1;

        $mat = Material::create([
            'module_id' => $module->id,
            'course_id' => $course->id,
            'title' => $title,
            'description' => 'Canvas compilabile (salvataggio per utente sul portale).',
            'file_path' => $relPath,
            'file_type' => 'canvas',
            'file_size' => filesize($htmlPath) ?: null,
            'is_downloadable' => false,
            'is_instructor_only' => false,
            'sort_order' => $sort,
        ]);
        Log::info("[import-canvas-scuola] created \"{$title}\" course={$course->slug} id={$mat->id}");
        $this->line("    CREATED [{$course->slug}] $title");
        $this->created++;
    }
}
