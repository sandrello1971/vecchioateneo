<?php

namespace Tests\Feature\Schola;

use App\Enums\BaseTheme;
use App\Models\BrandProfile;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P27 Fase 2 — UI segreteria del brand profile in /scuola/anagrafica + contrast-guard.
 * Solo configurazione: il tema non viene ancora applicato alle slide (Fase 3).
 */
class SchoolBrandProfileUiTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(array $attrs = []): School
    {
        return School::create(array_merge([
            'name' => 'Liceo Galilei',
            'slug' => 'liceo-' . Str::lower(Str::random(8)),
            'type' => 'liceo',
        ], $attrs));
    }

    private function makeSecretary(School $school): Student
    {
        return Student::create([
            'name' => 'Segreteria',
            'email' => 'sa' . uniqid() . '@ente.it',
            'password' => bcrypt('x'),
            'role' => null,
            'is_secretary' => true,
            'school_id' => $school->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function loginAs(Student $secretary): self
    {
        $this->withSession(['student_id' => $secretary->id]);

        return $this;
    }

    /** Payload anagrafica minimo valido + override del tema. */
    private function payload(array $brand = []): array
    {
        return array_merge([
            'name' => 'Liceo Galilei',
            'type' => 'liceo',
            'base_theme' => 'glitch',
        ], $brand);
    }

    // ============================================================

    public function test_segreteria_salva_tema_accento_font(): void
    {
        $school = $this->makeSchool();
        $sec = $this->makeSecretary($school);

        $this->loginAs($sec)
            ->patch(route('scuola.anagrafica.update'), $this->payload([
                'base_theme' => 'moderno',
                'accent_color' => '#1d4ed8', // leggibile su bianco; con '#' e minuscolo
                'font_choice' => 'sans_moderno',
            ]))
            ->assertRedirect(route('scuola.anagrafica.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brand_profiles', [
            'owner_type' => School::class,
            'owner_id' => $school->id,
            'base_theme' => 'moderno',
            'accent_color' => '1D4ED8', // normalizzato: senza '#', maiuscolo
            'font_choice' => 'sans_moderno',
        ]);
    }

    public function test_aggiornamento_non_duplica_il_profilo(): void
    {
        $school = $this->makeSchool();
        $sec = $this->makeSecretary($school);

        $this->loginAs($sec)->patch(route('scuola.anagrafica.update'), $this->payload(['base_theme' => 'glitch']));
        $this->loginAs($sec)->patch(route('scuola.anagrafica.update'), $this->payload(['base_theme' => 'caldo']));

        $this->assertSame(1, BrandProfile::where('owner_id', $school->id)->count());
        $this->assertSame(BaseTheme::Caldo, BrandProfile::forSchool($school)->base_theme);
    }

    public function test_accento_illeggibile_blocca_e_non_salva(): void
    {
        $school = $this->makeSchool();
        $sec = $this->makeSecretary($school);

        $this->loginAs($sec)
            ->patch(route('scuola.anagrafica.update'), $this->payload([
                'base_theme' => 'glitch',
                'accent_color' => 'FFD700', // oro su avorio → illeggibile
            ]))
            ->assertSessionHasErrors('accent_color');

        $this->assertDatabaseMissing('brand_profiles', ['owner_id' => $school->id]);
    }

    public function test_accento_leggibile_salvato(): void
    {
        $school = $this->makeSchool();
        $sec = $this->makeSecretary($school);

        $this->loginAs($sec)
            ->patch(route('scuola.anagrafica.update'), $this->payload([
                'base_theme' => 'glitch',
                'accent_color' => 'A6192E', // cremisi su avorio → leggibile
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brand_profiles', [
            'owner_id' => $school->id,
            'accent_color' => 'A6192E',
        ]);
    }

    public function test_logo_ereditato_da_settings_nessun_upload_separato(): void
    {
        $school = $this->makeSchool(['settings' => ['logo_path' => 'school-logos/x/logo.png']]);
        $sec = $this->makeSecretary($school);

        $this->loginAs($sec)
            ->patch(route('scuola.anagrafica.update'), $this->payload(['base_theme' => 'classico']))
            ->assertSessionHasNoErrors();

        // Il profilo non ha un logo proprio: resta NULL ed eredita da settings.
        $this->assertDatabaseHas('brand_profiles', [
            'owner_id' => $school->id,
            'logo_path' => null,
        ]);
        $this->assertSame(
            'school-logos/x/logo.png',
            BrandProfile::forSchool($school->fresh())->resolvedTheme()->logoPath
        );
    }

    public function test_scoped_segreteria_tocca_solo_la_propria_scuola(): void
    {
        $schoolA = $this->makeSchool(['name' => 'Liceo A']);
        $schoolB = $this->makeSchool(['name' => 'Liceo B']);
        $secA = $this->makeSecretary($schoolA);

        $this->loginAs($secA)
            ->patch(route('scuola.anagrafica.update'), $this->payload(['base_theme' => 'moderno']))
            ->assertRedirect();

        // Il profilo nasce per la scuola della segreteria, mai per un'altra.
        $this->assertDatabaseHas('brand_profiles', ['owner_id' => $schoolA->id]);
        $this->assertDatabaseMissing('brand_profiles', ['owner_id' => $schoolB->id]);
    }

    public function test_pagina_edit_mostra_sezione_tema(): void
    {
        $school = $this->makeSchool();
        $sec = $this->makeSecretary($school);

        $res = $this->loginAs($sec)->get(route('scuola.anagrafica.edit'));

        $res->assertOk()
            ->assertSee('Tema presentazioni')
            ->assertSee('GLITCH')        // tema di default
            ->assertSee('Anteprima')
            ->assertSee('Accento (override)');
    }

    public function test_non_segreteria_riceve_403(): void
    {
        $school = $this->makeSchool();
        $intruder = Student::create([
            'name' => 'Studente', 'email' => 'st' . uniqid() . '@ente.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_secretary' => false, 'school_id' => $school->id,
            'is_active' => true, 'must_change_password' => false,
        ]);

        $this->loginAs($intruder)
            ->patch(route('scuola.anagrafica.update'), $this->payload())
            ->assertForbidden();
    }
}
