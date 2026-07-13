<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Module;
use App\Models\Material;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consolida CONSILIUM sul fork piu' recente "ai-governance-executive", che diventa
 * il canonico "CONSILIUM". Opzione A: sposta i record dei canvas (cambia course_id
 * e module_id), NON tocca i file su disco (il portale serve da file_path nel DB).
 *
 * Sequenza (transazione unica):
 *   1. Sposta i 6 canvas numerati di CONSILIUM -> governance-executive, sui moduli omonimi
 *   2. certification_name = "Attestato di partecipazione — CONSILIUM" sul canonico
 *   3. vecchio CONSILIUM: is_active=false + slug -> consilium-archiviato-2026
 *   4. governance-executive: name -> "CONSILIUM", slug -> consilium
 *
 * I canvas PACK_ gia' presenti su governance-executive restano (verifica successiva).
 *
 * --dry-run, logging [consolida-consilium].
 */
class ConsolidaConsilium extends Command
{
    protected $signature = 'corsi:consolida-consilium {--dry-run : Mostra senza scrivere}';
    protected $description = 'Consolida CONSILIUM sul canonico ai-governance-executive.';

    private const SRC  = 'consilium';                 // vecchio, cede i canvas e viene archiviato
    private const DST  = 'ai-governance-executive';   // canonico, diventa CONSILIUM
    private const CERT = 'Attestato di partecipazione — CONSILIUM';
    private const SRC_NEW_SLUG = 'consilium-archiviato-2026';

    /**
     * Canvas da spostare: titolo -> titolo del modulo di destinazione (sul canonico).
     * I moduli hanno gli stessi titoli nei due corsi (stesso testo di base).
     */
    private function canvasMap(): array
    {
        return [
            'Canvas 1 — Mappa dei processi per funzione' => 'Parte 2 — Mappare i processi e identificare le opportunità',
            'Canvas 2 — Scheda caso d\'uso AI'           => 'Parte 2 — Mappare i processi e identificare le opportunità',
            'Canvas 3 — Matrice impatto / fattibilità'   => 'Parte 2 — Mappare i processi e identificare le opportunità',
            'Canvas 4 — Scheda Progetto 1'               => 'Parte 3 — Scegliere i 3 progetti prioritari',
            'Canvas 4 — Scheda Progetto 2'               => 'Parte 3 — Scegliere i 3 progetti prioritari',
            'Canvas 4 — Scheda Progetto 3'               => 'Parte 3 — Scegliere i 3 progetti prioritari',
            'Canvas 5 — AI Usage Policy essenziale'      => 'Parte 4 — Le regole del gioco: AI Usage Policy essenziale',
            'Canvas 6 — Roadmap 90 giorni'               => 'Parte 5 — La roadmap a 90 giorni',
        ];
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info('=== Consolida CONSILIUM su ' . self::DST . ' ===');
        $this->line('Mode: ' . ($dry ? 'DRY RUN (nessuna scrittura)' : 'LIVE'));
        $this->newLine();

        DB::beginTransaction();
        try {
            $src = Course::where('slug', self::SRC)->first();
            $dst = Course::where('slug', self::DST)->first();
            if (!$src || !$dst) {
                $this->error('Corso sorgente o destinazione non trovato.');
                DB::rollBack();
                return 1;
            }

            // 1) SPOSTA I CANVAS
            $this->line('1) Sposto i canvas di CONSILIUM sul canonico:');
            $moved = 0; $warn = 0;
            foreach ($this->canvasMap() as $canvasTitle => $moduleTitle) {
                $mat = Material::where('course_id', $src->id)
                    ->where('file_type', 'canvas')->where('title', $canvasTitle)->first();
                if (!$mat) { $this->warn("   canvas non trovato su src: $canvasTitle"); $warn++; continue; }

                $mod = Module::where('course_id', $dst->id)->where('title', $moduleTitle)->first();
                if (!$mod) { $this->error("   modulo dest non trovato: \"$moduleTitle\""); $warn++; continue; }

                if ($dry) {
                    $this->line("   WOULD MOVE  $canvasTitle  ->  [".self::DST."] $moduleTitle");
                } else {
                    $mat->course_id = $dst->id;
                    $mat->module_id = $mod->id;
                    $mat->save();
                    Log::info("[consolida-consilium] moved canvas \"$canvasTitle\" to dst module \"$moduleTitle\"");
                    $this->line("   MOVED  $canvasTitle");
                }
                $moved++;
            }

            // 2) CERTIFICATION sul canonico
            $this->newLine();
            $this->line('2) certification_name sul canonico:');
            if ($dry) {
                $this->line('   WOULD SET certification_name = "' . self::CERT . '"');
            } else {
                $dst->certification_name = self::CERT;
                $dst->save();
                $this->line('   SET certification_name = "' . self::CERT . '"');
            }

            // 3) ARCHIVIA il vecchio CONSILIUM + libera lo slug
            $this->newLine();
            $this->line('3) Archivio il vecchio CONSILIUM e libero lo slug:');
            if ($dry) {
                $this->line('   WOULD SET [consilium] is_active=false, slug="' . self::SRC_NEW_SLUG . '"');
            } else {
                $src->is_active = false;
                $src->slug = self::SRC_NEW_SLUG;
                $src->save();
                Log::info('[consolida-consilium] archived old consilium, slug='.self::SRC_NEW_SLUG);
                $this->line('   DONE [' . self::SRC_NEW_SLUG . '] is_active=false');
            }

            // 4) RINOMINA il canonico -> CONSILIUM / consilium
            $this->newLine();
            $this->line('4) Rinomino il canonico in CONSILIUM:');
            if ($dry) {
                $this->line('   WOULD SET [' . self::DST . '] name="CONSILIUM", slug="consilium"');
            } else {
                $dst->name = 'CONSILIUM';
                $dst->slug = 'consilium';
                $dst->save();
                Log::info('[consolida-consilium] renamed '.self::DST.' -> CONSILIUM/consilium');
                $this->line('   DONE name="CONSILIUM", slug="consilium"');
            }

            if ($dry) {
                DB::rollBack();
                $this->newLine();
                $this->warn("DRY RUN. Canvas da spostare: $moved (warn: $warn). Nessuna scrittura.");
                return 0;
            }
            DB::commit();
            $this->newLine();
            $this->info("Completato. Canvas spostati: $moved (warn: $warn). Canonico = CONSILIUM/consilium.");
            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Errore: ' . $e->getMessage());
            Log::error('[consolida-consilium] failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
