<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sostituzione mirata del brand "The Glitch World" → "Noscite" nel campo content
 * dei moduli. Con --dry-run mostra un'anteprima (slug corso, titolo modulo, estratto
 * PRIMA/DOPO) senza scrivere. Senza --dry-run applica in transazione e logga ogni
 * modulo modificato. Sostituisce SOLO la stringa esatta: nient'altro viene toccato.
 */
class ReplaceBrandInModules extends Command
{
    protected $signature = 'brand:replace-modules {--dry-run : Mostra le modifiche senza scrivere nulla}';

    protected $description = 'Sostituisce "The Glitch World" con "Noscite" nel content dei moduli';

    private const FROM = 'The Glitch World';
    private const TO = 'Noscite';
    private const CTX = 40; // caratteri di contesto per lato (~80 attorno all'occorrenza)

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $modules = Module::query()
            ->where('content', 'like', '%' . self::FROM . '%')
            ->with('course:id,slug')
            ->get();

        if ($modules->isEmpty()) {
            $this->info('Nessun modulo contiene "' . self::FROM . '". Niente da fare.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . $modules->count() . ' moduli interessati:');
        $this->newLine();

        foreach ($modules as $m) {
            $slug = $m->course->slug ?? '(senza corso)';
            $occ = substr_count($m->content, self::FROM);
            $this->line("<fg=cyan;options=bold>Corso:</>  {$slug}");
            $this->line("<fg=cyan;options=bold>Modulo:</> {$m->title}  <fg=gray>(occorrenze: {$occ})</>");
            foreach ($this->excerpts($m->content) as $ex) {
                $this->line('  <fg=red>PRIMA:</> …' . $ex['before'] . '…');
                $this->line('  <fg=green>DOPO :</> …' . $ex['after'] . '…');
            }
            $this->newLine();
        }

        if ($dry) {
            $this->warn('DRY-RUN: nessuna modifica scritta nel database.');
            $this->line('Per applicare: <options=bold>php artisan brand:replace-modules</> (senza --dry-run).');
            return self::SUCCESS;
        }

        // Applicazione reale in transazione.
        $updated = 0;
        DB::transaction(function () use ($modules, &$updated) {
            foreach ($modules as $m) {
                $new = str_replace(self::FROM, self::TO, $m->content);
                if ($new === $m->content) {
                    continue;
                }
                $m->content = $new;
                $m->save();
                $updated++;
                // Canale 'audit' dedicato: sempre registrato, a prescindere da LOG_LEVEL.
                Log::channel('audit')->info('[brand:replace-modules] modulo aggiornato', [
                    'module_id' => $m->id,
                    'course_slug' => $m->course->slug ?? null,
                    'title' => $m->title,
                    'from' => self::FROM,
                    'to' => self::TO,
                ]);
            }
        });

        $this->info("Fatto: {$updated} moduli aggiornati ('" . self::FROM . "' → '" . self::TO . "'). Vedi laravel.log per il dettaglio.");
        return self::SUCCESS;
    }

    /**
     * Estratti ~80 caratteri attorno a OGNI occorrenza (PRIMA/DOPO). Usa funzioni
     * multibyte per non spezzare i caratteri accentati nella finestra di contesto.
     *
     * @return list<array{before: string, after: string}>
     */
    private function excerpts(string $content): array
    {
        $out = [];
        $len = mb_strlen(self::FROM);
        $offset = 0;
        while (($pos = mb_strpos($content, self::FROM, $offset)) !== false) {
            $start = max(0, $pos - self::CTX);
            $window = mb_substr($content, $start, ($pos - $start) + $len + self::CTX);
            $before = $this->clean($window);
            $after = $this->clean(str_replace(self::FROM, self::TO, $window));
            $out[] = ['before' => $before, 'after' => $after];
            $offset = $pos + $len;
        }

        return $out;
    }

    /** Normalizza gli spazi/ritorni a capo per un estratto leggibile su una riga. */
    private function clean(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
