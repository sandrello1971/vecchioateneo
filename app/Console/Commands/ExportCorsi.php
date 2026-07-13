<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Esporta i corsi (moduli + contenuto HTML convertito in testo) in file .md,
 * uno per corso, in una cartella. Serve per revisione offline.
 *
 * Uso:
 *   php artisan corsi:export --out=/tmp/export_corsi
 *   php artisan corsi:export --out=/tmp/export_corsi --slug=consilium,ai-governance-executive
 *   php artisan corsi:export --out=/tmp/export_corsi --all
 *
 * Senza --slug e senza --all, esporta un set predefinito di corsi business.
 */
class ExportCorsi extends Command
{
    protected $signature = 'corsi:export
                            {--out=/tmp/export_corsi : Cartella di destinazione}
                            {--slug= : Slug specifici separati da virgola}
                            {--all : Esporta tutti i corsi}';

    protected $description = 'Esporta i corsi in file markdown (moduli + contenuto) per revisione.';

    private const DEFAULT_SLUGS = [
        'primus','consilium','ai-governance-executive',
        'initium-fondamenta-ai-operativa','fondamenta-ai-operativa',
        'structura','ai-literacy-essential','capire-lai-act',
    ];

    public function handle(): int
    {
        $out = rtrim((string) $this->option('out'), '/');
        if (!is_dir($out)) {
            if (!@mkdir($out, 0775, true) && !is_dir($out)) {
                $this->error("Impossibile creare la cartella: $out");
                return 1;
            }
        }

        // determina gli slug
        if ($this->option('all')) {
            $slugs = Course::orderBy('name')->pluck('slug')->all();
        } elseif ($this->option('slug')) {
            $slugs = array_map('trim', explode(',', (string) $this->option('slug')));
        } else {
            $slugs = self::DEFAULT_SLUGS;
        }

        $this->info('Esporto ' . count($slugs) . ' corsi in ' . $out);
        $done = 0;

        foreach ($slugs as $slug) {
            $course = Course::where('slug', $slug)->first();
            if (!$course) { $this->warn("  $slug non trovato, salto"); continue; }

            $md = $this->courseToMarkdown($course);
            $file = $out . '/' . $slug . '.md';
            file_put_contents($file, $md);
            $this->line("  scritto: " . basename($file) . '  (' . mb_strlen($md) . ' char)');
            $done++;
        }

        $this->newLine();
        $this->info("Fatto. File creati: $done in $out");
        $this->line('Scaricali con:  tar czf /tmp/export_corsi.tgz -C ' . dirname($out) . ' ' . basename($out));
        return 0;
    }

    private function courseToMarkdown(Course $course): string
    {
        $attivo = $course->is_active ? 'attivo' : 'ARCHIVIATO';
        $cert = $course->certification_name ?? '-';
        $out  = "# {$course->name}\n\n";
        $out .= "- slug: `{$course->slug}`\n";
        $out .= "- stato: {$attivo}\n";
        $out .= "- durata: " . ($course->duration_hours ?? '?') . " ore\n";
        $out .= "- certification_name: {$cert}\n";
        $out .= "- descrizione: " . trim((string) $course->short_description) . "\n";
        $out .= "- aggiornato: {$course->updated_at}\n\n";
        $out .= "---\n\n";

        $moduli = DB::select(
            "SELECT title, content, sort_order,
                    (SELECT COUNT(*) FROM materials mm WHERE mm.module_id = mo.id AND mm.file_type='canvas') AS canvas
             FROM modules mo WHERE mo.course_id = ? ORDER BY mo.sort_order",
            [$course->id]
        );

        foreach ($moduli as $m) {
            $txt = $this->htmlToText((string) $m->content);
            $out .= "## {$m->title}\n";
            $out .= "_(canvas agganciati: {$m->canvas} · " . mb_strlen($txt) . " char)_\n\n";
            $out .= $txt . "\n\n";
        }

        return $out;
    }

    /** Converte l'HTML del modulo in testo leggibile, preservando struttura minima. */
    private function htmlToText(string $html): string
    {
        // titoli/paragrafi -> newline
        $html = preg_replace('/<\/(h[1-6]|p|div|li|tr)>/i', "\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<li[^>]*>/i', '- ', $html);
        $txt  = strip_tags($html);
        $txt  = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // normalizza spazi e newline multipli
        $txt  = preg_replace("/[ \t]+/", ' ', $txt);
        $txt  = preg_replace("/\n{3,}/", "\n\n", $txt);
        return trim($txt);
    }
}
