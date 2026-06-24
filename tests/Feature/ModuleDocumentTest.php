<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleDocument;
use App\Models\BrandProfile;
use App\Models\ModulePresentation;
use App\Services\CourseSourcePdfBuilder;
use App\Services\ModuleDocumentService;
use App\Support\Branding\FontPair;
use App\Support\Branding\ResolvedTheme;
use App\Enums\BaseTheme;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * P29 Fase 1 — documento PDF per modulo (corsi Officina): schema module_documents
 * con stale-detection (content_hash) + renderer riusato (CourseSourcePdfBuilder,
 * HTML→blocchi) + service buildDocumentForModule. Additivo: non tocca P28/Schola.
 */
class ModuleDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ModuleDocumentService
    {
        return app(ModuleDocumentService::class);
    }

    private function makeModule(?string $content = '<h2>Introduzione</h2><p>Primo paragrafo del modulo.</p><h3>Dettagli</h3><ul><li>Punto A</li><li>Punto B</li></ul>'): Module
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

    // ============================================================
    // Schema
    // ============================================================

    public function test_module_document_si_crea_con_default_pending(): void
    {
        $module = $this->makeModule();
        $md = ModuleDocument::create(['module_id' => $module->id]);

        $this->assertSame('pending', $md->fresh()->status);
        $this->assertNull($md->fresh()->content_hash);
        $this->assertTrue($md->module->is($module));
    }

    public function test_status_fuori_enum_respinto_dal_check(): void
    {
        $module = $this->makeModule();
        $this->expectException(QueryException::class);
        \Illuminate\Support\Facades\DB::table('module_documents')->insert([
            'id' => Str::uuid(), 'module_id' => $module->id, 'status' => 'bogus',
        ]);
    }

    public function test_un_solo_documento_per_modulo(): void
    {
        $module = $this->makeModule();
        ModuleDocument::create(['module_id' => $module->id]);

        $this->expectException(QueryException::class);
        ModuleDocument::create(['module_id' => $module->id]);
    }

    // ============================================================
    // buildDocumentForModule
    // ============================================================

    public function test_build_document_genera_pdf_nel_path_modulo_e_salva_hash(): void
    {
        Storage::fake('local');

        $module = $this->makeModule();
        $md = ModuleDocument::create(['module_id' => $module->id]);

        $result = $this->service()->buildDocumentForModule($md);

        // path nello spazio modulo + status ready
        $this->assertSame("module-documents/{$module->id}/{$md->id}.pdf", $result->file_path);
        Storage::disk('local')->assertExists($result->file_path);
        $this->assertSame('ready', $result->status);

        // file è davvero un PDF
        $bytes = Storage::disk('local')->get($result->file_path);
        $this->assertStringStartsWith('%PDF', $bytes);

        // content_hash registrato = hash corrente del content (segnale di stale)
        $this->assertSame($module->currentContentHash(), $result->content_hash);
        $this->assertGreaterThan(0, $result->generation_meta['blocks'] ?? 0);
    }

    // ============================================================
    // isStale
    // ============================================================

    public function test_is_stale_falso_subito_dopo_generazione(): void
    {
        Storage::fake('local');

        $module = $this->makeModule();
        $md = ModuleDocument::create(['module_id' => $module->id]);
        $this->service()->buildDocumentForModule($md);

        $this->assertFalse($md->fresh()->isStale());
    }

    public function test_is_stale_vero_se_content_cambia(): void
    {
        Storage::fake('local');

        $module = $this->makeModule();
        $md = ModuleDocument::create(['module_id' => $module->id]);
        $this->service()->buildDocumentForModule($md);

        // Il contenuto del modulo cambia DOPO la generazione → hash diverso.
        $module->update(['content' => '<h2>Contenuto rivisto</h2><p>Testo aggiornato.</p>']);

        $this->assertTrue($md->fresh()->isStale());
    }

    public function test_is_stale_falso_se_non_ready(): void
    {
        $module = $this->makeModule();
        $md = ModuleDocument::create(['module_id' => $module->id]); // pending, nessun hash

        $this->assertFalse($md->isStale());
    }

    // ============================================================
    // Gate content vuoto + isolamento
    // ============================================================

    public function test_modulo_senza_content_errore_pulito_niente_file(): void
    {
        Storage::fake('local');

        $module = $this->makeModule(content: '');
        $md = ModuleDocument::create(['module_id' => $module->id]);

        try {
            $this->service()->buildDocumentForModule($md);
            $this->fail('Atteso RuntimeException per content vuoto.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('non ha un corpo', $e->getMessage());
        }

        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertSame('pending', $md->fresh()->status); // modello intatto
    }

    public function test_isolamento_non_tocca_module_presentations(): void
    {
        Storage::fake('local');

        $module = $this->makeModule();
        $md = ModuleDocument::create(['module_id' => $module->id]);
        $this->service()->buildDocumentForModule($md);

        $this->assertSame(0, ModulePresentation::count(), 'La generazione documento non deve toccare le presentazioni (P28).');
    }

    // ============================================================
    // Branding theme-agnostico (P29 fix)
    // ============================================================

    /** Legge la palette ATTIVA del builder dopo un render (prova deterministica del tema applicato). */
    private function activePalette(CourseSourcePdfBuilder $builder): array
    {
        $ref = new \ReflectionClass($builder);
        $accent = $ref->getProperty('accentRgb');
        $ink = $ref->getProperty('inkRgb');
        $accent->setAccessible(true);
        $ink->setAccessible(true);

        return ['accent' => $accent->getValue($builder), 'ink' => $ink->getValue($builder)];
    }

    public function test_renderer_senza_tema_resta_sul_teal_default_p25(): void
    {
        $builder = new CourseSourcePdfBuilder();
        $builder->buildFromHtml('<h2>Titolo</h2><p>Corpo.</p>', ['title' => 'Doc']);

        // Nessun tema → retrocompat P25: teal #55B1AE e ink storico.
        $this->assertSame([85, 177, 174], $this->activePalette($builder)['accent']);
        $this->assertSame([26, 31, 31], $this->activePalette($builder)['ink']);
    }

    public function test_renderer_usa_brand_glitch_quando_il_service_lo_passa(): void
    {
        $builder = new CourseSourcePdfBuilder();
        $theme = BrandProfile::forPlatform()->resolvedTheme(); // GLITCH

        $builder->buildFromHtml('<h2>Titolo</h2><p>Corpo.</p>', ['title' => 'Doc'], $theme);

        // GLITCH: accent cremisi A6192E = (166,25,46), ink 0A0A0A = (10,10,10). NON teal.
        $this->assertSame([166, 25, 46], $this->activePalette($builder)['accent']);
        $this->assertSame([10, 10, 10], $this->activePalette($builder)['ink']);
        $this->assertNotSame([85, 177, 174], $this->activePalette($builder)['accent']);
    }

    public function test_renderer_e_agnostico_accetta_un_tema_qualsiasi(): void
    {
        $builder = new CourseSourcePdfBuilder();
        // Tema ARBITRARIO (non GLITCH, non una palette nota): il renderer non sa cosa sia.
        $arbitrary = new ResolvedTheme(
            theme: BaseTheme::Glitch, // irrilevante: i colori sotto sono espliciti
            ink: '203040',
            background: 'FFFFFF',
            accent: '1D4ED8', // blu arbitrario
            fonts: FontPair::tryNamed(null) ?? BaseTheme::Glitch->fonts(),
            logoPath: null,
        );

        $pdf = $builder->buildFromHtml('<h2>Titolo</h2><p>Corpo.</p>', ['title' => 'Doc'], $arbitrary);

        $this->assertStringStartsWith('%PDF', $pdf);
        // I colori del PDF seguono il tema passato, non un brand hardcoded.
        $this->assertSame([29, 78, 216], $this->activePalette($builder)['accent']);
        $this->assertSame([32, 48, 64], $this->activePalette($builder)['ink']);
    }
}
