<?php

namespace Tests\Feature;

use App\Services\CourseDocumentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * Ingestion Markdown — il .md è formato di primo livello accanto al docx.
 * convertMarkdownToHtml (pandoc gfm) + dispatcher convertManualToHtml per
 * estensione (docx INVARIATO) + accept/validazione ampliati. Il resto della
 * pipeline (split, frontmatter, exam-prep) si riusa identico. Additivo.
 */
class IngestionMarkdownTest extends TestCase
{
    use RefreshDatabase;

    /** Parser puro (i metodi di conversione/parse non toccano DB/LLM). */
    private function parser(): CourseDocumentParser
    {
        return (new ReflectionClass(CourseDocumentParser::class))->newInstanceWithoutConstructor();
    }

    private function writeMd(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'md_') . '.md';
        file_put_contents($path, $content);

        return $path;
    }

    private function asAdmin(): self
    {
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);

        return $this;
    }

    // ============================================================
    // convertMarkdownToHtml (pandoc gfm)
    // ============================================================

    public function test_markdown_convertito_in_html_strutturato(): void
    {
        $md = <<<'MD'
# Il mondo che non aspetta

L'AI è uno strumento *potente* ma con limiti.

## I limiti

- Allucinazioni
- Costo computazionale
- Bias
MD;
        $html = $this->parser()->convertMarkdownToHtml($this->writeMd($md));

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Il mondo che non aspetta', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('<em>potente</em>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Bias</li>', $html);
    }

    public function test_accenti_utf8_preservati(): void
    {
        $html = $this->parser()->convertMarkdownToHtml($this->writeMd("# Però è così — qualità\n\ntesto"));
        $this->assertStringContainsString('Però è così — qualità', $html);
    }

    // ============================================================
    // dispatcher convertManualToHtml — instrada per estensione
    // ============================================================

    public function test_dispatcher_instrada_md_al_ramo_markdown(): void
    {
        $parser = Mockery::mock(CourseDocumentParser::class)->makePartial();
        $parser->shouldReceive('convertMarkdownToHtml')->once()->with('/x/manual.md')->andReturn('<h1>md</h1>');
        $parser->shouldNotReceive('convertDocxToHtml');

        $this->assertSame('<h1>md</h1>', $parser->convertManualToHtml('/x/manual.md'));
    }

    public function test_dispatcher_instrada_docx_al_ramo_docx_invariato(): void
    {
        $parser = Mockery::mock(CourseDocumentParser::class)->makePartial();
        $parser->shouldReceive('convertDocxToHtml')->once()->with('/x/manual.docx')->andReturn('<h1>docx</h1>');
        $parser->shouldNotReceive('convertMarkdownToHtml');

        $this->assertSame('<h1>docx</h1>', $parser->convertManualToHtml('/x/manual.docx'));
    }

    // ============================================================
    // Pipeline end-to-end su .md (riuso identico a valle)
    // ============================================================

    public function test_pipeline_md_split_moduli_frontmatter_examprep(): void
    {
        $md = <<<'MD'
Corso SEGNALE — un percorso pratico per usare l'AI in azienda ogni giorno.

# Il mondo che non aspetta

Introduzione al modulo uno.

## I limiti

Allucinazioni, costo, bias.

# La tua azienda nell'AI

Dove l'AI genera valore.

## Preparazione all'esame

Ripasso prima del quiz.
MD;
        $parser = $this->parser();
        $html = $parser->convertManualToHtml($this->writeMd($md));
        $normalized = $parser->normalizeHeadings($html);
        $modules = $parser->splitIntoModules($normalized);

        $titles = array_map(fn ($m) => trim($m['title']), $modules);
        $this->assertContains('Il mondo che non aspetta', $titles);
        $this->assertContains('La tua azienda nell\'AI', $titles);
        $this->assertCount(2, $modules, 'Ogni # = un modulo.');

        // Front-matter (prosa prima del primo #) usato per i metadati corso.
        $frontmatter = $parser->extractFrontmatter($normalized);
        $this->assertStringContainsString('Corso SEGNALE', strip_tags($frontmatter));

        // Exam-prep riconosciuto e separato dall'ultimo modulo.
        $separated = $parser->separateExamPrep($modules);
        $this->assertNotNull($separated['exam_prep_html']);
        $this->assertStringContainsString('Ripasso prima del quiz', strip_tags($separated['exam_prep_html']));
    }

    // ============================================================
    // Validazione upload: .md accettato, estensione invalida respinta
    // ============================================================

    public function test_upload_md_accettato(): void
    {
        Bus::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('manuale.md', "# Modulo 1\n\nTesto del modulo.");

        $this->asAdmin()
            ->post(route('admin.courses.ingest.parse'), ['manual_file' => $file])
            ->assertRedirect(); // → processing, nessun errore di validazione

        Bus::assertDispatched(\App\Jobs\ParseCourseDocumentsJob::class);
    }

    public function test_upload_estensione_invalida_respinta(): void
    {
        Bus::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('manuale.exe', 'binario');

        $this->asAdmin()
            ->post(route('admin.courses.ingest.parse'), ['manual_file' => $file])
            ->assertSessionHasErrors('manual_file');

        Bus::assertNotDispatched(\App\Jobs\ParseCourseDocumentsJob::class);
    }

    // ============================================================
    // Granularità: divisore (# PARTE, no corpo) vs contenuto (# Capitolo)
    // ============================================================

    public function test_md_distingue_parte_divisore_da_capitolo_contenuto(): void
    {
        $md = <<<'MD'
# PARTE PRIMA — CAPIRE L'AI

# Capitolo 1 — Come pensa un'AI

Prosa del capitolo uno con contenuto reale.

## 1.1 Token

Spiegazione dei token.

# PARTE SECONDA — PROMPTING

# Capitolo 2 — Il framework COFT

Prosa del capitolo due.
MD;
        $parser = $this->parser();
        $modules = $parser->splitIntoModules($parser->normalizeHeadings($parser->convertManualToHtml($this->writeMd($md))));

        $byTitle = [];
        foreach ($modules as $m) {
            $byTitle[$m['title']] = $m['is_divider'];
        }

        $this->assertTrue($byTitle['PARTE PRIMA — CAPIRE L\'AI'], 'PARTE senza corpo = divisore.');
        $this->assertTrue($byTitle['PARTE SECONDA — PROMPTING'], 'PARTE senza corpo = divisore.');
        $this->assertFalse($byTitle['Capitolo 1 — Come pensa un\'AI'], 'Capitolo con prosa = contenuto.');
        $this->assertFalse($byTitle['Capitolo 2 — Il framework COFT'], 'Capitolo con prosa = contenuto.');

        // I divisori NON sono scartati: restano moduli nell'ordine del .md.
        $this->assertCount(4, $modules);
    }

    public function test_no_corpo_e_il_criterio_primario_titolo_ambiguo_con_corpo_non_e_divisore(): void
    {
        // Titolo che combacia col pattern di sezione ("MODULO 1") MA con corpo reale
        // → il criterio no-corpo lo salva: NON è un divisore.
        $parser = $this->parser();
        $this->assertTrue($parser->looksLikeDividerTitle('MODULO 1 — Introduzione'), 'Il titolo matcha il pattern.');
        $this->assertFalse(
            $parser->isDividerModule('MODULO 1 — Introduzione', '<h1>MODULO 1 — Introduzione</h1><p>Contenuto reale del modulo.</p>'),
            'Ha corpo → contenuto, non divisore (no-corpo è primario).'
        );

        // Stesso titolo ma senza corpo → divisore.
        $this->assertTrue(
            $parser->isDividerModule('MODULO 1 — Introduzione', '<h1>MODULO 1 — Introduzione</h1>')
        );
    }

    public function test_docx_parte_con_capitoli_h2_non_e_divisore(): void
    {
        // Simula il ramo docx: PARTE è h1 e contiene i Capitoli come h2 (corpo pieno)
        // → NON divisore. Garantisce che il docx non cambi comportamento.
        $parser = $this->parser();
        $docxLike = '<h1>PARTE PRIMA — CAPIRE L\'AI</h1><h2>Capitolo 1</h2><p>Prosa del capitolo.</p>';
        $this->assertFalse($parser->isDividerModule('PARTE PRIMA — CAPIRE L\'AI', $docxLike));
    }

    // ============================================================
    // BUG FIX: <p><strong> NON è un confine di modulo nel Markdown
    // ============================================================

    public function test_markdown_non_promuove_strong_a_modulo(): void
    {
        // 2 veri # + 3 "**Sezione N**" (grassetto). I confini sono SOLO i 2 #.
        $md = <<<'MD'
# Modulo 1 — Workshop

Introduzione al workshop.

**Sezione 1 — Obiettivo**

Testo della sezione uno.

**Sezione 2 — Dati**

Testo della sezione due.

**Sezione 3 — Output**

Testo della sezione tre.

# Modulo 2 — Glossario

Voci del glossario.
MD;
        $parser = $this->parser();
        $norm = $parser->normalizeMarkdownHtml($parser->convertManualToHtml($this->writeMd($md)));

        // Conta SOLO i veri <h1> (2), non i 3 <p><strong>.
        $this->assertSame(2, preg_match_all('/<h1[^>]*>/i', $norm));

        $modules = $parser->splitIntoModules($norm);
        $this->assertCount(2, $modules, 'Solo 2 moduli (i #), le Sezioni NON sono moduli.');
        $this->assertSame('Modulo 1 — Workshop', $modules[0]['title']);

        // Le 3 Sezioni restano DENTRO il Modulo 1 come <p><strong>, non come moduli.
        $this->assertSame(3, substr_count($modules[0]['content_html'], 'Sezione '));
        $this->assertStringContainsString('<strong>Sezione 1 — Obiettivo</strong>', $modules[0]['content_html']);
    }

    public function test_suggest_split_level_ignora_gli_strong(): void
    {
        // "SEZIONE N" in grassetto NON deve gonfiare il conteggio h1 → resta level 1 su 6 #.
        $md = "# A\n\nx.\n\n**Sezione 1**\n\ny.\n\n# B\n\nz.\n\n**Sezione 2**\n\nw.";
        $parser = $this->parser();
        $norm = $parser->normalizeMarkdownHtml($parser->convertManualToHtml($this->writeMd($md)));
        $this->assertSame(2, preg_match_all('/<h1[^>]*>/i', $norm));
        $this->assertSame(1, $parser->suggestSplitLevel($norm));
    }

    public function test_docx_normalize_ANCORA_promuove_strong_invariato(): void
    {
        // Divergenza voluta: il ramo DOCX continua a promuovere i bold-heading
        // (Word li usa come heading). normalizeHeadings invariato.
        $parser = $this->parser();
        $docx = '<p><strong>SEZIONE 1 — Obiettivo</strong></p><p>testo</p>';
        $this->assertStringContainsString('<h1>', $parser->normalizeHeadings($docx));
        // Mentre il ramo Markdown lo lascia paragrafo:
        $this->assertStringNotContainsString('<h1>', $parser->normalizeMarkdownHtml($docx));
    }
}
