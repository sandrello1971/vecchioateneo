<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use App\Services\CourseDocumentParser;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

/**
 * Orchestratore SICURO del reimport corso-da-.md. Incatena i passi proteggendo
 * dalle trappole già viste (backup saltato, write accidentale):
 *
 *  - SENZA --confirm (default): valida + DRY-RUN (riusa course:reimport-from-markdown)
 *    + avvisi sui casi-limite. Non scrive, non fa backup.
 *  - CON --confirm: ri-valida + BACKUP pg_dump verificato (abort se troncato) + write
 *    clean-slate + report post-write con le liste per il ri-aggancio canvas (manuale).
 *
 * Riusa la logica esistente (ReimportCourseFromMarkdown / CourseDocumentParser):
 * non duplica il parsing. Scoping per slug. Idempotente.
 */
class ReimportCourseSafe extends Command
{
    protected $signature = 'course:reimport-safe
        {course_slug : slug del corso ESISTENTE}
        {md_path : path al manuale .md (UTF-8 pulito, già ribrandizzato)}
        {--confirm : esegue davvero (backup + write); senza, è solo dry-run sicuro}';

    protected $description = 'Reimport sicuro: backup automatico + dry-run; il write avviene solo con --confirm e backup valido.';

    public function handle(CourseDocumentParser $parser, DatabaseBackupService $backup): int
    {
        $slug = (string) $this->argument('course_slug');
        $mdPath = (string) $this->argument('md_path');
        $confirm = (bool) $this->option('confirm');

        // --- Validazione (sempre) ---
        $course = Course::where('slug', $slug)->first();
        if (!$course) {
            $this->error("Corso non trovato per slug: {$slug}");
            return self::FAILURE;
        }
        if (!is_file($mdPath)) {
            $this->error("File .md non trovato: {$mdPath}");
            return self::FAILURE;
        }
        if (!is_readable($mdPath)) {
            $this->error("Il file .md non è leggibile dall'utente corrente: {$mdPath}");
            $this->line('  → sistemare i permessi (es. chmod 644) e riprovare.');
            return self::FAILURE;
        }

        // --- DRY-RUN (riusa il comando esistente, niente --write) ---
        $this->call('course:reimport-from-markdown', [
            'course_slug' => $slug,
            'md_path' => $mdPath,
        ]);

        // --- Avvisi automatici sui casi-limite (non bloccanti) ---
        $this->emitWarnings($parser, $mdPath);

        if (!$confirm) {
            $this->newLine();
            $this->warn('DRY-RUN completato. Per eseguire: rilancia con --confirm');
            return self::SUCCESS;
        }

        // ====================  WRITE PATH (--confirm)  ====================

        // 1) Backup verificato — senza backup valido NON si scrive.
        $this->newLine();
        $this->info('Backup del database in corso (pg_dump)…');
        try {
            $backupPath = $backup->dump($slug, now()->format('Ymd_His'));
        } catch (\Throwable $e) {
            $this->error('ABORT: backup fallito — ' . $e->getMessage());
            return self::FAILURE;
        }
        if (!$backup->isValid($backupPath)) {
            $this->error("ABORT: backup non valido o troncato ({$backupPath}). Nessuna scrittura eseguita.");
            return self::FAILURE;
        }
        $sizeMb = round(filesize($backupPath) / 1_048_576, 1);
        $this->info("Backup OK: {$backupPath} ({$sizeMb} MB)");

        // 2) Write clean-slate (riusa il comando esistente con --write).
        $this->newLine();
        $exit = $this->call('course:reimport-from-markdown', [
            'course_slug' => $slug,
            'md_path' => $mdPath,
            '--write' => true,
        ]);
        if ($exit !== self::SUCCESS) {
            $this->error('Il write è fallito. Il backup resta disponibile: ' . $backupPath);
            return self::FAILURE;
        }

        // 3) Report post-write.
        $this->postWriteReport($course->fresh());

        return self::SUCCESS;
    }

    /** Avvisi DA VERIFICARE: moduli-intestazione (<150 char), split-level 2, zero divisori. */
    private function emitWarnings(CourseDocumentParser $parser, string $mdPath): void
    {
        $normalized = $parser->normalizeMarkdownHtml($parser->convertManualToHtml($mdPath));
        $level = $parser->suggestSplitLevel($normalized);
        $modules = $parser->splitIntoModules($normalized, $level);

        $warnings = [];

        foreach ($modules as $m) {
            $bodyLen = mb_strlen(trim(strip_tags($m['content_html'])));
            // Un modulo di CONTENUTO troppo corto è probabilmente un'intestazione
            // finita male (es. "MANUALE DISCENTE — …"); i divisori corti sono attesi.
            if (!$m['is_divider'] && $bodyLen < 150) {
                $warnings[] = "modulo di contenuto molto corto ({$bodyLen} char): \"{$m['title']}\" — possibile intestazione/frammento";
            }
        }

        if ($level === 2) {
            $warnings[] = 'split-level 2 (granularità # titolo + ## capitoli): verifica che i moduli siano i capitoli giusti';
        }

        $nDiv = count(array_filter($modules, fn ($m) => $m['is_divider']));
        if ($nDiv === 0) {
            $warnings[] = 'nessun divisore (PARTE/SEZIONE) rilevato: se il manuale è a Parti, verifica la struttura';
        }

        if ($warnings === []) {
            return;
        }

        $this->newLine();
        $this->warn('DA VERIFICARE (' . count($warnings) . '):');
        foreach ($warnings as $w) {
            $this->line('  ⚠ ' . $w);
        }
    }

    /** Liste per il ri-aggancio canvas (lasciato al giudizio umano) + sanity Noscite. */
    private function postWriteReport(Course $course): void
    {
        $modules = $course->modules()->orderBy('sort_order')->get(['id', 'sort_order', 'title', 'content']);
        $noscite = $modules->filter(fn ($m) => stripos((string) $m->content, 'noscite') !== false);

        // Materiali del corso: i canvas staccati (module_id NULL) da ri-agganciare.
        $materials = Material::where('course_id', $course->id)
            ->orWhereIn('module_id', $modules->pluck('id'))
            ->get(['id', 'title', 'file_type', 'module_id']);
        $canvas = $materials->where('file_type', 'canvas');

        $this->newLine();
        $this->info('REPORT POST-WRITE');
        $this->line('  • moduli nuovi: ' . $modules->count());
        $this->line('  • materiali del corso: ' . $materials->count() . ' (canvas: ' . $canvas->count() . ')');
        $this->line('  • moduli con "Noscite" nel content: <comment>' . $noscite->count() . '</comment>'
            . ($noscite->count() === 0 ? ' ✓' : ' (il .md andava ribrandizzato!)'));

        $this->newLine();
        $this->line('MATERIALI del corso (da ri-agganciare ai moduli):');
        $this->table(['id', 'title', 'file_type', 'module_id attuale'],
            $materials->map(fn ($mt) => [
                substr($mt->id, 0, 8), mb_strimwidth($mt->title, 0, 40, '…'), $mt->file_type, $mt->module_id ?: 'NULL',
            ])->all());

        $this->newLine();
        $this->line('MODULI nuovi (target del ri-aggancio):');
        $this->table(['sort', 'title'],
            $modules->map(fn ($m) => [$m->sort_order, mb_strimwidth($m->title, 0, 60, '…')])->all());

        $this->newLine();
        $this->warn('PROSSIMO PASSO: mappare i canvas ai moduli (vedi liste sopra), poi UPDATE manuale '
            . 'di materials.module_id. Il ri-aggancio NON è automatico: richiede giudizio umano.');
    }
}
