<?php

namespace Tests\Unit;

use App\Services\CourseSourceExtractor;
use Tests\TestCase;

/**
 * P25.1 — Test del CUORE dell'estrattore: mapAst() (AST pandoc → blocchi tipizzati).
 *
 * Indipendente da pandoc (l'AST è costruito in-memory, equivale a una fixture JSON)
 * e da TCPDF. Estende Tests\TestCase solo per avere il facade Log disponibile; NON
 * tocca il database. Copre in particolare i casi che INTERFERENZA non esercita
 * (BOX, EX/ESE, NUM/BUL, X.Y.Z) così i blocchi rari hanno copertura reale.
 */
class CourseSourceExtractorMapTest extends TestCase
{
    private CourseSourceExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new CourseSourceExtractor();
    }

    // ---- builder di nodi AST pandoc (equivalenti al JSON di `pandoc --to=json`) ----

    private function inlines(string $text): array
    {
        return [['t' => 'Str', 'c' => $text]];
    }

    private function header(int $level, string $text): array
    {
        return ['t' => 'Header', 'c' => [$level, ['', [], []], $this->inlines($text)]];
    }

    private function para(string $text): array
    {
        return ['t' => 'Para', 'c' => $this->inlines($text)];
    }

    private function blockquote(string $text): array
    {
        return ['t' => 'BlockQuote', 'c' => [$this->para($text)]];
    }

    /** Paragrafo interamente in grassetto (pseudo-titolo di alcuni manuali). */
    private function boldPara(string $text): array
    {
        return ['t' => 'Para', 'c' => [['t' => 'Strong', 'c' => $this->inlines($text)]]];
    }

    /** Paragrafo con grassetto SOLO all'inizio + prosa (NON è un titolo). */
    private function mixedBoldPara(string $boldStart, string $rest): array
    {
        return ['t' => 'Para', 'c' => [
            ['t' => 'Strong', 'c' => $this->inlines($boldStart)],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => $rest],
        ]];
    }

    private function divStyle(string $style, array $children): array
    {
        return ['t' => 'Div', 'c' => [['', [], [['custom-style', $style]]], $children]];
    }

    private function bulletList(array $items): array
    {
        return ['t' => 'BulletList', 'c' => array_map(fn ($i) => [$this->para($i)], $items)];
    }

    private function orderedList(array $items): array
    {
        return ['t' => 'OrderedList', 'c' => [
            [1, ['t' => 'Decimal'], ['t' => 'Period']],
            array_map(fn ($i) => [$this->para($i)], $items),
        ]];
    }

    private function map(array $blocks): array
    {
        return $this->extractor->mapAst(['blocks' => $blocks]);
    }

    /** @return array<string,array> blocchi indicizzati per id */
    private function byId(array $result): array
    {
        $out = [];
        foreach ($result['blocks'] as $b) {
            $out[$b['id']] = $b;
        }
        return $out;
    }

    /** @return list<array{0:string,1:string}> coppie [type, id] in ordine */
    private function typeIdPairs(array $result): array
    {
        return array_map(fn ($b) => [$b['type'], $b['id']], $result['blocks']);
    }

    // ------------------------------- CASI BASE -------------------------------

    public function test_base_part_h1_h2_p_e_prosa_resta_p(): void
    {
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — FONDAMENTI'),
            $this->header(2, 'Capitolo 1 — Filosofia'),
            $this->header(3, '1.1 Introduzione'),
            $this->para('Primo paragrafo di prosa lunga che resta un singolo blocco P.'),
            $this->para('Secondo paragrafo, anch’esso prosa: niente liste qui.'),
        ]);

        $this->assertSame([
            ['PART', 'p1'],
            ['H1', 'p1-cap1'],
            ['H2', 'p1-cap1-sec1'],
            ['P', 'p1-cap1-sec1-p1'],
            ['P', 'p1-cap1-sec1-p2'],
        ], $this->typeIdPairs($r));

        $this->assertSame('PARTE PRIMA — FONDAMENTI', $r['blocks'][0]['text']);
        $this->assertStringContainsString('prosa lunga', $r['blocks'][3]['text']);
        $this->assertEmpty($r['warnings']);
    }

    // ------------------------------- BOX -------------------------------

    public function test_blockquote_diventa_box(): void
    {
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — X'),
            $this->header(2, 'Capitolo 1 — Y'),
            $this->blockquote('Contenuto del riquadro evidenziato.'),
        ]);

        $box = $this->byId($r)['p1-cap1-box1'] ?? null;
        $this->assertNotNull($box, 'Il blockquote deve produrre un BOX con id atteso');
        $this->assertSame('BOX', $box['type']);
        $this->assertSame('Contenuto del riquadro evidenziato.', $box['text']);
    }

    public function test_stile_block_text_diventa_box(): void
    {
        // Div con custom-style "Block Text" (caso `+styles`) → BOX, non appiattito a P.
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — X'),
            $this->header(2, 'Capitolo 1 — Y'),
            $this->divStyle('Block Text', [$this->para('Testo dentro lo stile Block Text.')]),
        ]);

        $box = $this->byId($r)['p1-cap1-box1'] ?? null;
        $this->assertNotNull($box);
        $this->assertSame('BOX', $box['type']);
        $this->assertSame('Testo dentro lo stile Block Text.', $box['text']);
    }

    public function test_div_non_box_viene_appiattito_a_p(): void
    {
        // Un Div con stile non box-like (es. BodyText di pandoc) NON deve diventare BOX:
        // il Para interno resta P. È il bug che avevamo intercettato sul piccolo.
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — X'),
            $this->header(2, 'Capitolo 1 — Y'),
            $this->divStyle('Body Text', [$this->para('Paragrafo normale incapsulato in un Div di stile.')]),
        ]);

        $p = $this->byId($r)['p1-cap1-p1'] ?? null;
        $this->assertNotNull($p);
        $this->assertSame('P', $p['type']);
    }

    // ------------------------------- EX / ESE (euristica fragile) -------------------------------

    public function test_euristica_ex_ese_quando_scatta_e_quando_no(): void
    {
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — X'),
            $this->header(2, 'Capitolo 1 — Y'),
            $this->para('Esempio: come applicare il metodo.'),     // → EX
            $this->para('Esempio pratico senza due punti.'),       // → EX (apre con la parola)
            $this->para('Esercizio: prova a rifarlo da solo.'),    // → ESE
            $this->para('Per esempio, questo NON deve scattare.'), // → P
            $this->para('Esempification non è la parola esatta.'), // → P (no word boundary)
        ]);

        $types = array_map(fn ($b) => $b['type'], $r['blocks']);
        // [PART, H1, EX, EX, ESE, P, P]
        $this->assertSame(['PART', 'H1', 'EX', 'EX', 'ESE', 'P', 'P'], $types);
    }

    // ------------------------------- NUM / BUL -------------------------------

    public function test_liste_diventano_num_e_bul_non_p(): void
    {
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — X'),
            $this->header(2, 'Capitolo 1 — Y'),
            $this->bulletList(['primo punto', 'secondo punto']),
            $this->orderedList(['passo uno', 'passo due', 'passo tre']),
        ]);

        $byId = $this->byId($r);
        $bul = $byId['p1-cap1-bul1'] ?? null;
        $num = $byId['p1-cap1-num1'] ?? null;

        $this->assertNotNull($bul);
        $this->assertSame('BUL', $bul['type']);
        $this->assertSame(['primo punto', 'secondo punto'], $bul['items']);

        $this->assertNotNull($num);
        $this->assertSame('NUM', $num['type']);
        $this->assertCount(3, $num['items']);

        // Nessuna lista deve essere stata scambiata per P.
        foreach ($r['blocks'] as $b) {
            if (in_array($b['type'], ['NUM', 'BUL'], true)) {
                $this->assertArrayHasKey('items', $b);
            }
        }
    }

    // ------------------------------- X.Y.Z → H2 + warning -------------------------------

    public function test_sezione_profonda_degrada_a_h2_con_warning(): void
    {
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — X'),
            $this->header(2, 'Capitolo 1 — Y'),
            $this->header(3, '1.2.3 Sottosezione molto profonda'),
            $this->para('corpo'),
        ]);

        $byId = $this->byId($r);
        $sec = $byId['p1-cap1-sec1'] ?? null;
        $this->assertNotNull($sec, 'X.Y.Z deve comunque emettere un H2 (degradato), senza errore');
        $this->assertSame('H2', $sec['type']);

        // Esattamente 1 warning, e parla del degrado.
        $this->assertCount(1, $r['warnings']);
        $this->assertStringContainsString('degrad', mb_strtolower($r['warnings'][0]));

        // Il paragrafo sotto è agganciato alla sezione degradata, senza buchi.
        $this->assertArrayHasKey('p1-cap1-sec1-p1', $byId);
    }

    // ------------------------------- FRONTMATTER escluso, niente buchi -------------------------------

    public function test_frontmatter_escluso_senza_buchi_negli_id(): void
    {
        $r = $this->map([
            $this->header(1, 'Table of Contents'),
            $this->header(1, 'MANUALE FORMATORE — CONSILIUM v2.0'),
            $this->header(2, 'Strategia AI per PMI — Edizione Aprile 2026'),
            $this->para('Noscite — In digitālī nova virtūs'),
            $this->para('Documento riservato al docente. Non distribuire.'),
            // ---- inizio contenuto ----
            $this->header(1, 'PARTE PRIMA — FONDAMENTI'),
            $this->header(2, 'Capitolo 1 — Filosofia'),
            $this->header(3, '1.1 Introduzione'),
            $this->para('Primo paragrafo del corpo.'),
        ]);

        // 5 voci di frontmatter, tutte escluse dai blocchi.
        $this->assertCount(5, $r['frontmatter']);

        // Il PRIMO blocco emesso è p1 (PART), non p2: niente buco da frontmatter.
        $this->assertSame('p1', $r['blocks'][0]['id']);
        $this->assertSame('PART', $r['blocks'][0]['type']);

        // Il primo paragrafo del corpo è -p1 (contiguo), non -p2.
        $this->assertSame('p1-cap1-sec1-p1', $r['blocks'][3]['id']);
    }

    // ------------------------------- ID deterministici (regression guard) -------------------------------

    public function test_id_deterministici_esatti_con_reset_di_parte(): void
    {
        $r = $this->map([
            $this->header(1, 'PARTE PRIMA — A'),
            $this->header(2, 'Capitolo 1 — A1'),
            $this->header(3, '1.1 Sez'),
            $this->para('p uno'),
            $this->para('p due'),
            $this->header(3, '1.2 Sez'),
            $this->para('p tre'),
            $this->header(2, 'Capitolo 2 — A2'),
            $this->header(3, '2.1 Sez'),
            $this->para('q uno'),
            $this->para('q due'),
            $this->para('q tre'),
            $this->header(1, 'PARTE SECONDA — B'),   // reset capitolo e sezione
            $this->header(2, 'Capitolo 3 — B1'),
            $this->header(3, '3.1 Sez'),
            $this->para('r uno'),
        ]);

        $ids = array_map(fn ($b) => $b['id'], $r['blocks']);

        $this->assertSame([
            'p1',
            'p1-cap1',
            'p1-cap1-sec1',
            'p1-cap1-sec1-p1',
            'p1-cap1-sec1-p2',
            'p1-cap1-sec2',
            'p1-cap1-sec2-p1',
            'p1-cap2',
            'p1-cap2-sec1',
            'p1-cap2-sec1-p1',
            'p1-cap2-sec1-p2',
            'p1-cap2-sec1-p3',   // l'esempio canonico
            'p2',                // PARTE SECONDA: part incrementa, cap/sec resettano
            'p2-cap1',
            'p2-cap1-sec1',
            'p2-cap1-sec1-p1',
        ], $ids);
    }

    // ------------------------------- Titoli numerati (manuali Licei/Istituti) -------------------------------

    public function test_titoli_numerati_flat_diventano_h1(): void
    {
        $r = $this->map([
            $this->header(2, 'Manuale del Formatore — Edizione Licei'), // frontmatter (non riconosciuto)
            $this->header(1, '1. Introduzione al manuale del formatore'),
            $this->para('Testo introduttivo.'),
            $this->header(1, '3. Capitolo 0 — I due pilastri (1 ora)'),
            $this->header(1, '4. Modulo 1 — Capire l’AI (3 ore)'),
            $this->para('Contenuto del modulo.'),
        ]);

        $this->assertSame([
            ['H1', 'cap1'],
            ['P', 'cap1-p1'],
            ['H1', 'cap2'],
            ['H1', 'cap3'],
            ['P', 'cap3-p1'],
        ], $this->typeIdPairs($r));
        // Il sottotitolo iniziale resta frontmatter (escluso).
        $this->assertSame(['Manuale del Formatore — Edizione Licei'], $r['frontmatter']);
    }

    // ------------------------------- Titoli in grassetto (manuali FREQUENZA/RUMORE) -------------------------------

    public function test_paragrafi_grassetto_promossi_a_heading(): void
    {
        $r = $this->map([
            $this->boldPara('MANUALE PER IL FORMATORE'),          // frontmatter (non matcha pattern)
            $this->boldPara('PARTE PRIMA — FONDAMENTI'),          // → PART
            $this->boldPara('Capitolo 1 — Filosofia'),            // → H1
            $this->boldPara('1.1 Chi siete voi'),                 // → H2
            $this->para('Prosa normale del paragrafo.'),          // → P
            $this->mixedBoldPara('Nota importante:', 'questo grassetto è in mezzo alla prosa, NON un titolo.'),
        ]);

        $this->assertSame([
            ['PART', 'p1'],
            ['H1', 'p1-cap1'],
            ['H2', 'p1-cap1-sec1'],
            ['P', 'p1-cap1-sec1-p1'],
            ['P', 'p1-cap1-sec1-p2'], // il grassetto-in-prosa resta P
        ], $this->typeIdPairs($r));
        $this->assertStringContainsString('MANUALE PER IL FORMATORE', $r['frontmatter'][0]);
    }
}
