<?php

namespace Tests\Feature;

use App\Jobs\GenerateCourseDocumentJob;
use App\Jobs\GenerateModuleDocumentJob;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Module;
use App\Models\ModuleDocument;
use App\Models\ModulePresentation;
use App\Services\CourseDocumentService;
use App\Services\ModuleDocumentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * P29 Fase 2 — documento PDF dell'INTERO corso: hash aggregato (modulo
 * modificato/aggiunto/rimosso/riordinato) + schema course_documents + service +
 * controller/job (modulo e corso) + UI badge stale. Additivo: non tocca P28/mindmap.
 */
class CourseDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(): Course
    {
        return Course::create([
            'name' => 'Fondamenti AI',
            'slug' => 'corso-' . Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
    }

    private function addModule(Course $course, string $title, ?string $content, int $sort): Module
    {
        return Module::create([
            'course_id' => $course->id,
            'title' => $title,
            'content' => $content,
            'sort_order' => $sort,
            'is_active' => true,
        ]);
    }

    private function makeCourseWithModules(): Course
    {
        $course = $this->makeCourse();
        $this->addModule($course, 'Modulo 1', '<h2>Intro</h2><p>Primo.</p>', 0);
        $this->addModule($course, 'Modulo 2', '<h2>Sviluppo</h2><p>Secondo.</p>', 1);

        return $course;
    }

    private function courseService(): CourseDocumentService
    {
        return app(CourseDocumentService::class);
    }

    private function asAdmin(): self
    {
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);

        return $this;
    }

    // ============================================================
    // Hash aggregato corso — i 4 casi di cambiamento
    // ============================================================

    public function test_hash_aggregato_cambia_se_un_modulo_cambia_contenuto(): void
    {
        $course = $this->makeCourseWithModules();
        $before = $course->currentContentHash();

        $course->modules()->where('sort_order', 0)->first()->update(['content' => '<h2>Intro rivista</h2>']);

        $this->assertNotSame($before, $course->fresh()->currentContentHash());
    }

    public function test_hash_aggregato_cambia_se_si_aggiunge_un_modulo(): void
    {
        $course = $this->makeCourseWithModules();
        $before = $course->currentContentHash();

        $this->addModule($course, 'Modulo 3', '<h2>Extra</h2>', 2);

        $this->assertNotSame($before, $course->fresh()->currentContentHash());
    }

    public function test_hash_aggregato_cambia_se_si_rimuove_un_modulo(): void
    {
        $course = $this->makeCourseWithModules();
        $before = $course->currentContentHash();

        $course->modules()->where('sort_order', 1)->first()->delete();

        $this->assertNotSame($before, $course->fresh()->currentContentHash());
    }

    public function test_hash_aggregato_cambia_se_si_riordinano_i_moduli(): void
    {
        $course = $this->makeCourseWithModules();
        $before = $course->currentContentHash();

        // Scambia sort_order dei due moduli (stesso contenuto, solo ordine diverso).
        $m0 = $course->modules()->where('sort_order', 0)->first();
        $m1 = $course->modules()->where('sort_order', 1)->first();
        $m0->update(['sort_order' => 1]);
        $m1->update(['sort_order' => 0]);

        $this->assertNotSame($before, $course->fresh()->currentContentHash());
    }

    public function test_hash_aggregato_stabile_se_nulla_cambia(): void
    {
        $course = $this->makeCourseWithModules();
        $this->assertSame($course->currentContentHash(), $course->fresh()->currentContentHash());
    }

    // ============================================================
    // Schema course_documents
    // ============================================================

    public function test_course_document_default_pending_e_unico_per_corso(): void
    {
        $course = $this->makeCourse();
        $cd = CourseDocument::create(['course_id' => $course->id]);
        $this->assertSame('pending', $cd->fresh()->status);
        $this->assertNull($cd->fresh()->content_hash);

        $this->expectException(QueryException::class);
        CourseDocument::create(['course_id' => $course->id]);
    }

    public function test_course_document_status_fuori_enum_respinto(): void
    {
        $course = $this->makeCourse();
        $this->expectException(QueryException::class);
        \Illuminate\Support\Facades\DB::table('course_documents')->insert([
            'id' => Str::uuid(), 'course_id' => $course->id, 'status' => 'bogus',
        ]);
    }

    // ============================================================
    // buildDocumentForCourse
    // ============================================================

    public function test_build_document_corso_concatena_moduli_ordinati_e_salva_hash(): void
    {
        Storage::fake('local');
        $course = $this->makeCourseWithModules();
        $cd = CourseDocument::create(['course_id' => $course->id]);

        $result = $this->courseService()->buildDocumentForCourse($cd);

        $this->assertSame("course-documents/{$course->id}/{$cd->id}.pdf", $result->file_path);
        Storage::disk('local')->assertExists($result->file_path);
        $this->assertSame('ready', $result->status);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($result->file_path));

        // content_hash = hash AGGREGATO del corso + meta moduli
        $this->assertSame($course->currentContentHash(), $result->content_hash);
        $this->assertSame(2, $result->generation_meta['modules']);
    }

    public function test_build_corso_senza_moduli_con_contenuto_errore_pulito(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $this->addModule($course, 'Vuoto', '', 0); // modulo senza contenuto
        $cd = CourseDocument::create(['course_id' => $course->id]);

        try {
            $this->courseService()->buildDocumentForCourse($cd);
            $this->fail('Atteso RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('moduli con contenuto', $e->getMessage());
        }

        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertSame('pending', $cd->fresh()->status);
    }

    // ============================================================
    // isStale (corso)
    // ============================================================

    public function test_is_stale_corso_falso_dopo_generazione_vero_dopo_modifica(): void
    {
        Storage::fake('local');
        $course = $this->makeCourseWithModules();
        $cd = CourseDocument::create(['course_id' => $course->id]);
        $this->courseService()->buildDocumentForCourse($cd);

        $this->assertFalse($cd->fresh()->isStale());

        // Aggiunta di un modulo → hash aggregato diverso → stale.
        $this->addModule($course, 'Modulo 3', '<h2>Nuovo</h2>', 2);
        $this->assertTrue($cd->fresh()->isStale());
    }

    // ============================================================
    // Job corso → ready / failed
    // ============================================================

    public function test_job_corso_failed_registra_reason(): void
    {
        $course = $this->makeCourse();
        $cd = CourseDocument::create(['course_id' => $course->id, 'status' => 'generating']);

        $mock = \Mockery::mock(CourseDocumentService::class);
        $mock->shouldReceive('buildDocumentForCourse')->andThrow(new RuntimeException('boom corso'));

        (new GenerateCourseDocumentJob($cd->id))->handle($mock);

        $this->assertSame('failed', $cd->fresh()->status);
        $this->assertStringContainsString('boom corso', $cd->fresh()->generation_meta['failure_reason']);
    }

    // ============================================================
    // Controller corso: generate / regenerate / status / download / guard / auth
    // ============================================================

    public function test_controller_corso_generate_dispatcha(): void
    {
        Bus::fake();
        $course = $this->makeCourseWithModules();

        $this->asAdmin()->from(route('admin.courses.modules.index', $course))
            ->post(route('admin.courses.document.generate', $course))->assertRedirect();

        $this->assertSame('generating', CourseDocument::where('course_id', $course->id)->first()->status);
        Bus::assertDispatchedAfterResponse(GenerateCourseDocumentJob::class);
    }

    public function test_controller_corso_anti_doppio_submit(): void
    {
        Bus::fake();
        $course = $this->makeCourseWithModules();
        CourseDocument::create(['course_id' => $course->id, 'status' => 'generating']);

        $this->asAdmin()->from(route('admin.courses.modules.index', $course))
            ->post(route('admin.courses.document.generate', $course))->assertRedirect();

        Bus::assertNotDispatchedAfterResponse(GenerateCourseDocumentJob::class);
    }

    public function test_controller_corso_guard_nessun_contenuto_422(): void
    {
        $course = $this->makeCourse();
        $this->addModule($course, 'Vuoto', '', 0);

        $this->asAdmin()->post(route('admin.courses.document.generate', $course))->assertStatus(422);
        $this->assertSame(0, CourseDocument::count());
    }

    public function test_controller_corso_status_json(): void
    {
        $course = $this->makeCourseWithModules();
        CourseDocument::create(['course_id' => $course->id, 'status' => 'generating']);

        $this->asAdmin()->getJson(route('admin.courses.document.status', $course))
            ->assertOk()->assertJson(['status' => 'generating']);
    }

    public function test_controller_corso_download_quando_ready(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $cd = CourseDocument::create([
            'course_id' => $course->id, 'status' => 'ready',
            'file_path' => "course-documents/{$course->id}/d.pdf",
            'generation_meta' => ['filename' => 'fondamenti-ai.pdf'],
        ]);
        Storage::disk('local')->put($cd->file_path, '%PDF-1.7');

        $this->asAdmin()->get(route('admin.courses.document.download', $course))
            ->assertOk()->assertDownload('fondamenti-ai.pdf');
    }

    public function test_controller_corso_download_solo_se_ready(): void
    {
        $course = $this->makeCourse();
        CourseDocument::create(['course_id' => $course->id, 'status' => 'pending']);

        $this->asAdmin()->get(route('admin.courses.document.download', $course))->assertNotFound();
    }

    public function test_controller_corso_non_admin_bloccato(): void
    {
        $course = $this->makeCourseWithModules();
        $this->post(route('admin.courses.document.generate', $course))->assertRedirect('/admin/login');
    }

    // ============================================================
    // Controller modulo (P29 Fase 1 esposta in Fase 2)
    // ============================================================

    public function test_controller_modulo_generate_dispatcha(): void
    {
        Bus::fake();
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Modulo 1', '<h2>X</h2><p>Y</p>', 0);

        $this->asAdmin()->from(route('admin.courses.modules.edit', [$course, $module]))
            ->post(route('admin.courses.modules.document.generate', [$course, $module]))->assertRedirect();

        $this->assertSame('generating', ModuleDocument::where('module_id', $module->id)->first()->status);
        Bus::assertDispatchedAfterResponse(GenerateModuleDocumentJob::class);
    }

    public function test_controller_modulo_guard_content_vuoto_422(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Modulo 1', '', 0);

        $this->asAdmin()->post(route('admin.courses.modules.document.generate', [$course, $module]))->assertStatus(422);
        $this->assertSame(0, ModuleDocument::count());
    }

    public function test_controller_modulo_non_nel_corso_404(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Modulo 1', '<h2>X</h2>', 0);
        $altro = $this->makeCourse();

        $this->asAdmin()
            ->post(route('admin.courses.modules.document.generate', [$altro, $module]))
            ->assertNotFound();
    }

    public function test_job_modulo_failed_registra_reason(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Modulo 1', '<h2>X</h2>', 0);
        $md = ModuleDocument::create(['module_id' => $module->id, 'status' => 'generating']);

        $mock = \Mockery::mock(ModuleDocumentService::class);
        $mock->shouldReceive('buildDocumentForModule')->andThrow(new RuntimeException('boom modulo'));

        (new GenerateModuleDocumentJob($md->id))->handle($mock);

        $this->assertSame('failed', $md->fresh()->status);
        $this->assertStringContainsString('boom modulo', $md->fresh()->generation_meta['failure_reason']);
    }

    // ============================================================
    // UI — badge stale (modulo e corso)
    // ============================================================

    public function test_ui_modulo_badge_aggiornato_e_obsoleto(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Modulo 1', '<h2>Intro</h2><p>Testo.</p>', 0);
        $md = ModuleDocument::create(['module_id' => $module->id]);
        app(ModuleDocumentService::class)->buildDocumentForModule($md);

        // Subito dopo: AGGIORNATO
        $this->asAdmin()->get(route('admin.courses.modules.edit', [$course, $module]))
            ->assertOk()->assertSee('Documento PDF')->assertSee('AGGIORNATO');

        // Dopo modifica del content: OBSOLETO
        $module->update(['content' => '<h2>Cambiato</h2>']);
        $this->asAdmin()->get(route('admin.courses.modules.edit', [$course, $module]))
            ->assertOk()->assertSee('OBSOLETO');
    }

    public function test_ui_corso_pannello_e_badge_obsoleto(): void
    {
        Storage::fake('local');
        $course = $this->makeCourseWithModules();
        $cd = CourseDocument::create(['course_id' => $course->id]);
        $this->courseService()->buildDocumentForCourse($cd);

        $this->asAdmin()->get(route('admin.courses.modules.index', $course))
            ->assertOk()->assertSee('Documento del corso')->assertSee('AGGIORNATO');

        // Aggiunta modulo → OBSOLETO
        $this->addModule($course, 'Modulo 3', '<h2>Nuovo</h2>', 2);
        $this->asAdmin()->get(route('admin.courses.modules.index', $course))
            ->assertOk()->assertSee('OBSOLETO');
    }

    // ============================================================
    // Isolamento
    // ============================================================

    public function test_isolamento_non_tocca_presentations_ne_mindmap(): void
    {
        Storage::fake('local');
        $course = $this->makeCourseWithModules();
        $cd = CourseDocument::create(['course_id' => $course->id]);
        $this->courseService()->buildDocumentForCourse($cd);

        $this->assertSame(0, ModulePresentation::count());
        // mindmap del modulo intatta (nessun mindmap_markdown popolato)
        $this->assertNull($course->modules()->first()->mindmap_markdown);
    }
}
