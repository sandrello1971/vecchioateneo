<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rimuove OGNI nome di azienda ("Noscite" e varianti) dal testo dei corsi, tramite
 * riformulazioni grammaticali mirate (non semplici cancellazioni: "Noscite" è spesso
 * dentro le frasi). L'unico nome che resta è l'autore, Stefano Andrello. Con --dry-run
 * mostra le sostituzioni e il residuo; senza, applica in transazione e logga.
 */
class ScrubCompanyName extends Command
{
    protected $signature = 'brand:scrub-company {--dry-run : Mostra le modifiche senza scrivere}';

    protected $description = 'Rimuove i nomi azienda (Noscite) dal testo dei corsi con riformulazioni grammaticali';

    /** Sostituzioni nel content dei moduli (old => new), grammaticali e mirate. */
    private const MODULE_MAP = [
        'trilogia formativa Noscite' => 'trilogia formativa',
        'laboratorio direzionale Noscite' => 'laboratorio direzionale',
        '<p>Noscite — In digitālī nova virtūs</p>' => '',
        '<p><strong>Noscite — Umanesimo digitale</strong></p>' => '',
        'il lavoro di Noscite:' => 'il nostro lavoro:',
        'offerta formativa Noscite' => 'offerta formativa',
        'contatta Noscite per pianificare' => 'contatta il formatore per pianificare',
        'Il percorso completo Noscite' => 'Il percorso completo',
        'Il vostro percorso Noscite' => 'Il vostro percorso',
        'la formazione Noscite da' => 'la formazione da',
        'la formazione Noscite è' => 'la formazione è',
        'gli strumenti che Noscite stessa usa nei propri progetti' => 'gli strumenti usati nella pratica professionale',
        'è il percorso Noscite pensato' => 'è il percorso pensato',
        'un formatore Noscite,' => 'un formatore,',
        '; Noscite lo lancia' => '; il formatore lo lancia',
        'Il percorso Noscite continua' => 'Il percorso continua',
        'della trilogia Noscite.' => 'della trilogia.',
        'portale Atheneum Noscite,' => 'portale Atheneum,',
        'raccomandata da Noscite per' => 'raccomandata per',
        'La policy Noscite si articola' => 'La policy si articola',
        'Il percorso Noscite prosegue' => 'Il percorso prosegue',
        'tutti i corsi Noscite vi insegnano' => 'tutti i corsi vi insegnano',
    ];

    /** Sostituzioni nei campi descrittivi del corso. */
    private const COURSE_DESC_MAP = [
        'formativo di base di Noscite,' => 'formativo di base,',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // --- Moduli ---
        $modules = Module::where('content', 'like', '%Noscite%')->get();
        $this->info(($dry ? '[DRY-RUN] ' : '') . "Moduli con 'Noscite': {$modules->count()}.");

        $moduleRows = [];
        foreach ($modules as $m) {
            $new = $m->content;
            foreach (self::MODULE_MAP as $old => $rep) {
                $new = str_replace($old, $rep, $new);
            }
            $before = substr_count($m->content, 'Noscite');
            $after = substr_count($new, 'Noscite');
            $moduleRows[] = [$m->id, $new, $m->course?->slug, $before, $after];
            $flag = $after > 0 ? '<fg=red>RESIDUO ' . $after . '</>' : '<fg=green>ok</>';
            $this->line("  {$m->course?->slug}  ·  Noscite {$before} → {$after}  {$flag}");
        }

        // --- Campi corso ---
        $courses = Course::where('description', 'like', '%di Noscite%')->get();
        $courseRows = [];
        foreach ($courses as $c) {
            $new = $c->description;
            foreach (self::COURSE_DESC_MAP as $old => $rep) {
                $new = str_replace($old, $rep, $new);
            }
            $courseRows[] = [$c->id, $new, $c->slug, substr_count((string) $c->description, 'Noscite'), substr_count((string) $new, 'Noscite')];
            $this->line("  [corso.description] {$c->slug}  ·  Noscite " . substr_count((string) $c->description, 'Noscite') . ' → ' . substr_count((string) $new, 'Noscite'));
        }

        $residual = array_sum(array_map(fn ($r) => $r[4], $moduleRows)) + array_sum(array_map(fn ($r) => $r[4], $courseRows));
        $this->newLine();

        if ($dry) {
            if ($residual > 0) {
                $this->error("DRY-RUN: restano {$residual} 'Noscite' non coperti dalla mappa — vanno aggiunte regole prima di applicare.");
            } else {
                $this->info("DRY-RUN ok: dopo l'applicazione NON resta alcun 'Noscite'. Nessuna scrittura effettuata.");
            }
            return self::SUCCESS;
        }

        if ($residual > 0) {
            $this->error("Interrotto: restano {$residual} 'Noscite' non coperti. Nessuna modifica applicata.");
            return self::FAILURE;
        }

        $n = 0;
        DB::transaction(function () use ($moduleRows, $courseRows, &$n) {
            foreach ($moduleRows as [$id, $new]) {
                $m = Module::find($id);
                if ($m && $m->content !== $new) {
                    $m->content = $new;
                    $m->save();
                    $n++;
                    Log::channel('audit')->info('[brand:scrub-company] nome azienda rimosso dal modulo', ['module_id' => $id]);
                }
            }
            foreach ($courseRows as [$id, $new]) {
                $c = Course::find($id);
                if ($c && $c->description !== $new) {
                    $c->description = $new;
                    $c->save();
                    Log::channel('audit')->info('[brand:scrub-company] nome azienda rimosso dalla description del corso', ['course_id' => $id]);
                }
            }
        });

        $this->info("Fatto: {$n} moduli aggiornati. Nessun nome azienda residuo. Dettaglio in audit.log.");
        return self::SUCCESS;
    }
}
