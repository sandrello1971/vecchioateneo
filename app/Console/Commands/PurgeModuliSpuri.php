<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Module;
use App\Models\Material;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rimuove i moduli "spuri" vuoti dai corsi scuola: intestazioni strutturali
 * (PARTE PRIMA/SECONDA, titolo-corso) che l'ingest ha trasformato in moduli.
 *
 * SICUREZZA A TRIPLA CONDIZIONE: un modulo viene rimosso SOLO se
 *   1) il titolo matcha un pattern "strutturale" (PARTE.., titolo-corso), E
 *   2) ha contenuto sotto la soglia (default 400 char), E
 *   3) NON ha materiali/canvas agganciati.
 * Se anche una sola condizione non regge, il modulo NON viene toccato.
 * Cosi' Introduzione, Glossario, Appendice-template (che hanno testo) restano SEMPRE.
 *
 * Transazione unica, --dry-run (rollback), logging [purge-moduli-spuri].
 */
class PurgeModuliSpuri extends Command
{
    protected $signature = 'scuola:purge-moduli-spuri
                            {--soglia=400 : Soglia char sotto cui un modulo e\' considerato guscio vuoto}
                            {--dry-run : Mostra le operazioni senza scrivere}';

    protected $description = 'Rimuove i moduli spuri vuoti (PARTE.., titolo-corso) dai corsi scuola.';

    private const SLUGS = [
        'umanesimo-digitale-e-ai-licei',
        'schola-umanesimo-digitale-e-ai-istituti-tecnici',
        'schola-umanesimo-digitale-e-ai-ist-tech-informatici',
        'schola-ai-per-dirigenti-scolastici-e-docenti',
        'initium-fondamenta-ai-operativa',
    ];

    /**
     * Pattern dei titoli considerati "strutturali" (candidati alla rimozione).
     * Solo intestazioni di struttura del manuale, MAI contenuti veri.
     */
    private function isStrutturale(string $title): bool
    {
        $t = mb_strtoupper($title);
        // "PARTE PRIMA/SECONDA/TERZA/QUARTA/QUINTA/SESTA..." (intestazioni di sezione)
        if (preg_match('/^\s*PARTE\s+(PRIMA|SECONDA|TERZA|QUARTA|QUINTA|SESTA|SETTIMA)/u', $t)) return true;
        // titolo-corso finito come modulo
        if (mb_strpos($t, 'UMANESIMO DIGITALE E INTELLIGENZA ARTIFICIALE') !== false) return true;
        return false;
    }

    private bool $dry = false;
    private int $removed = 0;
    private int $keptSafe = 0;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $soglia = (int) $this->option('soglia');

        $this->info('=== Purge moduli spuri (corsi scuola) ===');
        $this->line('Soglia guscio vuoto: ' . $soglia . ' char');
        $this->line('Mode: ' . ($this->dry ? 'DRY RUN (nessuna scrittura)' : 'LIVE'));
        $this->newLine();

        DB::beginTransaction();
        try {
            foreach (self::SLUGS as $slug) {
                $course = Course::where('slug', $slug)->first();
                if (!$course) { $this->warn("corso $slug non trovato"); continue; }

                $moduli = Module::where('course_id', $course->id)->orderBy('sort_order')->get();
                $this->line("--- $slug ---");

                foreach ($moduli as $mo) {
                    $len = mb_strlen((string) $mo->content);
                    $mat = Material::where('module_id', $mo->id)->count();

                    $strutturale = $this->isStrutturale($mo->title);

                    if (!$strutturale) {
                        continue; // non e' un candidato: e' un modulo vero (o Introduzione/Glossario)
                    }

                    // e' strutturale: applico le due condizioni di sicurezza restanti
                    if ($len >= $soglia) {
                        $this->line("  KEEP (ha contenuto {$len}ch): {$mo->title}");
                        $this->keptSafe++;
                        continue;
                    }
                    if ($mat > 0) {
                        $this->line("  KEEP (ha {$mat} materiali agganciati): {$mo->title}");
                        $this->keptSafe++;
                        continue;
                    }

                    // tutte e tre le condizioni ok -> rimuovibile
                    if ($this->dry) {
                        $this->line("  WOULD DELETE ({$len}ch, mat:0): {$mo->title}");
                    } else {
                        $mo->delete();
                        Log::info("[purge-moduli-spuri] deleted module \"{$mo->title}\" from $slug (len=$len)");
                        $this->line("  DELETED: {$mo->title}");
                    }
                    $this->removed++;
                }
            }

            if ($this->dry) {
                DB::rollBack();
                $this->newLine();
                $this->warn("DRY RUN. Da rimuovere: {$this->removed}, protetti (contenuto/materiali): {$this->keptSafe}.");
                return 0;
            }
            DB::commit();
            $this->newLine();
            $this->info("Completato. Rimossi: {$this->removed}, protetti: {$this->keptSafe}.");
            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Errore: ' . $e->getMessage());
            Log::error('[purge-moduli-spuri] failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
