<?php

namespace Tests\Feature\Corsi;

use App\Models\Course;
use App\Models\CourseConceptMap;
use App\Models\Module;
use App\Services\ConceptMapGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ModuleConceptMapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@e.it']);
    }

    private function make(): array
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $module = Module::create([
            'course_id' => $course->id, 'title' => 'M', 'sort_order' => 0,
            'content' => '<p>Testo del modulo con alcuni concetti chiave.</p>', 'is_active' => true,
        ]);

        return [$course, $module];
    }

    private function fakeGraph(): array
    {
        return [
            'nodes' => [['id' => 'n1', 'label' => 'Concetto A'], ['id' => 'n2', 'label' => 'Concetto B']],
            'edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'si collega a']],
            'physics' => ['enabled' => true],
        ];
    }

    public function test_generate_crea_mappa_concettuale_in_bozza(): void
    {
        [$course, $module] = $this->make();
        $this->mock(ConceptMapGenerationService::class, function (MockInterface $m) {
            $m->shouldReceive('generate')->once()->andReturn($this->fakeGraph());
        });

        $this->admin()
            ->post(route('admin.courses.modules.conceptmap.generate', [$course, $module]))
            ->assertRedirect();

        $map = CourseConceptMap::where('module_id', $module->id)->first();
        $this->assertNotNull($map);
        $this->assertTrue($map->ai_generated);
        $this->assertSame('draft', $map->visibility);          // resta in bozza
        $this->assertCount(2, $map->data['nodes']);
        $this->assertCount(1, $map->data['edges']);
        $this->assertNotEmpty($map->content_hash);
    }

    public function test_publish_e_unpublish(): void
    {
        [$course, $module] = $this->make();
        $map = $course->conceptMaps()->create([
            'module_id' => $module->id, 'title' => 'Mappa', 'visibility' => 'draft',
            'ai_generated' => true, 'data' => $this->fakeGraph(), 'sort_order' => 0,
        ]);

        $this->admin()->patch(route('admin.courses.modules.conceptmap.visibility', [$course, $module]), ['visibility' => 'published'])->assertRedirect();
        $this->assertSame('published', $map->fresh()->visibility);

        $this->admin()->patch(route('admin.courses.modules.conceptmap.visibility', [$course, $module]), ['visibility' => 'draft'])->assertRedirect();
        $this->assertSame('draft', $map->fresh()->visibility);
    }

    public function test_destroy_elimina_la_mappa(): void
    {
        [$course, $module] = $this->make();
        $course->conceptMaps()->create([
            'module_id' => $module->id, 'title' => 'Mappa', 'visibility' => 'draft',
            'ai_generated' => true, 'data' => $this->fakeGraph(), 'sort_order' => 0,
        ]);

        $this->admin()->delete(route('admin.courses.modules.conceptmap.destroy', [$course, $module]))->assertRedirect();
        $this->assertNull(CourseConceptMap::where('module_id', $module->id)->first());
    }

    public function test_studente_vede_solo_la_mappa_pubblicata(): void
    {
        [$course, $module] = $this->make();
        $map = $course->conceptMaps()->create([
            'module_id' => $module->id, 'title' => 'Mappa', 'visibility' => 'draft',
            'ai_generated' => true, 'data' => $this->fakeGraph(), 'sort_order' => 0,
        ]);

        // bozza → non visibile
        $this->assertNull($course->conceptMaps()->published()->where('module_id', $module->id)->first());
        // pubblicata → visibile
        $map->update(['visibility' => 'published']);
        $this->assertNotNull($course->conceptMaps()->published()->where('module_id', $module->id)->first());
    }
}
