<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Normalizza il copyright di OGNI corso a un'unica dicitura pulita, SENZA nomi di
 * azienda: "© Stefano Andrello · tutti i diritti riservati.".
 *
 * Regola (verificata sui dati): la dicitura vive in coda al content dell'ULTIMO
 * modulo del corso (max sort_order).
 *  - Se l'ultimo modulo ha già un'attribuzione (contiene "Stefano Andrello"),
 *    rimuove l'intero colophon in coda (credito autore + azienda/tagline/date/email)
 *    e vi mette la riga canonica.
 *  - Altrimenti aggiunge la riga canonica in coda.
 * Idempotente: se il modulo termina già con la riga canonica, salta.
 */
class NormalizeCourseCopyright extends Command
{
    protected $signature = 'course:copyright {--dry-run : Mostra le modifiche senza scrivere nulla}';

    protected $description = 'Normalizza il copyright dei corsi a "© Stefano Andrello · tutti i diritti riservati." (nessun nome azienda)';

    private const CANON = '<p><em>© Stefano Andrello · tutti i diritti riservati.</em></p>';

    // Marcatori che compaiono SOLO nei colophon (credito/copyright), MAI nel corpo:
    // "Noscite" da solo è escluso perché appare anche nel testo ("Noscite lo lancia…").
    private const MARKERS = [
        'Stefano Andrello', 'Il Rumore Che Serve', 'theglitchworld',
        'Tutti i diritti riservati', 'tutti i diritti riservati',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $courses = Course::orderBy('slug')->get();

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Normalizzo il copyright su {$courses->count()} corsi.");
        $this->line('Dicitura: ' . self::CANON);
        $this->newLine();

        $planned = 0;
        $rows = [];
        foreach ($courses as $course) {
            // reorder() azzera l'orderBy('sort_order') ASC di default della relazione,
            // altrimenti prevarrebbe e prenderei il PRIMO modulo invece dell'ultimo.
            $last = $course->modules()->reorder()->orderByDesc('sort_order')->orderByDesc('id')->first();
            if (!$last) {
                $this->line("<fg=yellow>SALTO</> {$course->slug} — nessun modulo.");
                continue;
            }

            $content = (string) $last->content;
            if (str_ends_with(rtrim($content), self::CANON)) {
                $this->line("<fg=gray>OK   </> {$course->slug} — già normalizzato.");
                continue;
            }

            $start = $this->colophonStart($content);
            $new = ($start !== null ? $this->stripFrom($content, $start) : rtrim($content)) . self::CANON;
            $planned++;
            $rows[] = [$course->slug, $last->id, $new];

            $action = $start !== null ? '<fg=red>SOSTITUISCE colophon</>' : '<fg=green>AGGIUNGE</>';
            $this->line("<fg=cyan;options=bold>{$course->slug}</>  →  {$action}  (modulo: {$last->title})");
            $this->line('  <fg=red>PRIMA (coda):</> …' . $this->clean($this->tail($content)));
            $this->line('  <fg=green>DOPO  (coda):</> …' . $this->clean($this->tail($new)));
            $this->newLine();
        }

        if ($dry) {
            $this->warn("DRY-RUN: {$planned} corsi da aggiornare, nessuna scrittura.");
            $this->line('Per applicare: <options=bold>php artisan course:copyright</> (senza --dry-run).');
            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($rows, &$updated) {
            foreach ($rows as [$slug, $moduleId, $new]) {
                $m = Module::find($moduleId);
                if (!$m) {
                    continue;
                }
                $m->content = $new;
                $m->save();
                $updated++;
                Log::channel('audit')->info('[course:copyright] copyright normalizzato', [
                    'course_slug' => $slug,
                    'module_id' => $moduleId,
                ]);
            }
        });

        $this->info("Fatto: {$updated} corsi aggiornati. Dicitura unica applicata. Dettaglio in audit.log.");
        return self::SUCCESS;
    }

    /**
     * Offset di BYTE da cui inizia il colophon in coda: l'apertura del paragrafo <p>
     * che contiene il PRIMO marcatore di colophon (autore/tagline/copyright). Null se
     * nessun marcatore → non c'è colophon da rimuovere. Opera ai confini di tag ASCII
     * (<p): sicuro coi caratteri multibyte del testo.
     */
    private function colophonStart(string $content): ?int
    {
        $pos = null;
        foreach (self::MARKERS as $mk) {
            $p = strpos($content, $mk);
            if ($p !== false && ($pos === null || $p < $pos)) {
                $pos = $p;
            }
        }
        if ($pos === null) {
            return null;
        }
        // Ultima apertura di paragrafo <p( |>) a offset <= $pos (evita <pre>).
        if (!preg_match_all('/<p(?=[\s>])/i', $content, $mm, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = null;
        foreach ($mm[0] as $mo) {
            if ($mo[1] <= $pos) {
                $start = $mo[1];
            } else {
                break;
            }
        }

        return $start;
    }

    /** Taglia da $start alla fine, consumando un eventuale <hr> immediatamente prima. */
    private function stripFrom(string $content, int $start): string
    {
        $head = substr($content, 0, $start);
        $head = preg_replace('/\s*<hr\s*\/?>\s*$/i', '', $head);

        return rtrim($head);
    }

    /** Ultimi ~200 caratteri, per l'anteprima. */
    private function tail(string $s): string
    {
        return mb_substr($s, -200);
    }

    private function clean(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
