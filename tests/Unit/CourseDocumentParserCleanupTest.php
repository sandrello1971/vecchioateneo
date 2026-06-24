<?php

namespace Tests\Unit;

use App\Services\CourseDocumentParser;
use ReflectionClass;
use Tests\TestCase;

/**
 * Fix ingestion #1 — pipeline. normalizeHeadings() ora, oltre alla promozione
 * heading storica, fa pulizia conservativa: rimuove front-matter manuale (a) e
 * prompt-segnaposto (d) post-heading, e promuove i numerati "N. TITOLO" (c).
 * Enfasi sui casi CONSERVATIVI che NON devono attivarsi (no contenuto mangiato,
 * no paragrafi promossi a caso). Metodi puri → istanza senza costruttore.
 */
class CourseDocumentParserCleanupTest extends TestCase
{
    private CourseDocumentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ReflectionClass(CourseDocumentParser::class))->newInstanceWithoutConstructor();
    }

    // ============================================================
    // (a) front-matter post-heading
    // ============================================================

    public function test_frontmatter_post_heading_rimosso_ma_contenuto_reale_preservato(): void
    {
        $html = <<<'HTML'
<h2>Il mondo che non aspetta</h2>
<p>MANUALE DISCENTE</p>
<p>Noscite — In digitālī nova virtūs</p>
<p>Richard Feynman aveva un principio che guida tutto il lavoro: spiegare in modo semplice.</p>
HTML;
        $out = $this->parser->normalizeHeadings($html);

        $this->assertStringNotContainsString('MANUALE DISCENTE', $out);
        $this->assertStringNotContainsString('nova virtūs', $out);
        // Il titolo (heading) e il paragrafo di contenuto reale restano.
        $this->assertStringContainsString('Il mondo che non aspetta', $out);
        $this->assertStringContainsString('Richard Feynman', $out);
    }

    public function test_payoff_nuovo_brand_rimosso(): void
    {
        $out = $this->parser->normalizeHeadings('<h1>X</h1><p>Il Rumore Che Serve</p><p>Contenuto vero del corso.</p>');
        $this->assertStringNotContainsString('Il Rumore Che Serve', $out);
        $this->assertStringContainsString('Contenuto vero del corso.', $out);
    }

    public function test_paragrafo_lungo_che_cita_il_marcatore_NON_viene_rimosso(): void
    {
        // CONSERVATIVO: una frase reale che contiene "manuale discente" come parte
        // di un discorso (paragrafo lungo) NON deve sparire.
        $html = '<p>In questo manuale discente troverai esercizi pratici e casi reali pensati per accompagnarti, passo dopo passo, lungo tutto il percorso formativo aziendale.</p>';
        $out = $this->parser->normalizeHeadings($html);
        $this->assertStringContainsString('esercizi pratici', $out);
    }

    // ============================================================
    // (c) heading numerati
    // ============================================================

    public function test_numero_titolo_maiuscolo_promosso_a_heading(): void
    {
        $out = $this->parser->normalizeHeadings('<p>1. UTILIZZO DEGLI STRUMENTI AI</p><p>2. GOVERNANCE DEI DATI</p>');
        $this->assertStringContainsString('<h3>1. UTILIZZO DEGLI STRUMENTI AI</h3>', $out);
        $this->assertStringContainsString('<h3>2. GOVERNANCE DEI DATI</h3>', $out);
    }

    public function test_lista_normale_NON_promossa(): void
    {
        // CONSERVATIVO: voci/frasi minuscole non sono titoli.
        $html = '<p>1. compra il latte</p><p>2. chiama il fornitore e conferma la consegna</p>';
        $out = $this->parser->normalizeHeadings($html);
        $this->assertStringNotContainsString('<h3>', $out);
        $this->assertStringContainsString('<p>1. compra il latte</p>', $out);
    }

    public function test_titlecase_numerato_NON_promosso(): void
    {
        // CONSERVATIVO: "1. Introduzione ai modelli" (title/sentence case) resta <p>.
        $out = $this->parser->normalizeHeadings('<p>1. Introduzione ai modelli linguistici</p>');
        $this->assertStringNotContainsString('<h3>', $out);
    }

    public function test_frase_numerata_con_punteggiatura_NON_promossa(): void
    {
        // Anche se maiuscola, se termina come frase (.) non è un titolo.
        $out = $this->parser->normalizeHeadings('<p>1. VERIFICA SEMPRE I DATI PRIMA DI PROCEDERE.</p>');
        $this->assertStringNotContainsString('<h3>', $out);
    }

    // ============================================================
    // (d) prompt-segnaposto
    // ============================================================

    public function test_segnaposto_noto_omesso(): void
    {
        $out = $this->parser->normalizeHeadings('<p>Usa questo spazio durante il corso:</p>');
        $this->assertStringNotContainsString('Usa questo spazio', $out);
    }

    public function test_testo_normale_simile_NON_omesso(): void
    {
        // CONSERVATIVO: una frase che parla di "spazio" ma non è il prompt noto resta.
        $html = '<p>Lo spazio di lavoro condiviso è dove il team collabora ogni giorno.</p>';
        $out = $this->parser->normalizeHeadings($html);
        $this->assertStringContainsString('spazio di lavoro condiviso', $out);
    }

    // ============================================================
    // Regressione: docx "normale" invariato
    // ============================================================

    public function test_documento_normale_resta_invariato(): void
    {
        // Heading veri + liste + paragrafi: nessuna delle nuove regole deve attivarsi.
        $html = <<<'HTML'
<h1>Capitolo 1 — Introduzione</h1>
<p>Questo è un paragrafo normale di contenuto didattico, scritto in prosa.</p>
<ul><li>Primo punto di una lista.</li><li>Secondo punto della lista.</li></ul>
<h2>1.1 — Approfondimento</h2>
<p>Altro contenuto reale del capitolo, senza marcatori né segnaposto.</p>
HTML;
        $this->assertSame($html, $this->parser->normalizeHeadings($html));
    }
}
