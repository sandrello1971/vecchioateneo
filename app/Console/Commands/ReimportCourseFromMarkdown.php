<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Services\CourseDocumentParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-import "clean slate" dei moduli di un corso ESISTENTE dal suo manuale .md
 * (già ribrandizzato e UTF-8 pulito).
 *
 * DRY-RUN di default: senza --write non scrive nulla, mostra solo anteprima+report.
 * Con --write: stacca i materiali dei moduli (module_id=NULL + course_id valorizzato,
 * così i canvas NON vengono cancellati dal CASCADE e restano sul corso), cancella i
 * moduli vecchi, ricrea dal .md (pandoc gfm → split al livello scelto → is_divider).
 *
 * Granularità robusta: --split-level=1|2 forza il livello; in automatico distingue
 * i .md "molti #" (capitoli a #) dai .md "un # titolo + molti ##" (capitoli a ##).
 *
 * I .md sono già senza branding "Noscite" → nessuna sostituzione: import as-is.
 * Solo il corso indicato per slug. Mai prod senza backup (passo separato).
 */
class ReimportCourseFromMarkdown extends Command
{
    protected $signature = 'course:reimport-from-markdown
        {course_slug : slug del corso ESISTENTE da ricostruire}
        {md_path : path al manuale .md (UTF-8 pulito, già ribrandizzato)}
        {--split-level= : forza il livello di split (1=#, 2=##); default = auto-detect}
        {--write : esegue davvero il clean-slate; senza, è solo DRY-RUN}';

    protected $description = 'Re-import clean-slate dei moduli di un corso esistente dal manuale .md (dry-run di default).';

    public function handle(CourseDocumentParser $parser): int
    {
        $slug = (string) $this->argument('course_slug');
        $mdPath = (string) $this->argument('md_path');
        $write = (bool) $this->option('write');

        $course = Course::where('slug', $slug)->first();
        if (!$course) {
            $this->error("Corso non trovato per slug: {$slug}");
            return self::FAILURE;
        }
        if (!is_file($mdPath)) {
            $this->error("File .md non trovato: {$mdPath}");
            return self::FAILURE;
        }

        // --- Conversione + scelta livello ---
        $normalized = $parser->normalizeMarkdownHtml($parser->convertManualToHtml($mdPath));
        $h1 = preg_match_all('/<h1[^>]*>/i', $normalized);
        $h2 = preg_match_all('/<h2[^>]*>/i', $normalized);

        $forced = $this->option('split-level');
        if ($forced !== null && !in_array((string) $forced, ['1', '2'], true)) {
            $this->error('--split-level ammette solo 1 o 2.');
            return self::FAILURE;
        }
        $level = $forced !== null ? (int) $forced : $parser->suggestSplitLevel($normalized);
        $reason = $forced !== null
            ? "forzato da --split-level={$level}"
            : ($level === 2
                ? "auto: {$h1} h1 (titolo-manuale) + {$h2} h2 → i moduli sono i ##"
                : "auto: {$h1} h1 → i moduli sono i #");

        $modules = $parser->splitIntoModules($normalized, $level);

        // --- Stato attuale del corso ---
        $oldModuleIds = $course->modules()->pluck('id')->all();
        $materialsTotal = Material::whereIn('module_id', $oldModuleIds)->count();
        $canvasTotal = Material::whereIn('module_id', $oldModuleIds)->where('file_type', 'canvas')->count();

        // --- Anteprima ---
        $this->info("Corso: {$course->name}  (slug: {$course->slug})");
        $this->line("Livello di split: {$level}  [{$reason}]");
        $this->line("h1={$h1}  h2={$h2}");
        $this->newLine();

        $rows = [];
        foreach ($modules as $i => $m) {
            $bodyLen = mb_strlen(trim(strip_tags($m['content_html'])));
            $rows[] = [
                $i,
                mb_strimwidth($m['title'], 0, 60, '…'),
                $m['is_divider'] ? 'DIVISORE' : 'contenuto',
                $bodyLen,
            ];
        }
        $this->table(['sort', 'titolo', 'tipo', 'len(testo)'], $rows);

        $nDiv = count(array_filter($modules, fn ($m) => $m['is_divider']));
        $nCont = count($modules) - $nDiv;
        $this->newLine();
        $this->line("Moduli da creare: <info>" . count($modules) . "</info> ({$nCont} contenuto + {$nDiv} divisori)");
        $this->line("Moduli attuali da CANCELLARE: <comment>" . count($oldModuleIds) . "</comment>");
        $this->line("Materiali da STACCARE (preservati, module_id→NULL): <comment>{$materialsTotal}</comment> (di cui canvas: {$canvasTotal})");

        if (!$write) {
            $this->newLine();
            $this->warn('DRY-RUN: nessuna modifica al DB. Rilancia con --write per eseguire davvero.');
            return self::SUCCESS;
        }

        // --- Clean slate (solo con --write) ---
        DB::transaction(function () use ($course, $oldModuleIds, $modules) {
            // 1) Stacca i materiali PRIMA del delete (FK module_id è CASCADE):
            //    module_id=NULL li preserva; course_id li tiene legati al corso.
            Material::whereIn('module_id', $oldModuleIds)
                ->update(['module_id' => null, 'course_id' => $course->id]);

            // 2) Cancella i moduli vecchi del corso.
            Module::whereIn('id', $oldModuleIds)->delete();

            // 3) Ricrea dal .md, nell'ordine del file.
            $sort = 0;
            foreach ($modules as $m) {
                Module::create([
                    'course_id' => $course->id,
                    'title' => $m['title'],
                    'content' => $m['content_html'],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]);
            }
        });

        $this->newLine();
        $this->info('FATTO (--write): clean slate eseguito.');
        $this->line("  • materiali staccati e preservati: {$materialsTotal} (canvas: {$canvasTotal})");
        $this->line('  • moduli cancellati: ' . count($oldModuleIds));
        $this->line('  • moduli creati: ' . count($modules));

        return self::SUCCESS;
    }
}
