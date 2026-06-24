<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSource;
use App\Services\CourseSourceExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P25.1 — Integrazione pandoc (estrazione end-to-end dalla fixture .docx) e test del
 * comando `course:recover-source`. I test che richiedono pandoc si auto-skippano se il
 * binario non è presente (portabilità CI); il mapper puro è già coperto a parte.
 */
class CourseSourceRecoverTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/Fixtures/p25/mini-course.docx');
    }

    private function requirePandoc(): void
    {
        try {
            app(CourseSourceExtractor::class)->assertPandocAvailable();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('pandoc non disponibile: ' . $e->getMessage());
        }
    }

    private function makeCourse(array $attrs = []): Course
    {
        return Course::create(array_merge([
            'name' => 'INTERFERENZA',
            'slug' => 'consilium-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ], $attrs));
    }

    // ---- Integrazione pandoc: copre end-to-end anche i tipi rari (BOX/EX/ESE/NUM/BUL) ----

    public function test_integrazione_pandoc_estrae_tutti_i_tipi(): void
    {
        $this->requirePandoc();

        $res = app(CourseSourceExtractor::class)->extractFromDocx($this->fixture());

        $counts = [];
        foreach ($res['blocks'] as $b) {
            $counts[$b['type']] = ($counts[$b['type']] ?? 0) + 1;
        }

        $this->assertSame(
            ['PART' => 1, 'H1' => 1, 'H2' => 1, 'P' => 1, 'BUL' => 1, 'NUM' => 1, 'BOX' => 1, 'EX' => 1, 'ESE' => 1],
            $counts
        );
        $this->assertCount(9, $res['blocks']);
        $this->assertEmpty($res['warnings']);
        $this->assertEmpty($res['frontmatter']);
    }

    // ---- Comando ----

    public function test_comando_popola_solo_course_sources(): void
    {
        $this->requirePandoc();
        $course = $this->makeCourse();

        $this->artisan('course:recover-source', [
            'course_id' => $course->id,
            'docx' => $this->fixture(),
        ])->assertExitCode(0);

        $this->assertDatabaseHas('course_sources', [
            'course_id' => $course->id,
            'version' => '1.0',
        ]);

        $source = CourseSource::where('course_id', $course->id)->first();
        $this->assertNotNull($source);
        $this->assertCount(9, $source->blocks);

        // Non deve aver toccato corsi pubblicati: nessun modulo creato/modificato.
        $this->assertSame(0, $course->modules()->count());
    }

    public function test_comando_con_flag_pdf_genera_pdf_privato(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $this->artisan('course:recover-source', [
            'course_id' => $course->id,
            'docx' => $this->fixture(),
            '--pdf' => true,
        ])->assertExitCode(0);

        $path = "course-sources/{$course->id}/v1.0.pdf";
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($path));
    }

    public function test_comando_senza_pdf_non_scrive_pdf(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $this->artisan('course:recover-source', [
            'course_id' => $course->id,
            'docx' => $this->fixture(),
        ])->assertExitCode(0);

        Storage::disk('local')->assertMissing("course-sources/{$course->id}/v1.0.pdf");
    }

    // ---- Regola critica: aggancio per ID interno, MAI per nome/slug ----

    public function test_comando_rifiuta_slug_invece_di_id(): void
    {
        $course = $this->makeCourse(['slug' => 'consilium-rule']);

        // Passando lo SLUG al posto dell'ID interno → rifiutato, niente sorgente.
        $this->artisan('course:recover-source', [
            'course_id' => $course->slug,
            'docx' => $this->fixture(),
        ])->assertExitCode(1);

        $this->assertDatabaseCount('course_sources', 0);
    }

    public function test_comando_rifiuta_course_id_inesistente(): void
    {
        $this->artisan('course:recover-source', [
            'course_id' => '00000000-0000-0000-0000-000000000000',
            'docx' => $this->fixture(),
        ])->assertExitCode(1);

        $this->assertDatabaseCount('course_sources', 0);
    }

    public function test_comando_rifiuta_versione_duplicata(): void
    {
        $this->requirePandoc();
        $course = $this->makeCourse();

        $this->artisan('course:recover-source', [
            'course_id' => $course->id,
            'docx' => $this->fixture(),
        ])->assertExitCode(0);

        // Stessa versione due volte → immutabilità, secondo tentativo rifiutato.
        $this->artisan('course:recover-source', [
            'course_id' => $course->id,
            'docx' => $this->fixture(),
        ])->assertExitCode(1);

        $this->assertDatabaseCount('course_sources', 1);
    }
}
