<?php

namespace Tests\Feature;

use App\Services\CourseSourcePdfBuilder;
use Tests\TestCase;

/**
 * P29 fix rendering — il builder deduplica il titolo in testa: se il content
 * ripete come primo heading lo stesso titolo già reso dalla banda TITLE/PART,
 * lo rimuove (difetto parser). Non rimuove heading diversi né ripetizioni più in
 * basso. Verifica via lastRenderedBlocks (conteggio blocchi resi). No DB.
 */
class CourseSourcePdfBuilderDedupTest extends TestCase
{
    private function builder(): CourseSourcePdfBuilder
    {
        return new CourseSourcePdfBuilder();
    }

    public function test_titolo_ripetuto_in_testa_viene_deduplicato(): void
    {
        $b = $this->builder();
        // TITLE + (h2 'Titolo' rimosso) + P  → 2 blocchi
        $b->buildFromHtml('<h2>Titolo</h2><p>Corpo.</p>', ['title' => 'Titolo']);
        $this->assertSame(2, $b->lastRenderedBlocks);
    }

    public function test_heading_diverso_in_testa_resta(): void
    {
        $b = $this->builder();
        // TITLE + H1 'Altro' + P → 3 blocchi (niente eco del titolo)
        $b->buildFromHtml('<h2>Altro</h2><p>Corpo.</p>', ['title' => 'Titolo']);
        $this->assertSame(3, $b->lastRenderedBlocks);
    }

    public function test_match_case_insensitive_e_spazi(): void
    {
        $b = $this->builder();
        $b->buildFromHtml('<h2>  il   MONDO che non aspetta </h2><p>Corpo.</p>', ['title' => 'Il mondo che non aspetta']);
        $this->assertSame(2, $b->lastRenderedBlocks); // eco rimosso nonostante case/spazi
    }

    public function test_primo_blocco_non_heading_non_viene_toccato(): void
    {
        $b = $this->builder();
        // Primo blocco è un <p> uguale al titolo → NON è un heading → resta.
        $b->buildFromHtml('<p>Titolo</p><p>Corpo.</p>', ['title' => 'Titolo']);
        $this->assertSame(3, $b->lastRenderedBlocks);
    }

    public function test_ripetizione_titolo_piu_in_basso_resta(): void
    {
        $b = $this->builder();
        // L'eco in testa è rimosso, ma un heading 'Titolo' più in basso resta.
        $b->buildFromHtml('<h2>Titolo</h2><p>x</p><h3>Titolo</h3>', ['title' => 'Titolo']);
        // TITLE + P + H2('Titolo' interno) = 3 (rimosso solo il primo eco)
        $this->assertSame(3, $b->lastRenderedBlocks);
    }

    public function test_dedup_per_sezione_nel_documento_corso(): void
    {
        $b = $this->builder();
        // Ogni sezione: PART(titolo modulo) + (h2 eco rimosso) + P.
        $sections = [
            ['title' => 'Modulo A', 'html' => '<h2>Modulo A</h2><p>a.</p>'],
            ['title' => 'Modulo B', 'html' => '<h2>Modulo B</h2><p>b.</p>'],
        ];
        $b->buildFromSections($sections, ['title' => 'Corso']);
        // TITLE + [PART A + P] + [PART B + P] = 5 (i due h2 eco rimossi)
        $this->assertSame(5, $b->lastRenderedBlocks);
    }
}
