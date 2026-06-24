<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LessonPresentation;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Services\Schola\LessonPresentationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * P28 Fase 1 — slide per modulo (corsi Officina): schema module_presentations +
 * orchestratore condiviso (buildForModule riusa buildFrom). Additivo: non tocca
 * lesson_presentations/Schola.
 */
class ModulePresentationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LessonPresentationService
    {
        return new LessonPresentationService();
    }

    private function makeModule(?string $content = "## Intro\n\nPrimo punto.\n\n## Sviluppo\n\nSecondo punto."): Module
    {
        $course = Course::create([
            'name' => 'Fondamenti AI',
            'slug' => 'corso-' . Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        return Module::create([
            'course_id' => $course->id,
            'title' => 'Modulo 1 — Introduzione',
            'content' => $content,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    /** Fake della Claude API: ritorna una spec valida con due layout. */
    private function fakeLlm(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode(['slides' => [
                ['layout' => 'bullets_clean', 'title' => 'Introduzione', 'bullets' => ['Punto A', 'Punto B']],
                ['layout' => 'stat', 'value' => '3×', 'label' => 'più efficace'],
            ]])]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ], 200)]);
    }

    // ============================================================
    // Schema
    // ============================================================

    public function test_module_presentation_si_crea_con_default_pending(): void
    {
        $module = $this->makeModule();
        $mp = ModulePresentation::create(['module_id' => $module->id]);

        $this->assertSame('pending', $mp->fresh()->status);
        $this->assertTrue($mp->module->is($module));
    }

    public function test_status_fuori_enum_respinto_dal_check(): void
    {
        $module = $this->makeModule();
        $this->expectException(QueryException::class);
        \Illuminate\Support\Facades\DB::table('module_presentations')->insert([
            'id' => Str::uuid(), 'module_id' => $module->id, 'status' => 'bogus',
        ]);
    }

    public function test_una_sola_presentazione_per_modulo(): void
    {
        $module = $this->makeModule();
        ModulePresentation::create(['module_id' => $module->id]);

        $this->expectException(QueryException::class);
        ModulePresentation::create(['module_id' => $module->id]);
    }

    // ============================================================
    // buildForModule
    // ============================================================

    public function test_build_for_module_genera_pptx_glitch_nel_path_modulo(): void
    {
        Storage::fake('local');
        $this->fakeLlm();

        $module = $this->makeModule();
        $mp = ModulePresentation::create(['module_id' => $module->id]);

        $result = $this->service()->buildForModule($mp);

        // path nello spazio modulo (non lezione)
        $this->assertSame("module-presentations/{$module->id}/{$mp->id}.pptx", $result['file_path']);
        Storage::disk('local')->assertExists($result['file_path']);
        $this->assertSame(3, $result['meta']['slides']); // cover + 2

        // S0: buildFrom restituisce la spec COMPLETA (cover + slides + theme) per la persistenza.
        $this->assertArrayHasKey('spec', $result);
        $this->assertSame('cover', $result['spec']['slides'][0]['layout']);
        $this->assertCount(3, $result['spec']['slides']);
        $this->assertArrayHasKey('theme', $result['spec']);

        // tema GLITCH applicato (forPlatform: nessun brand_profile → default GLITCH)
        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path($result['file_path']));
        $cover = $zip->getFromName('ppt/slides/slide1.xml');
        $content = $zip->getFromName('ppt/slides/slide2.xml');
        $pres = $zip->getFromName('ppt/presentation.xml');
        $zip->close();

        $this->assertStringContainsString('0A0A0A', $cover);    // ink scuro (cover)
        $this->assertStringContainsString('A6192E', $content);  // accento cremisi GLITCH
        $this->assertStringContainsString('F4F1EA', $content);  // sfondo avorio GLITCH
        preg_match('/cx="(\d+)" cy="(\d+)"/', $pres, $m);
        $this->assertEqualsWithDelta(16 / 9, (int) $m[1] / (int) $m[2], 0.01); // 16:9
    }

    public function test_modulo_senza_content_errore_pulito_niente_file(): void
    {
        Storage::fake('local');
        $this->fakeLlm();

        $module = $this->makeModule(content: '');
        $mp = ModulePresentation::create(['module_id' => $module->id]);

        try {
            $this->service()->buildForModule($mp);
            $this->fail('Atteso RuntimeException per content vuoto.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('non ha un corpo', $e->getMessage());
        }

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_isolamento_non_tocca_lesson_presentations(): void
    {
        Storage::fake('local');
        $this->fakeLlm();

        $module = $this->makeModule();
        $mp = ModulePresentation::create(['module_id' => $module->id]);
        $this->service()->buildForModule($mp);

        $this->assertSame(0, LessonPresentation::count(), 'La generazione modulo non deve toccare le presentazioni Schola.');
    }
}
