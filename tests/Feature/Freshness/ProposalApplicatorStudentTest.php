<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\Material;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentSourceVersion;
use App\Models\UpdateProposal;
use App\Services\Freshness\ProposalApplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P25.B-a.3 — applicazione verbatim su modules.content (in-place), versioning/rollback
 * studente, gate minori, e BARRIERA CANVAS (materials e student_canvas_data intatti;
 * solo UPDATE, mai DELETE di moduli).
 */
class ProposalApplicatorStudentTest extends TestCase
{
    use RefreshDatabase;

    private const BEFORE = 'Il dato è 1,8 miliardi nel 2025';
    private const AFTER = 'Il dato è 2,3 miliardi nel 2026';

    private function course(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function module(Course $course, ?string $html = null): Module
    {
        return Module::create([
            'course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0,
            'content' => $html ?? ('<p>' . self::BEFORE . ', con una crescita del 58%.</p>'),
        ]);
    }

    private function approvedStudent(Course $course, Module $m, array $attrs = []): UpdateProposal
    {
        return UpdateProposal::create(array_merge([
            'course_id' => $course->id, 'content_source' => 'student', 'module_id' => $m->id,
            'before' => self::BEFORE, 'after' => self::AFTER, 'audience' => 'adult',
            'status' => 'approved', 'reviewed_at' => now(),
        ], $attrs));
    }

    public function test_apply_studente_inplace_versione_e_changelog(): void
    {
        $course = $this->course();
        $m = $this->module($course);
        $this->approvedStudent($course, $m);

        $res = app(ProposalApplicator::class)->applyStudent($course);

        $this->assertSame(1, $res['applied']);
        $this->assertSame('1.0', $res['version_from']);
        $this->assertSame('1.1', $res['version_to']);

        // modules.content aggiornato IN-PLACE (coda '58%' preservata).
        $m->refresh();
        $this->assertStringContainsString(self::AFTER, $m->content);
        $this->assertStringContainsString('crescita del 58%', $m->content);
        $this->assertStringNotContainsString(self::BEFORE, $m->content);

        // Versioni: 1.0 (pre, intatta col before) + 1.1 (post).
        $v10 = StudentSourceVersion::where('course_id', $course->id)->where('version', '1.0')->first();
        $this->assertStringContainsString(self::BEFORE, $v10->content[0]['content_html']);
        $this->assertNotNull(StudentSourceVersion::where('course_id', $course->id)->where('version', '1.1')->first());

        // Changelog studente + proposta applied.
        $this->assertDatabaseHas('course_changelog', [
            'course_id' => $course->id, 'content_source' => 'student',
            'version_from' => '1.0', 'version_to' => '1.1', 'kind' => 'apply',
        ]);
        $this->assertSame('applied', UpdateProposal::where('course_id', $course->id)->first()->status);
    }

    public function test_before_non_trovato_fallisce_pulito(): void
    {
        $course = $this->course();
        $m = $this->module($course, '<p>Testo riformulato che non contiene il before.</p>');
        $p = $this->approvedStudent($course, $m);

        $res = app(ProposalApplicator::class)->applyStudent($course);

        $this->assertSame(0, $res['applied']);
        $this->assertNull($res['version_to']);
        $this->assertStringContainsString('studente', $p->refresh()->apply_error);
        $this->assertSame('approved', $p->status);
        $this->assertSame('<p>Testo riformulato che non contiene il before.</p>', $m->refresh()->content);
        $this->assertSame(0, StudentSourceVersion::where('course_id', $course->id)->count());
    }

    public function test_before_non_unico_fallisce_pulito(): void
    {
        $course = $this->course();
        $m = $this->module($course, '<p>' . self::BEFORE . '.</p><p>Di nuovo: ' . self::BEFORE . '.</p>');
        $p = $this->approvedStudent($course, $m);

        $res = app(ProposalApplicator::class)->applyStudent($course);

        $this->assertSame(0, $res['applied']);
        $this->assertStringContainsString('non univoco', $p->refresh()->apply_error);
    }

    public function test_rollback_studente_ripristina_inplace(): void
    {
        $course = $this->course();
        $m = $this->module($course);
        $this->approvedStudent($course, $m);

        app(ProposalApplicator::class)->applyStudent($course);
        $this->assertStringContainsString(self::AFTER, $m->refresh()->content);

        $res = app(ProposalApplicator::class)->rollbackStudent($course);

        $this->assertTrue($res['rolled_back']);
        $this->assertSame('1.1', $res['version_from']);
        $this->assertSame('1.2', $res['version_to']);
        $this->assertSame('1.0', $res['restored_to']);
        // modules.content ripristinato in-place.
        $this->assertStringContainsString(self::BEFORE, $m->refresh()->content);
        $this->assertStringNotContainsString(self::AFTER, $m->content);
    }

    public function test_gate_minori_studente_blocca_senza_conferma(): void
    {
        $course = $this->course();
        CourseFreshnessConfig::create(['course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [], 'audience' => 'minor']);
        $m = $this->module($course);
        $this->approvedStudent($course, $m, ['audience' => 'minor']);

        $blocked = app(ProposalApplicator::class)->applyStudent($course); // senza conferma
        $this->assertSame('minor_confirmation_required', $blocked['blocked']);
        $this->assertStringContainsString(self::BEFORE, $m->refresh()->content); // intatto

        $ok = app(ProposalApplicator::class)->applyStudent($course, minorConfirmed: true);
        $this->assertSame(1, $ok['applied']);
        $this->assertStringContainsString(self::AFTER, $m->refresh()->content);
    }

    public function test_BARRIERA_CANVAS_materials_e_canvas_intatti_solo_update(): void
    {
        $course = $this->course();
        $m = $this->module($course);
        $this->approvedStudent($course, $m);

        // Canvas: material legato al modulo + dato discente legato al material.
        $material = Material::create(['course_id' => $course->id, 'module_id' => $m->id, 'title' => 'Canvas 1', 'is_instructor_only' => false]);
        $student = Student::create(['name' => 'Discente', 'email' => 's+' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true]);
        $canvasId = (string) Str::uuid();
        DB::table('student_canvas_data')->insert([
            'id' => $canvasId, 'student_id' => $student->id, 'material_id' => $material->id,
            'data' => json_encode(['risposta' => 'dato compilato dal discente']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $matBefore = Material::count();
        $matTitleBefore = $material->fresh()->title;
        $canvasBefore = DB::table('student_canvas_data')->where('id', $canvasId)->value('data');
        $moduleIdsBefore = Module::where('course_id', $course->id)->pluck('id')->sort()->values()->all();

        app(ProposalApplicator::class)->applyStudent($course);

        // materials INTATTI (conteggio + contenuto).
        $this->assertSame($matBefore, Material::count());
        $this->assertSame($matTitleBefore, $material->fresh()->title);
        $this->assertNotNull($material->fresh()); // non cancellato

        // student_canvas_data INTATTO (conteggio + dato del discente).
        $this->assertSame(1, DB::table('student_canvas_data')->count());
        $this->assertSame($canvasBefore, DB::table('student_canvas_data')->where('id', $canvasId)->value('data'));

        // Moduli: SOLO update, mai delete (stessi id, stesso conteggio).
        $moduleIdsAfter = Module::where('course_id', $course->id)->pluck('id')->sort()->values()->all();
        $this->assertSame($moduleIdsBefore, $moduleIdsAfter);
        $this->assertStringContainsString(self::AFTER, $m->fresh()->content); // il modulo è stato aggiornato
    }

    public function test_hitl_solo_approved_pending_mai_applicata(): void
    {
        $course = $this->course();
        $m = $this->module($course);
        $this->approvedStudent($course, $m, ['status' => 'pending']); // NON approvata

        $res = app(ProposalApplicator::class)->applyStudent($course);

        $this->assertSame(0, $res['applied']);
        $this->assertStringContainsString(self::BEFORE, $m->refresh()->content); // intatto
        $this->assertSame(0, StudentSourceVersion::where('course_id', $course->id)->count());
    }
}
