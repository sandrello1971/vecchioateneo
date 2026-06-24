<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseSource;
use App\Services\CourseSourceExtractor;
use App\Services\CourseSourcePdfBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * P25.1 — Recupero one-time di un corso esistente: .docx → sorgente strutturato.
 *
 * Aggancio SEMPRE per `course_id` INTERNO (uuid PK), MAI per nome/slug: l'argomento
 * è risolto con Course::find() sulla primary key. Un nome o uno slug non corrispondono
 * alla PK e vengono rifiutati.
 *
 * NON tocca corsi pubblicati: scrive solo una riga in `course_sources` (e, con --pdf,
 * un PDF round-trip in storage privato). Non modifica `courses` né `modules`.
 */
class RecoverCourseSource extends Command
{
    // NB: l'opzione versione si chiama --source-version (non --version): `--version`/-V
    // è un'opzione globale riservata da Symfony Console e non è ridefinibile.
    protected $signature = 'course:recover-source
        {course_id : ID INTERNO del corso (uuid PK) — MAI nome o slug}
        {docx : Path al file .docx sorgente}
        {--source-version=1.0 : Versione del sorgente strutturato (stringa, es. "1.0")}
        {--pdf : Rigenera anche il PDF round-trip dal sorgente}';

    protected $description = 'P25.1 — Recupera un .docx in sorgente strutturato (course_sources), agganciato per course_id interno.';

    public function handle(CourseSourceExtractor $extractor, CourseSourcePdfBuilder $pdfBuilder): int
    {
        $courseId = (string) $this->argument('course_id');
        $docxPath = (string) $this->argument('docx');
        $version = (string) $this->option('source-version');

        // 1) Aggancio per ID INTERNO. Rifiuta a monte qualsiasi cosa non sia un uuid
        // (nome/slug): senza questa guard un valore non-uuid farebbe crashare la query
        // sulla PK uuid invece di dare un errore chiaro.
        if (!Str::isUuid($courseId)) {
            $this->error("course_id non valido: \"{$courseId}\" non è un uuid.");
            $this->line('Passa l\'ID interno del corso (uuid PK), non il nome né lo slug.');
            return self::FAILURE;
        }

        $course = Course::find($courseId);
        if (!$course) {
            $this->error("course_id interno non trovato: {$courseId}");
            $this->line('Passa l\'ID interno del corso (uuid PK), non il nome né lo slug.');
            return self::FAILURE;
        }

        if (!is_file($docxPath)) {
            $this->error("File .docx non trovato: {$docxPath}");
            return self::FAILURE;
        }

        // 2) Immutabilità: niente sovrascrittura di una versione esistente.
        if (CourseSource::where('course_id', $course->id)->where('version', $version)->exists()) {
            $this->error("Esiste già un sorgente v{$version} per il corso {$course->id}. Usa --version con un valore diverso.");
            return self::FAILURE;
        }

        // 3) Estrazione .docx → blocchi (guard su pandoc dentro l'estrattore).
        try {
            $result = $extractor->extractFromDocx($docxPath);
        } catch (\Throwable $e) {
            $this->error('Estrazione fallita: ' . $e->getMessage());
            return self::FAILURE;
        }

        $blocks = $result['blocks'];
        foreach ($result['warnings'] as $w) {
            $this->warn($w);
        }
        if (empty($blocks)) {
            $this->error('Nessun blocco estratto dal .docx: niente da salvare.');
            return self::FAILURE;
        }

        // 4) Persistenza: SOLO course_sources. Nessuna scrittura su courses/modules.
        $source = CourseSource::create([
            'course_id' => $course->id,
            'version' => $version,
            'blocks' => $blocks,
        ]);

        $this->info("Sorgente creato: course_sources {$source->id}");
        $this->line("  corso:    \"{$course->name}\" (id {$course->id})");
        $this->line("  versione: {$version}");
        $this->line('  blocchi:  ' . count($blocks) . ' · frontmatter escluso: ' . count($result['frontmatter']) . ' voci');

        // 5) --pdf opzionale: round-trip in storage PRIVATO (mai pubblico).
        if ($this->option('pdf')) {
            $bytes = $pdfBuilder->build($blocks, ['title' => "{$course->name} — sorgente v{$version}"]);
            $path = "course-sources/{$course->id}/v{$version}.pdf";
            Storage::disk('local')->put($path, $bytes);
            $this->info('  PDF round-trip: ' . Storage::disk('local')->path($path)
                . " ({$pdfBuilder->lastPageCount} pagine, {$pdfBuilder->lastRenderedBlocks} blocchi)");
        }

        return self::SUCCESS;
    }
}
