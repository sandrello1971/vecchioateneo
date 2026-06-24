<?php

namespace Tests\Feature\Schola;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Schola\LessonPresentationService;
use App\Services\Schola\SlidePreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * S1 — anteprima slide (render PPTX→PNG, lazy + cache, servita da controller).
 * I test del controller mockano SlidePreviewService (niente LibreOffice → CI-safe);
 * uno smoke test reale gira solo se soffice è presente.
 */
class SlidePreviewTest extends TestCase
{
    use RefreshDatabase;

    // ===== helper auth/dominio =====

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function asAdmin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);
    }

    private function lesson(Student $prof): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'Rivoluzione', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause',
            'position' => 0, 'generation_status' => 'ready', 'content' => '## Crisi']);
    }

    /** Presentazione ready con .pptx + un PNG di slide già in cache sul disco fake. */
    private function readyLessonPres(Lesson $lesson, int $slides = 1): array
    {
        $pptx = "lesson-presentations/{$lesson->id}/pres.pptx";
        $png = "lesson-presentations/{$lesson->id}/pres/slide_1.png";
        Storage::disk('local')->put($pptx, 'PPTXBYTES');
        Storage::disk('local')->put($png, 'PNGBYTES');
        LessonPresentation::create(['lesson_id' => $lesson->id, 'file_path' => $pptx,
            'status' => 'ready', 'generation_meta' => ['slides' => $slides]]);

        return [$pptx, $png];
    }

    private function makeModule(string $content = "## Intro\n\nUno.\n\n## Sviluppo\n\nDue."): Module
    {
        $course = Course::create(['name' => 'Corso AI', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);

        return Module::create(['course_id' => $course->id, 'title' => 'Modulo 1', 'content' => $content, 'sort_order' => 0, 'is_active' => true]);
    }

    private function fakeLlm(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode(['slides' => [
                ['layout' => 'bullets_clean', 'title' => 'Intro', 'bullets' => ['A', 'B']],
                ['layout' => 'stat', 'value' => '3×', 'label' => 'meglio'],
            ]])]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ], 200)]);
    }

    // ===== controller: lezioni =====

    public function test_owner_vede_la_slide_png(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        [, $png] = $this->readyLessonPres($lesson);

        $this->mock(SlidePreviewService::class, fn ($m) => $m->shouldReceive('imagesFor')->once()->andReturn([$png]));

        $this->asProf($prof)->get(route('docente.lessons.presentation.preview', [$lesson, 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_intruso_403(): void
    {
        Storage::fake('local');
        $owner = $this->prof();
        $lesson = $this->lesson($owner);
        $this->readyLessonPres($lesson);

        $this->asProf($this->prof())->get(route('docente.lessons.presentation.preview', [$lesson, 1]))
            ->assertForbidden();
    }

    public function test_non_ready_404(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'pending']);

        $this->asProf($prof)->get(route('docente.lessons.presentation.preview', [$lesson, 1]))
            ->assertNotFound();
    }

    public function test_slide_fuori_range_404(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        [, $png] = $this->readyLessonPres($lesson);

        $this->mock(SlidePreviewService::class, fn ($m) => $m->shouldReceive('imagesFor')->andReturn([$png])); // 1 sola slide

        $this->asProf($prof)->get(route('docente.lessons.presentation.preview', [$lesson, 5]))
            ->assertNotFound();
    }

    // ===== controller: moduli =====

    public function test_admin_vede_la_slide_modulo(): void
    {
        Storage::fake('local');
        $module = $this->makeModule();
        $pptx = "module-presentations/{$module->id}/pres.pptx";
        $png = "module-presentations/{$module->id}/pres/slide_1.png";
        Storage::disk('local')->put($pptx, 'PPTX');
        Storage::disk('local')->put($png, 'PNG');
        ModulePresentation::create(['module_id' => $module->id, 'file_path' => $pptx,
            'status' => 'ready', 'generation_meta' => ['slides' => 1]]);

        $this->mock(SlidePreviewService::class, fn ($m) => $m->shouldReceive('imagesFor')->once()->andReturn([$png]));

        $this->asAdmin()->get(route('admin.courses.modules.presentation.preview', [$module->course, $module, 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_modulo_non_nel_corso_404(): void
    {
        Storage::fake('local');
        $module = $this->makeModule();
        $altroCorso = Course::create(['name' => 'Altro', 'slug' => 'altro-' . Str::lower(Str::random(6)), 'is_active' => true]);

        $this->asAdmin()->get(route('admin.courses.modules.presentation.preview', [$altroCorso, $module, 1]))
            ->assertNotFound();
    }

    // ===== smoke test render reale (skip se soffice assente) =====

    public function test_render_reale_produce_png_e_cache(): void
    {
        if (!(new ExecutableFinder())->find('soffice')) {
            $this->markTestSkipped('LibreOffice (soffice) non disponibile in questo ambiente.');
        }

        Storage::fake('local');
        $this->fakeLlm();

        $module = $this->makeModule();
        $mp = ModulePresentation::create(['module_id' => $module->id, 'status' => 'generating']);
        $result = (new LessonPresentationService())->buildForModule($mp); // crea il .pptx reale

        $preview = new SlidePreviewService();
        $images = $preview->imagesFor($result['file_path']);

        $this->assertCount(3, $images, 'cover + 2 slide → 3 PNG'); // coerente con meta.slides
        foreach ($images as $p) {
            Storage::disk('local')->assertExists($p);
            $this->assertMatchesRegularExpression('/slide_\d+\.png$/', $p);
        }

        // Secondo accesso: cache → stessi path, nessun errore/ri-render rotto.
        $this->assertSame($images, $preview->imagesFor($result['file_path']));
    }
}
