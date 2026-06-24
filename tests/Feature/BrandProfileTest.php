<?php

namespace Tests\Feature;

use App\Enums\BaseTheme;
use App\Models\BrandProfile;
use App\Models\School;
use App\Support\Branding\ContrastGuard;
use App\Support\Branding\FontPair;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P27 Fase 1 — schema brand_profiles + modello BrandProfile + ContrastGuard.
 * Solo schema/modello: niente UI, niente generatore.
 */
class BrandProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(array $attrs = []): School
    {
        return School::create(array_merge([
            'name' => 'Liceo Test',
            'slug' => 'liceo-' . Str::lower(Str::random(8)),
            'type' => 'liceo',
        ], $attrs));
    }

    // ============================================================
    // Schema: morph nullable, CHECK base_theme, UNIQUE owner
    // ============================================================

    public function test_profilo_piattaforma_owner_null_si_crea(): void
    {
        $p = BrandProfile::create(['base_theme' => 'glitch']);

        $this->assertNull($p->owner_type);
        $this->assertNull($p->owner_id);
        $this->assertDatabaseHas('brand_profiles', ['id' => $p->id, 'base_theme' => 'glitch']);
    }

    public function test_un_solo_profilo_piattaforma_nulls_not_distinct(): void
    {
        BrandProfile::create(['base_theme' => 'glitch']);

        $this->expectException(QueryException::class);
        BrandProfile::create(['base_theme' => 'moderno']); // secondo owner NULL → vietato
    }

    public function test_un_solo_profilo_per_scuola_unique(): void
    {
        $school = $this->makeSchool();
        BrandProfile::create(['owner_type' => School::class, 'owner_id' => $school->id, 'base_theme' => 'glitch']);

        $this->expectException(QueryException::class);
        BrandProfile::create(['owner_type' => School::class, 'owner_id' => $school->id, 'base_theme' => 'caldo']);
    }

    public function test_base_theme_fuori_catalogo_respinto_dal_check(): void
    {
        $this->expectException(QueryException::class);
        // bypass del cast enum per colpire il CHECK a livello DB
        DB::table('brand_profiles')->insert(['base_theme' => 'fucsia']);
    }

    public function test_accent_color_non_esadecimale_respinto_dal_check(): void
    {
        $this->expectException(QueryException::class);
        DB::table('brand_profiles')->insert(['base_theme' => 'glitch', 'accent_color' => 'NOPE99']);
    }

    // ============================================================
    // forSchool / forPlatform
    // ============================================================

    public function test_for_school_con_profilo_ritorna_quel_profilo(): void
    {
        $school = $this->makeSchool();
        $p = BrandProfile::create(['owner_type' => School::class, 'owner_id' => $school->id, 'base_theme' => 'moderno']);

        $resolved = BrandProfile::forSchool($school);

        $this->assertTrue($resolved->exists);
        $this->assertSame($p->id, $resolved->id);
        $this->assertSame(BaseTheme::Moderno, $resolved->base_theme);
    }

    public function test_for_school_senza_profilo_ritorna_default_glitch(): void
    {
        $school = $this->makeSchool();

        $resolved = BrandProfile::forSchool($school);

        $this->assertFalse($resolved->exists, 'Il default non è persistito.');
        $this->assertSame(BaseTheme::Glitch, $resolved->base_theme);
    }

    public function test_for_platform_senza_riga_ritorna_glitch(): void
    {
        $p = BrandProfile::forPlatform();

        $this->assertFalse($p->exists);
        $this->assertSame(BaseTheme::Glitch, $p->base_theme);
    }

    // ============================================================
    // resolvedTheme: override + ereditarietà
    // ============================================================

    public function test_resolved_accent_override_applicato(): void
    {
        $p = BrandProfile::make(['base_theme' => 'glitch', 'accent_color' => '123456']);

        $this->assertSame('123456', $p->resolvedTheme()->accent);
    }

    public function test_resolved_accent_nullo_eredita_dal_tema_base(): void
    {
        $p = BrandProfile::make(['base_theme' => 'caldo']); // accent_color null

        $this->assertSame(BaseTheme::Caldo->defaultAccent(), $p->resolvedTheme()->accent);
    }

    public function test_resolved_font_override_applicato_altrimenti_base(): void
    {
        $override = BrandProfile::make(['base_theme' => 'glitch', 'font_choice' => 'sans_moderno']);
        $this->assertEquals(FontPair::named('sans_moderno'), $override->resolvedTheme()->fonts);

        $base = BrandProfile::make(['base_theme' => 'glitch']); // font_choice null
        $this->assertEquals(BaseTheme::Glitch->fonts(), $base->resolvedTheme()->fonts);
    }

    public function test_resolved_logo_eredita_da_settings_scuola_se_vuoto(): void
    {
        $school = $this->makeSchool(['settings' => ['logo_path' => 'school-logos/abc/logo.png']]);
        BrandProfile::create(['owner_type' => School::class, 'owner_id' => $school->id, 'base_theme' => 'classico']);

        // logo_path del profilo è NULL → eredita da schools.settings
        $this->assertSame('school-logos/abc/logo.png', BrandProfile::forSchool($school)->resolvedTheme()->logoPath);
    }

    public function test_resolved_logo_override_vince_su_settings(): void
    {
        $school = $this->makeSchool(['settings' => ['logo_path' => 'school-logos/abc/logo.png']]);
        BrandProfile::create([
            'owner_type' => School::class, 'owner_id' => $school->id,
            'base_theme' => 'classico', 'logo_path' => 'school-logos/abc/slide-logo.png',
        ]);

        $this->assertSame('school-logos/abc/slide-logo.png', BrandProfile::forSchool($school)->resolvedTheme()->logoPath);
    }

    public function test_default_glitch_resolve_palette_e_font_glitch(): void
    {
        $resolved = BrandProfile::forPlatform()->resolvedTheme();

        $this->assertSame('A6192E', $resolved->accent);   // cremisi
        $this->assertSame('0A0A0A', $resolved->ink);       // nero
        $this->assertSame('F4F1EA', $resolved->background); // avorio
        $this->assertSame('Playfair Display', $resolved->fonts->titlePrimary);
        $this->assertNull($resolved->logoPath);
    }

    // ============================================================
    // ContrastGuard
    // ============================================================

    public function test_contrast_accento_leggibile_ok(): void
    {
        $guard = new ContrastGuard();

        $this->assertTrue($guard->isAccentReadable('A6192E', BaseTheme::Glitch)); // cremisi su avorio
    }

    public function test_contrast_accento_illeggibile_flaggato(): void
    {
        $guard = new ContrastGuard();

        $this->assertFalse($guard->isAccentReadable('FFD700', BaseTheme::Glitch)); // oro su avorio
        $report = $guard->inspect('FFD700', BaseTheme::Glitch);
        $this->assertFalse($report['readable']);
        $this->assertLessThan(4.5, $report['ratio']);
    }

    public function test_ogni_tema_curato_ha_accento_default_leggibile(): void
    {
        $guard = new ContrastGuard();

        foreach (BaseTheme::cases() as $theme) {
            $this->assertTrue(
                $guard->isAccentReadable($theme->defaultAccent(), $theme),
                "Il tema {$theme->value} ha un accento di default illeggibile sul proprio sfondo."
            );
        }
    }
}
