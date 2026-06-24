<?php

namespace Tests\Feature\Schola;

use App\Enums\BaseTheme;
use App\Services\Schola\LessonPresentationService;
use App\Support\Branding\FontPair;
use App\Support\Branding\ResolvedTheme;
use Tests\TestCase;
use ZipArchive;

/**
 * P27 Fase 3 — generatore slide: 16:9, model aggiornato, resolvedTheme applicato,
 * contratto layout chiuso con fallback. (Il QA visivo vive a parte: rende in
 * immagini e ispeziona.)
 */
class LessonPresentationGeneratorTest extends TestCase
{
    private function service(): LessonPresentationService
    {
        return new LessonPresentationService();
    }

    private function glitchTheme(?string $logo = null): ResolvedTheme
    {
        $p = BaseTheme::Glitch->palette();

        return new ResolvedTheme(BaseTheme::Glitch, $p['ink'], $p['background'], $p['accent'], FontPair::named('editoriale'), $logo);
    }

    /** Renderizza una spec e ritorna il path del .pptx temporaneo. */
    private function render(array $spec): string
    {
        $out = tempnam(sys_get_temp_dir(), 'pptx_') . '.pptx';
        $this->service()->renderPptx($spec, $out);
        $this->assertFileExists($out);

        return $out;
    }

    private function zipText(string $pptx, string $entry): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($pptx) === true);
        $xml = $zip->getFromName($entry);
        $zip->close();

        return $xml ?: '';
    }

    // ============================================================

    public function test_model_string_aggiornato(): void
    {
        $this->assertSame('claude-sonnet-4-6', config('services.pptx.model'));
    }

    public function test_slide_sono_16_9(): void
    {
        $spec = $this->service()->buildSpec('T', 'S', 'Scuola', $this->glitchTheme(), [
            ['layout' => 'bullets_clean', 'title' => 'A', 'bullets' => ['x']],
        ]);
        $pptx = $this->render($spec);

        $xml = $this->zipText($pptx, 'ppt/presentation.xml');
        preg_match('/cx="(\d+)" cy="(\d+)"/', $xml, $m);
        [$cx, $cy] = [(int) $m[1], (int) $m[2]];

        $this->assertSame(6858000, $cy);                       // 7.5"
        $this->assertEqualsWithDelta(16 / 9, $cx / $cy, 0.01);  // 16:9, non 4:3 (che sarebbe ~1.33)
        @unlink($pptx);
    }

    public function test_buildspec_inietta_tema_e_prepone_cover(): void
    {
        $spec = $this->service()->buildSpec('Titolo', 'Sottotitolo', 'Liceo X', $this->glitchTheme('school-logos/x/logo.png'), [
            ['layout' => 'bullets_clean', 'title' => 'Sez', 'bullets' => ['a', 'b']],
        ]);

        // tema
        $this->assertSame('0A0A0A', $spec['theme']['ink']);
        $this->assertSame('F4F1EA', $spec['theme']['background']);
        $this->assertSame('A6192E', $spec['theme']['accent']);
        $this->assertSame('Playfair Display', $spec['theme']['fonts']['title']['primary']);
        $this->assertSame('Bookman Old Style', $spec['theme']['fonts']['title']['fallback']);

        // cover prepostata
        $this->assertSame('cover', $spec['slides'][0]['layout']);
        $this->assertSame('Titolo', $spec['slides'][0]['title']);
        $this->assertSame('Liceo X', $spec['slides'][0]['school']);
        $this->assertSame('bullets_clean', $spec['slides'][1]['layout']);
    }

    public function test_tema_applicato_nel_pptx(): void
    {
        $spec = $this->service()->buildSpec('Cover', 'Sub', 'Scuola', $this->glitchTheme(), [
            ['layout' => 'bullets_clean', 'title' => 'Sezione', 'bullets' => ['punto']],
        ]);
        $pptx = $this->render($spec);

        $cover = $this->zipText($pptx, 'ppt/slides/slide1.xml');
        $content = $this->zipText($pptx, 'ppt/slides/slide2.xml');

        $this->assertStringContainsString('0A0A0A', $cover, 'cover su sfondo ink scuro');
        $this->assertStringContainsString('A6192E', $content, 'accento applicato nel contenuto');
        $this->assertStringContainsString('F4F1EA', $content, 'sfondo chiaro del tema nel contenuto');
        @unlink($pptx);
    }

    public function test_ogni_layout_del_menu_rende(): void
    {
        $spec = $this->service()->buildSpec('T', 'S', 'Scuola', $this->glitchTheme(), [
            ['layout' => 'process_cards', 'title' => 'Ciclo', 'steps' => [['title' => 'A', 'text' => 'x'], ['title' => 'B', 'text' => 'y']]],
            ['layout' => 'columns', 'title' => 'Tre', 'columns' => [['title' => 'A', 'text' => 'x'], ['title' => 'B', 'text' => 'y'], ['title' => 'C', 'text' => 'z']]],
            ['layout' => 'stat', 'value' => '3×', 'label' => 'meglio'],
            ['layout' => 'bullets_clean', 'title' => 'Punti', 'bullets' => ['a', 'b', 'c']],
        ]);
        $pptx = $this->render($spec);

        // cover + 4 layout = 5 slide
        $zip = new ZipArchive();
        $zip->open($pptx);
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('#ppt/slides/slide\d+\.xml$#', $zip->getNameIndex($i))) {
                $count++;
            }
        }
        $zip->close();
        $this->assertSame(5, $count);
        @unlink($pptx);
    }

    // ---- contratto layout chiuso ----

    public function test_layout_sconosciuto_fallback_bullets(): void
    {
        $out = $this->service()->normalizeSlides([
            ['layout' => 'pyramid_3d', 'title' => 'Strano', 'bullets' => ['a', 'b']],
        ]);

        $this->assertSame('bullets_clean', $out[0]['layout']);
        $this->assertSame('Strano', $out[0]['title']);
        $this->assertSame(['a', 'b'], $out[0]['bullets']);
    }

    public function test_layout_validi_preservati(): void
    {
        $out = $this->service()->normalizeSlides([
            ['layout' => 'process_cards', 'title' => 'P', 'steps' => [['title' => 'A', 'text' => 'x']]],
            ['layout' => 'stat', 'value' => '70%', 'label' => 'quota'],
        ]);

        $this->assertSame('process_cards', $out[0]['layout']);
        $this->assertCount(1, $out[0]['steps']);
        $this->assertSame('stat', $out[1]['layout']);
        $this->assertSame('70%', $out[1]['value']);
    }

    public function test_campi_mancanti_cadono_in_fallback(): void
    {
        $out = $this->service()->normalizeSlides([
            ['layout' => 'columns', 'title' => 'Solo una', 'columns' => [['title' => 'A', 'text' => 'x']]], // <2 → fallback
            ['layout' => 'stat', 'title' => 'Senza valore', 'label' => 'x'],                                  // no value → fallback
            ['layout' => 'process_cards', 'title' => 'Senza step', 'steps' => []],                             // no steps → fallback
        ]);

        foreach ($out as $slide) {
            $this->assertSame('bullets_clean', $slide['layout']);
            $this->assertNotEmpty($slide['bullets']); // mai una slide vuota/rotta
        }
    }
}
