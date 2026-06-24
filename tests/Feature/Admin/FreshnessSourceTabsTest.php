<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseSource;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Models\StudentSourceVersion;
use App\Models\UpdateProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P25.B-a (wiring UI) — coda a due tab (Formatore/Studente) + apply/rollback PER-SORGENTE.
 * Additivo: il flusso formatore resta invariato; le due sorgenti non si mescolano mai.
 */
class FreshnessSourceTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // il percorso formatore rigenera un PDF
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it']);
    }

    private function course(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    /** Corso con sorgente formatore + sezione live + modulo studente + 2 proposte approved. */
    private function fullCourse(): array
    {
        $course = $this->course();
        CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [
            ['id' => 'b1', 'type' => 'P', 'text' => 'DATO FORMATORE 2025 nel manuale'],
        ]]);
        $mat = Material::create(['course_id' => $course->id, 'title' => 'Manuale', 'is_instructor_only' => true]);
        $sec = InstructorManualSection::create(['material_id' => $mat->id, 'course_id' => $course->id, 'title' => 'S',
            'anchor' => 'a-' . uniqid(), 'heading_level' => 2, 'sort_order' => 0, 'content_html' => '<p>DATO FORMATORE 2025 nel manuale.</p>']);
        $mod = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>DATO STUDENTE 2025 nel modulo.</p>']);

        $instr = UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'instructor', 'block_id' => 'b1',
            'before' => 'DATO FORMATORE 2025 nel manuale', 'after' => 'DATO FORMATORE 2026 nel manuale', 'audience' => 'adult', 'status' => 'approved', 'reviewed_at' => now()]);
        $stud = UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'student', 'module_id' => $mod->id,
            'before' => 'DATO STUDENTE 2025 nel modulo', 'after' => 'DATO STUDENTE 2026 nel modulo', 'audience' => 'adult', 'status' => 'approved', 'reviewed_at' => now()]);

        return compact('course', 'sec', 'mod', 'instr', 'stud');
    }

    public function test_tab_mostra_solo_la_propria_sorgente(): void
    {
        $course = $this->course();
        UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'instructor', 'block_id' => 'b1',
            'before' => 'PENDING FORMATORE XYZ', 'after' => 'x', 'audience' => 'adult', 'status' => 'pending']);
        UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'student', 'module_id' => null,
            'before' => 'PENDING STUDENTE XYZ', 'after' => 'y', 'audience' => 'adult', 'status' => 'pending']);

        $this->actingAdmin()->get(route('admin.freshness.proposals.index', ['source' => 'instructor']))
            ->assertOk()->assertSee('PENDING FORMATORE XYZ')->assertDontSee('PENDING STUDENTE XYZ');

        $this->actingAdmin()->get(route('admin.freshness.proposals.index', ['source' => 'student']))
            ->assertOk()->assertSee('PENDING STUDENTE XYZ')->assertDontSee('PENDING FORMATORE XYZ');
    }

    public function test_apply_studente_instrada_a_applyStudent_e_non_tocca_formatore(): void
    {
        ['course' => $course, 'sec' => $sec, 'mod' => $mod, 'instr' => $instr] = $this->fullCourse();

        $this->actingAdmin()->post(route('admin.freshness.proposals.apply', $course), ['content_source' => 'student'])
            ->assertRedirect();

        // Studente applicato in-place; formatore intatto.
        $this->assertStringContainsString('DATO STUDENTE 2026', $mod->refresh()->content);
        $this->assertSame('approved', $instr->refresh()->status); // proposta formatore NON toccata
        $this->assertStringContainsString('DATO FORMATORE 2025', $sec->refresh()->content_html); // live formatore intatto
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count()); // niente nuova versione formatore
        $this->assertSame(1, StudentSourceVersion::where('course_id', $course->id)->where('version', '1.1')->count());
    }

    public function test_apply_formatore_instrada_al_flusso_esistente_e_non_tocca_studente(): void
    {
        ['course' => $course, 'sec' => $sec, 'mod' => $mod, 'stud' => $stud] = $this->fullCourse();

        $this->actingAdmin()->post(route('admin.freshness.proposals.apply', $course), ['content_source' => 'instructor'])
            ->assertRedirect();

        // Formatore applicato; studente intatto.
        $this->assertStringContainsString('DATO FORMATORE 2026', $sec->refresh()->content_html);
        $this->assertSame('2.1', CourseSource::where('course_id', $course->id)->orderByDesc('created_at')->orderByDesc('id')->first()->version);
        $this->assertSame('approved', $stud->refresh()->status); // proposta studente NON toccata
        $this->assertStringContainsString('DATO STUDENTE 2025', $mod->refresh()->content); // modulo studente intatto
        $this->assertSame(0, StudentSourceVersion::where('course_id', $course->id)->count());
    }

    public function test_rollback_per_sorgente_studente(): void
    {
        ['course' => $course, 'mod' => $mod] = $this->fullCourse();

        $this->actingAdmin()->post(route('admin.freshness.proposals.apply', $course), ['content_source' => 'student']);
        $this->assertStringContainsString('DATO STUDENTE 2026', $mod->refresh()->content);

        $this->actingAdmin()->post(route('admin.freshness.proposals.rollback', $course), ['content_source' => 'student'])
            ->assertRedirect();

        $this->assertStringContainsString('DATO STUDENTE 2025', $mod->refresh()->content); // ripristinato
    }

    public function test_gate_minori_su_tab_studente(): void
    {
        ['course' => $course, 'mod' => $mod] = $this->fullCourse();
        \App\Models\CourseFreshnessConfig::create(['course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [], 'audience' => 'minor']);

        // Senza conferma → bloccato, modulo intatto.
        $this->actingAdmin()->post(route('admin.freshness.proposals.apply', $course), ['content_source' => 'student'])
            ->assertRedirect();
        $this->assertStringContainsString('DATO STUDENTE 2025', $mod->refresh()->content);

        // Con conferma → applicato.
        $this->actingAdmin()->post(route('admin.freshness.proposals.apply', $course), ['content_source' => 'student', 'confirm_minor' => '1'])
            ->assertRedirect();
        $this->assertStringContainsString('DATO STUDENTE 2026', $mod->refresh()->content);
    }
}
