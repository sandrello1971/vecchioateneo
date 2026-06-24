<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseSource;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Services\CourseDocumentParser;
use App\Services\CourseSourceExtractor;
use App\Services\Freshness\CoordinatedMatchService;
use App\Services\InstructorManualService;
use App\Services\InstructorManualSplitterService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Manuale FORMATORE in Markdown. CRITICO: il formatore è la fonte strutturata
 * della Freshness → il .md deve generare course_sources ESATTAMENTE come il docx
 * (extractFromMarkdown riusa mapAst). Upload + course_sources + sezioni; docx
 * invariato. I test che usano pandoc si auto-skippano se assente.
 */
class FormatoreMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private function requirePandoc(): void
    {
        try {
            app(CourseSourceExtractor::class)->assertPandocAvailable();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('pandoc non disponibile: ' . $e->getMessage());
        }
    }

    private function mdFixture(): string
    {
        return base_path('tests/Fixtures/p25/mini-course.md');
    }

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'INITIUM', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function service(): InstructorManualService
    {
        $rag = Mockery::mock(RagService::class);
        $rag->shouldReceive('indexDocument')->andReturnNull();

        return new InstructorManualService(
            $rag,
            app(InstructorManualSplitterService::class),
            app(CourseSourceExtractor::class),
            app(CoordinatedMatchService::class),
            app(CourseDocumentParser::class)
        );
    }

    // ============================================================
    // extractFromMarkdown — blocchi della stessa forma del docx
    // ============================================================

    public function test_extract_from_markdown_produce_blocchi_strutturati(): void
    {
        $this->requirePandoc();
        $result = app(CourseSourceExtractor::class)->extractFromMarkdown($this->mdFixture());

        $blocks = $result['blocks'];
        $this->assertNotEmpty($blocks, 'Il .md deve produrre blocchi non vuoti.');

        // Stessa FORMA del docx: ogni blocco ha id, type, text.
        foreach ($blocks as $b) {
            $this->assertArrayHasKey('id', $b);
            $this->assertArrayHasKey('type', $b);
            $this->assertArrayHasKey('text', $b);
        }
        // I tipi appartengono allo stesso vocabolario del docx (PART/H1/H2/P/...).
        $types = array_values(array_unique(array_map(fn ($b) => $b['type'], $blocks)));
        $this->assertNotEmpty(array_intersect($types, ['PART', 'H1', 'H2', 'H3', 'P', 'BOX', 'NUM', 'BUL']));

        // Il fatto databile è preservato nel testo (input per il claim extractor).
        $allText = implode("\n", array_map(fn ($b) => $b['text'], $blocks));
        $this->assertStringContainsString('2024', $allText);
        $this->assertStringContainsString('ISTAT 2025', $allText);
    }

    public function test_extract_from_markdown_ids_deterministici(): void
    {
        $this->requirePandoc();
        $extractor = app(CourseSourceExtractor::class);
        $a = $extractor->extractFromMarkdown($this->mdFixture());
        $b = $extractor->extractFromMarkdown($this->mdFixture());

        $this->assertSame(
            array_map(fn ($x) => $x['id'], $a['blocks']),
            array_map(fn ($x) => $x['id'], $b['blocks']),
            'Gli id devono essere deterministici (come il docx).'
        );
    }

    // ============================================================
    // Upload formatore .md: content_html + course_sources + sezioni
    // ============================================================

    public function test_upload_formatore_md_genera_html_e_course_sources(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $file = new UploadedFile($this->mdFixture(), 'manuale_formatore.md', null, null, true);

        $material = $this->service()->uploadAndImport($file, $course, 'Manuale Formatore', null, null, false);

        // 1) HTML di consultazione generato (pandoc gfm→html5).
        $this->assertNotEmpty($material->content_html);
        $this->assertStringContainsString('<h1', $material->content_html);
        $this->assertSame('md', $material->file_type);
        $this->assertTrue($material->is_instructor_only);

        // 2) CRITICO: course_sources generato (la Freshness ha la sua fonte).
        $source = CourseSource::where('course_id', $course->id)->first();
        $this->assertNotNull($source, 'Il formatore .md DEVE generare course_sources.');
        $this->assertSame('1.0', $source->version);
        $this->assertNotEmpty($source->blocks, 'I blocchi del sorgente non devono essere vuoti.');

        // 3) Sezioni del manuale (splitter su content_html, source-agnostic).
        $this->assertGreaterThan(0, InstructorManualSection::where('material_id', $material->id)->count());
    }

    // ============================================================
    // Validazione controller: .md accettato
    // ============================================================

    public function test_controller_accetta_md(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);

        $file = new UploadedFile($this->mdFixture(), 'manuale.md', null, null, true);

        $this->post(route('admin.courses.instructor-materials.store', $course), [
            'title' => 'Manuale Formatore MD',
            'docx' => $file,
        ])->assertRedirect(route('admin.courses.edit', $course));

        $this->assertNotNull(Material::where('course_id', $course->id)->where('is_instructor_only', true)->first());
        $this->assertNotNull(CourseSource::where('course_id', $course->id)->first());
    }

    public function test_controller_rifiuta_estensione_non_valida(): void
    {
        $course = $this->makeCourse();
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);
        $file = UploadedFile::fake()->createWithContent('manuale.exe', 'x');

        $this->post(route('admin.courses.instructor-materials.store', $course), [
            'title' => 'X', 'docx' => $file,
        ])->assertSessionHasErrors('docx');
    }
}
