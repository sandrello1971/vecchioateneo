<?php

namespace Tests\Feature\Schola;

use App\Jobs\GenerateModulePresentationJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModulePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blocco B / B2 — bi-versione MODULI lato admin (mirror del Blocco A lezioni):
 * publish promuove la bozza ed elimina la vecchia pubblicata; correggere clona la
 * pubblicata in una bozza senza toccarla; unpublish ritira.
 */
class ModulePresentationPublishTest extends TestCase
{
    use RefreshDatabase;

    private function module(): Module
    {
        $course = Course::create(['name' => 'Corso', 'slug' => 'c-' . Str::lower(Str::random(8)), 'is_active' => true]);

        return Module::create(['course_id' => $course->id, 'title' => 'M1', 'content' => '## x', 'sort_order' => 0, 'is_active' => true]);
    }

    private function asAdmin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);
    }

    private function makePresentation(Module $module, ?string $publishedAt, bool $withCachePng = false): ModulePresentation
    {
        $pres = ModulePresentation::create([
            'module_id' => $module->id, 'status' => 'ready', 'source' => 'generated',
            'spec' => ['theme' => [], 'slides' => [['layout' => 'cover']]],
            'generation_meta' => ['filename' => 'm1.pptx', 'slides' => 1],
            'published_at' => $publishedAt,
        ]);
        $path = "module-presentations/{$module->id}/{$pres->id}.pptx";
        Storage::disk('local')->put($path, 'PPTX-' . $pres->id);
        if ($withCachePng) {
            Storage::disk('local')->put("module-presentations/{$module->id}/{$pres->id}/slide_1.png", 'PNG');
        }
        $pres->update(['file_path' => $path]);

        return $pres->refresh();
    }

    public function test_publish_promuove_bozza_ed_elimina_vecchia(): void
    {
        Storage::fake('local');
        $module = $this->module();
        $old = $this->makePresentation($module, publishedAt: now(), withCachePng: true);
        $draft = $this->makePresentation($module, publishedAt: null);

        $this->asAdmin()->post(route('admin.courses.modules.presentation.publish', [$module->course, $module]))->assertRedirect();

        $this->assertSame(1, $module->presentations()->count());
        $this->assertTrue($module->presentations()->whereNotNull('published_at')->first()->is($draft->refresh()));
        $this->assertDatabaseMissing('module_presentations', ['id' => $old->id]);
        Storage::disk('local')->assertMissing($old->file_path);
        Storage::disk('local')->assertMissing("module-presentations/{$module->id}/{$old->id}/slide_1.png");
    }

    public function test_correzione_clona_senza_toccare_la_pubblicata(): void
    {
        Bus::fake();
        Storage::fake('local');
        $module = $this->module();
        $published = $this->makePresentation($module, publishedAt: now());

        $this->asAdmin()->post(route('admin.courses.modules.presentation.edit', [$module->course, $module]), ['instruction' => 'cambia slide 1'])
            ->assertRedirect();

        $this->assertSame(1, $module->presentations()->whereNull('published_at')->count(), 'è nata una bozza');
        $this->assertSame(1, $module->presentations()->whereNotNull('published_at')->count(), 'resta 1 sola pubblicata');
        $this->assertTrue($module->presentations()->whereNotNull('published_at')->first()->is($published));
        Bus::assertDispatchedAfterResponse(GenerateModulePresentationJob::class);
    }

    public function test_unpublish_ritira(): void
    {
        Storage::fake('local');
        $module = $this->module();
        $this->makePresentation($module, publishedAt: now());

        $this->asAdmin()->post(route('admin.courses.modules.presentation.unpublish', [$module->course, $module]))->assertRedirect();

        $this->assertSame(0, $module->presentations()->whereNotNull('published_at')->count());
    }
}
