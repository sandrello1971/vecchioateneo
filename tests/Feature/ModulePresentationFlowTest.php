<?php

namespace Tests\Feature;

use App\Jobs\GenerateModulePresentationJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Services\Schola\LessonPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P28 Fase 2 — controller + job + UI della presentazione modulo (corsi Officina).
 * Pattern async gemello di Schola; auth admin; additivo.
 */
class ModulePresentationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeModule(?string $content = "## Intro\n\nTesto."): Module
    {
        $course = Course::create(['name' => 'Corso', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);

        return Module::create([
            'course_id' => $course->id, 'title' => 'Modulo 1',
            'content' => $content, 'sort_order' => 0, 'is_active' => true,
        ]);
    }

    private function asAdmin(): self
    {
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);

        return $this;
    }

    private function genRoute(Module $m, string $verb = 'generate'): string
    {
        return route("admin.courses.modules.presentation.{$verb}", [$m->course, $m]);
    }

    // ---- controller: generate ----

    public function test_generate_crea_e_dispatcha(): void
    {
        Bus::fake();
        $module = $this->makeModule();

        $this->asAdmin()->from(route('admin.courses.modules.edit', [$module->course, $module]))
            ->post($this->genRoute($module))
            ->assertRedirect();

        $mp = ModulePresentation::where('module_id', $module->id)->first();
        $this->assertNotNull($mp);
        $this->assertSame('generating', $mp->status);
        Bus::assertDispatchedAfterResponse(GenerateModulePresentationJob::class);
    }

    public function test_generate_anti_doppio_submit(): void
    {
        Bus::fake();
        $module = $this->makeModule();
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'generating']);

        $this->asAdmin()->from(route('admin.courses.modules.edit', [$module->course, $module]))
            ->post($this->genRoute($module))->assertRedirect();

        Bus::assertNotDispatchedAfterResponse(GenerateModulePresentationJob::class);
    }

    public function test_generate_richiede_contenuto(): void
    {
        $module = $this->makeModule(content: '');

        $this->asAdmin()->post($this->genRoute($module))->assertStatus(422);
        $this->assertSame(0, ModulePresentation::count());
    }

    public function test_regenerate_dispatcha(): void
    {
        Bus::fake();
        $module = $this->makeModule();
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'file_path' => 'x.pptx']);

        $this->asAdmin()->from(route('admin.courses.modules.edit', [$module->course, $module]))
            ->post($this->genRoute($module, 'regenerate'))->assertRedirect();

        $this->assertSame('generating', ModulePresentation::where('module_id', $module->id)->first()->status);
        Bus::assertDispatchedAfterResponse(GenerateModulePresentationJob::class);
    }

    // ---- job: buildForModule → ready / failed ----

    public function test_job_costruisce_via_service_ready(): void
    {
        $module = $this->makeModule();
        $mp = ModulePresentation::create(['module_id' => $module->id, 'status' => 'pending']);

        $mock = \Mockery::mock(LessonPresentationService::class);
        $mock->shouldReceive('buildForModule')->once()->andReturn([
            'file_path' => "module-presentations/{$module->id}/{$mp->id}.pptx",
            'meta' => ['slides' => 3, 'filename' => 'modulo-1.pptx'],
        ]);

        (new GenerateModulePresentationJob($mp->id))->handle($mock);

        $mp->refresh();
        $this->assertSame('ready', $mp->status);
        $this->assertSame("module-presentations/{$module->id}/{$mp->id}.pptx", $mp->file_path);
        $this->assertSame(3, $mp->generation_meta['slides']);
    }

    public function test_job_fallito_registra_reason(): void
    {
        $module = $this->makeModule();
        $mp = ModulePresentation::create(['module_id' => $module->id, 'status' => 'pending']);

        $mock = \Mockery::mock(LessonPresentationService::class);
        $mock->shouldReceive('buildForModule')->andThrow(new \RuntimeException('render boom'));

        (new GenerateModulePresentationJob($mp->id))->handle($mock);

        $mp->refresh();
        $this->assertSame('failed', $mp->status);
        $this->assertStringContainsString('render boom', $mp->generation_meta['failure_reason']);
    }

    // ---- download ----

    public function test_download_quando_ready(): void
    {
        Storage::fake('local');
        $module = $this->makeModule();
        $mp = ModulePresentation::create([
            'module_id' => $module->id, 'status' => 'ready',
            'file_path' => "module-presentations/{$module->id}/p.pptx",
            'generation_meta' => ['filename' => 'modulo-1.pptx'],
        ]);
        Storage::disk('local')->put($mp->file_path, 'PPTX');

        $this->asAdmin()->get($this->genRoute($module, 'download'))
            ->assertOk()->assertDownload('modulo-1.pptx');
    }

    public function test_download_solo_se_ready(): void
    {
        $module = $this->makeModule();
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'pending']);

        $this->asAdmin()->get($this->genRoute($module, 'download'))->assertNotFound();
    }

    // ---- scoping / auth ----

    public function test_non_admin_bloccato(): void
    {
        $module = $this->makeModule();

        $this->post($this->genRoute($module))->assertRedirect('/admin/login');
    }

    // ---- UI ----

    public function test_edit_mostra_pannello_e_stato_genera(): void
    {
        $module = $this->makeModule();

        $this->asAdmin()->get(route('admin.courses.modules.edit', [$module->course, $module]))
            ->assertOk()
            ->assertSee('Presentazione (.pptx)')
            ->assertSee('Genera presentazione');
    }

    public function test_edit_mostra_scarica_e_rigenera_se_ready(): void
    {
        $module = $this->makeModule();
        ModulePresentation::create([
            'module_id' => $module->id, 'status' => 'ready',
            'file_path' => "module-presentations/{$module->id}/p.pptx",
            'generation_meta' => ['slides' => 4],
        ]);

        $this->asAdmin()->get(route('admin.courses.modules.edit', [$module->course, $module]))
            ->assertOk()
            ->assertSee('Scarica .pptx')
            ->assertSee('Rigenera');
    }

    public function test_modulo_non_nel_corso_404(): void
    {
        $module = $this->makeModule();
        $altroCorso = Course::create(['name' => 'Altro', 'slug' => 'altro-' . Str::lower(Str::random(8)), 'is_active' => true]);

        $this->asAdmin()
            ->post(route('admin.courses.modules.presentation.generate', [$altroCorso, $module]))
            ->assertNotFound();
    }
}
