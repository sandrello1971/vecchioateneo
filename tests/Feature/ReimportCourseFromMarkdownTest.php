<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Services\CourseDocumentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * course:reimport-from-markdown — clean slate dei moduli di un corso esistente dal
 * .md. Dry-run di default; --write esegue. Granularità robusta (auto split-level).
 * Canvas preservati (module_id→NULL, mai cancellati). Solo il corso per slug.
 */
class ReimportCourseFromMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private function parser(): CourseDocumentParser
    {
        return (new ReflectionClass(CourseDocumentParser::class))->newInstanceWithoutConstructor();
    }

    private function makeCourse(string $slug): Course
    {
        return Course::create(['name' => 'Corso ' . $slug, 'slug' => $slug, 'is_active' => true, 'sort_order' => 1]);
    }

    private function writeMd(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'reimp_') . '.md';
        file_put_contents($path, $content);

        return $path;
    }

    // ============================================================
    // Rilevamento split-level (deterministico)
    // ============================================================

    public function test_split_level_uno_quando_molti_h1(): void
    {
        $html = $this->parser()->normalizeHeadings($this->parser()->convertManualToHtml(
            $this->writeMd("# Cap 1\n\nprosa.\n\n# Cap 2\n\nprosa.\n\n# Cap 3\n\nprosa.")
        ));
        $this->assertSame(1, $this->parser()->suggestSplitLevel($html));
    }

    public function test_split_level_due_quando_un_h1_e_molti_h2(): void
    {
        $html = $this->parser()->normalizeHeadings($this->parser()->convertManualToHtml(
            $this->writeMd("# Titolo manuale\n\nintro.\n\n## Cap 1\n\nprosa.\n\n## Cap 2\n\nprosa.\n\n## Cap 3\n\nprosa.")
        ));
        $this->assertSame(2, $this->parser()->suggestSplitLevel($html));
    }

    // ============================================================
    // Dry-run: nessuna scrittura
    // ============================================================

    public function test_dry_run_non_scrive_nulla(): void
    {
        $course = $this->makeCourse('corso-x');
        $old = Module::create(['course_id' => $course->id, 'title' => 'Vecchio', 'content' => '<p>old</p>', 'sort_order' => 0, 'is_active' => true]);
        $md = $this->writeMd("# Cap 1\n\nNuova prosa uno.\n\n# Cap 2\n\nNuova prosa due.");

        $this->artisan('course:reimport-from-markdown', ['course_slug' => 'corso-x', 'md_path' => $md])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        // Nessuna modifica: il modulo vecchio c'è ancora, niente nuovi.
        $this->assertSame(1, $course->modules()->count());
        $this->assertNotNull(Module::find($old->id));
    }

    // ============================================================
    // --write: clean slate + canvas preservati
    // ============================================================

    public function test_write_clean_slate_preserva_canvas(): void
    {
        $course = $this->makeCourse('corso-y');
        $oldA = Module::create(['course_id' => $course->id, 'title' => 'Vecchio A', 'content' => '<p>a</p>', 'sort_order' => 0, 'is_active' => true]);
        $oldB = Module::create(['course_id' => $course->id, 'title' => 'Vecchio B', 'content' => '<p>b</p>', 'sort_order' => 1, 'is_active' => true]);
        $canvas = Material::create([
            'module_id' => $oldA->id, 'title' => 'Canvas progetto', 'file_type' => 'canvas',
            'file_path' => 'materials/c.html', 'is_downloadable' => false, 'is_instructor_only' => false, 'sort_order' => 0,
        ]);

        $md = $this->writeMd("# PARTE PRIMA — INTRO\n\n# Capitolo 1 — Uno\n\nProsa uno.\n\n# Capitolo 2 — Due\n\nProsa due.");

        $this->artisan('course:reimport-from-markdown', ['course_slug' => 'corso-y', 'md_path' => $md, '--write' => true])
            ->expectsOutputToContain('clean slate eseguito')
            ->assertSuccessful();

        // Vecchi moduli rimossi.
        $this->assertNull(Module::find($oldA->id));
        $this->assertNull(Module::find($oldB->id));

        // Nuovi moduli creati dal .md (1 divisore + 2 contenuto), ordinati.
        $new = $course->modules()->orderBy('sort_order')->get();
        $this->assertSame(3, $new->count());
        $this->assertSame('PARTE PRIMA — INTRO', $new[0]->title);
        $this->assertSame('Capitolo 1 — Uno', $new[1]->title);

        // Canvas PRESERVATO (non cancellato dal CASCADE): staccato dal modulo, legato al corso.
        $canvas->refresh();
        $this->assertNotNull($canvas->id, 'Il canvas non deve essere cancellato.');
        $this->assertNull($canvas->module_id, 'Il canvas è staccato dal modulo (module_id NULL).');
        $this->assertSame($course->id, $canvas->course_id, 'Il canvas resta legato al corso.');
    }

    public function test_idempotenza_non_duplica(): void
    {
        $course = $this->makeCourse('corso-z');
        Module::create(['course_id' => $course->id, 'title' => 'Vecchio', 'content' => '<p>x</p>', 'sort_order' => 0, 'is_active' => true]);
        $md = $this->writeMd("# Cap 1\n\nProsa uno.\n\n# Cap 2\n\nProsa due.");

        $this->artisan('course:reimport-from-markdown', ['course_slug' => 'corso-z', 'md_path' => $md, '--write' => true])->assertSuccessful();
        $afterFirst = $course->modules()->count();

        $this->artisan('course:reimport-from-markdown', ['course_slug' => 'corso-z', 'md_path' => $md, '--write' => true])->assertSuccessful();
        $afterSecond = $course->modules()->count();

        $this->assertSame(2, $afterFirst);
        $this->assertSame($afterFirst, $afterSecond, 'Ri-eseguire non duplica (clean slate).');
    }

    // ============================================================
    // Override + scoping
    // ============================================================

    public function test_override_split_level(): void
    {
        $course = $this->makeCourse('corso-ov');
        Module::create(['course_id' => $course->id, 'title' => 'V', 'content' => '<p>v</p>', 'sort_order' => 0, 'is_active' => true]);
        // .md con 1 # + molti ## (auto=2) ma forziamo 1 → un solo modulo gigante.
        $md = $this->writeMd("# Manuale\n\n## A\n\nprosa.\n\n## B\n\nprosa.");

        $this->artisan('course:reimport-from-markdown', ['course_slug' => 'corso-ov', 'md_path' => $md, '--split-level' => '1', '--write' => true])
            ->assertSuccessful();

        $this->assertSame(1, $course->modules()->count(), 'Con --split-level=1 il # è l\'unico modulo.');
    }

    public function test_corso_inesistente_fallisce(): void
    {
        $md = $this->writeMd("# Cap 1\n\nprosa.");
        $this->artisan('course:reimport-from-markdown', ['course_slug' => 'non-esiste', 'md_path' => $md])
            ->assertFailed();
    }

    public function test_solo_il_corso_indicato_altri_intatti(): void
    {
        $target = $this->makeCourse('target');
        Module::create(['course_id' => $target->id, 'title' => 'T', 'content' => '<p>t</p>', 'sort_order' => 0, 'is_active' => true]);
        $other = $this->makeCourse('altro');
        $otherMod = Module::create(['course_id' => $other->id, 'title' => 'Altro modulo', 'content' => '<p>o</p>', 'sort_order' => 0, 'is_active' => true]);

        $md = $this->writeMd("# Cap 1\n\nprosa uno.\n\n# Cap 2\n\nprosa due.");
        $this->artisan('course:reimport-from-markdown', ['course_slug' => 'target', 'md_path' => $md, '--write' => true])->assertSuccessful();

        // L'altro corso non è toccato.
        $this->assertNotNull(Module::find($otherMod->id));
        $this->assertSame(1, $other->modules()->count());
    }
}
