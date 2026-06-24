<?php

namespace Tests\Unit;

use App\Services\CourseDocumentParser;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regressione: normalizeHeadings() non deve far "scavalcare" il match oltre il
 * confine </p> di un paragrafo.
 *
 * Bug reale (corso "schola"): un paragrafo in cui <strong> è solo un PREFISSO
 * (es. "<strong>Sezione 1 — Principi</strong>. testo...") faceva sì che, col
 * flag /s e (.*?), la regex cercasse il primo </strong></p> molto più in basso,
 * inglobando interi <h1> di capitoli successivi (CAPITOLO 5) in un unico falso
 * heading e cancellando il confine del capitolo.
 *
 * Il parser usa $this->llm solo altrove: i metodi qui testati sono puri, quindi
 * istanziamo senza costruttore e non tocchiamo né DB né LLM.
 */
class CourseDocumentParserHeadingTest extends TestCase
{
    private CourseDocumentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ReflectionClass(CourseDocumentParser::class))
            ->newInstanceWithoutConstructor();
    }

    public function test_strong_prefisso_non_ingloba_gli_heading_successivi(): void
    {
        // <strong> è solo prefisso del paragrafo, e "Sezione 1" combacia col
        // pattern di livello 1: senza il fix questo blocco si "mangiava" il <h1>.
        $html = <<<'HTML'
<h1>CAPITOLO 4 — AI literacy degli studenti</h1>
<p><strong>Sezione 1 — Principi</strong>. Umanesimo Digitale, doppia missione.</p>
<p><strong>Sezione 2 — Regole</strong>: ammesso, condizionato, vietato.</p>
<h1>CAPITOLO 5 — Governance scolastica</h1>
<p>Contenuto del capitolo cinque.</p>
HTML;

        $modules = $this->parser->normalizeAndSplitIntoModules($html);

        $titles = array_map(fn ($m) => trim($m['title']), $modules);

        $this->assertContains('CAPITOLO 4 — AI literacy degli studenti', $titles);
        $this->assertContains('CAPITOLO 5 — Governance scolastica', $titles);
        $this->assertCount(2, $modules, 'I due capitoli devono restare moduli distinti.');

        // Le "Sezioni" (strong-prefisso) NON sono heading: restano dentro il Cap. 4.
        $cap4 = $modules[0]['content_html'];
        $this->assertStringContainsString('Sezione 1 — Principi', $cap4);
        $this->assertStringContainsString('Sezione 2 — Regole', $cap4);
    }

    public function test_paragrafo_interamente_grassetto_diventa_heading(): void
    {
        // Comportamento legittimo preservato: <p><strong>Capitolo N</strong></p>
        // (grassetto sull'intero paragrafo) viene promosso a heading di capitolo.
        $html = <<<'HTML'
<p><strong>Capitolo 1 — Introduzione</strong></p>
<p>Testo del primo capitolo.</p>
<p><strong>Capitolo 2 — Approfondimento</strong></p>
<p>Testo del secondo capitolo.</p>
HTML;

        $modules = $this->parser->normalizeAndSplitIntoModules($html);

        $titles = array_map(fn ($m) => trim($m['title']), $modules);

        $this->assertCount(2, $modules);
        $this->assertContains('Capitolo 1 — Introduzione', $titles);
        $this->assertContains('Capitolo 2 — Approfondimento', $titles);
    }
}
