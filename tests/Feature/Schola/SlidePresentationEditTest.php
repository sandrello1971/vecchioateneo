<?php

namespace Tests\Feature\Schola;

use App\Enums\BaseTheme;
use App\Jobs\GenerateLessonPresentationJob;
use App\Jobs\GenerateModulePresentationJob;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Schola\LessonPresentationService;
use App\Support\Branding\FontPair;
use App\Support\Branding\ResolvedTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * S2 — correzione delle slide via prompt. Il cuore è la GARANZIA di modifica
 * mirata: il merge avviene in PHP per numero di slide, quindi solo la slide
 * indicata cambia (copertina, tema, ordine e count restano identici). Testabile
 * deterministicamente perché l'LLM è fakeato e la garanzia è strutturale.
 */
class SlidePresentationEditTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LessonPresentationService
    {
        return new LessonPresentationService();
    }

    private function glitchTheme(): ResolvedTheme
    {
        $p = BaseTheme::Glitch->palette();

        return new ResolvedTheme(BaseTheme::Glitch, $p['ink'], $p['background'], $p['accent'], FontPair::named('editoriale'), null);
    }

    /** Spec realistica: copertina + 3 slide di contenuto. */
    private function sampleSpec(): array
    {
        $content = $this->service()->normalizeSlides([
            ['layout' => 'bullets_clean', 'title' => 'Uno', 'bullets' => ['a', 'b']],
            ['layout' => 'bullets_clean', 'title' => 'Due', 'bullets' => ['c', 'd']],
            ['layout' => 'stat', 'value' => '3×', 'label' => 'meglio'],
        ]);

        return $this->service()->buildSpec('Titolo', 'Sub', 'Scuola', $this->glitchTheme(), $content);
    }

    /** Fake Claude: ritorna un edit della SOLA slide 3 (indice 2). */
    private function fakeEditLlm(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode(['edits' => [
                ['slide_number' => 3, 'slide' => ['layout' => 'bullets_clean', 'title' => 'MODIFICATA', 'bullets' => ['nuovo punto']]],
            ]])]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 12],
        ], 200)]);
    }

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
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'T', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause',
            'position' => 0, 'generation_status' => 'ready', 'content' => '## C']);
    }

    private function makeModule(): Module
    {
        $course = Course::create(['name' => 'Corso', 'slug' => 'c-' . Str::lower(Str::random(8)), 'is_active' => true]);

        return Module::create(['course_id' => $course->id, 'title' => 'M1', 'content' => '## x', 'sort_order' => 0, 'is_active' => true]);
    }

    // ===== GARANZIA: modifica mirata =====

    public function test_edit_cambia_solo_la_slide_indicata(): void
    {
        Storage::fake('local');
        $this->fakeEditLlm();

        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $spec = $this->sampleSpec();
        $pres = LessonPresentation::create([
            'lesson_id' => $lesson->id, 'status' => 'ready',
            'file_path' => "lesson-presentations/{$lesson->id}/pres.pptx",
            'generation_meta' => ['slides' => 4], 'spec' => $spec,
        ]);

        $result = $this->service()->editSpec($pres, 'Nella slide 3 metti un solo punto');
        $new = $result['spec'];

        // count e struttura invariati
        $this->assertCount(4, $new['slides']);
        $this->assertSame($spec['theme'], $new['theme'], 'il tema non deve cambiare');

        // SOLO la slide 3 (indice 2) cambia
        $this->assertSame('MODIFICATA', $new['slides'][2]['title']);
        $this->assertSame(['nuovo punto'], $new['slides'][2]['bullets']);

        // tutte le altre slide IDENTICHE (copertina inclusa)
        $this->assertSame($spec['slides'][0], $new['slides'][0], 'copertina invariata');
        $this->assertSame($spec['slides'][1], $new['slides'][1], 'slide 2 invariata');
        $this->assertSame($spec['slides'][3], $new['slides'][3], 'slide 4 invariata');

        // pptx ri-renderizzato + meta della correzione
        Storage::disk('local')->assertExists($result['file_path']);
        $this->assertSame('pptx-edit-v1', $result['meta']['prompt_version']);
        $this->assertSame([3], $result['meta']['edited_slides']);
    }

    public function test_edit_invalida_la_cache_anteprima(): void
    {
        Storage::fake('local');
        $this->fakeEditLlm();

        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pptx = "lesson-presentations/{$lesson->id}/pres.pptx";
        // PNG di anteprima già in cache (S1)
        Storage::disk('local')->put("lesson-presentations/{$lesson->id}/pres/slide_1.png", 'OLD');

        $pres = LessonPresentation::create([
            'lesson_id' => $lesson->id, 'status' => 'ready', 'file_path' => $pptx,
            'generation_meta' => ['slides' => 4], 'spec' => $this->sampleSpec(),
        ]);

        $this->service()->editSpec($pres, 'cambia la slide 3');

        Storage::disk('local')->assertMissing("lesson-presentations/{$lesson->id}/pres/slide_1.png");
    }

    public function test_edit_senza_spec_lancia_eccezione(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready',
            'file_path' => 'x/y.pptx', 'spec' => null]);

        $this->expectException(RuntimeException::class);
        $this->service()->editSpec($pres, 'qualcosa');
    }

    // ===== Job: con istruzione → editSpec =====

    public function test_job_con_istruzione_usa_editspec_e_persiste(): void
    {
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'generating',
            'file_path' => 'lesson-presentations/x/pres.pptx', 'spec' => ['slides' => [['layout' => 'cover']]]]);

        $this->mock(LessonPresentationService::class, function ($m) {
            $m->shouldReceive('editSpec')->once()->andReturn([
                'file_path' => 'lesson-presentations/x/pres.pptx',
                'meta' => ['slides' => 4, 'prompt_version' => 'pptx-edit-v1'],
                'spec' => ['slides' => [['layout' => 'cover'], ['layout' => 'bullets_clean', 'title' => 'NEW']]],
            ]);
            $m->shouldNotReceive('build');
        });

        (new GenerateLessonPresentationJob($pres->id, 'modifica la slide 2'))->handle(app(LessonPresentationService::class));
        $pres->refresh();

        $this->assertSame('ready', $pres->status);
        $this->assertSame('NEW', $pres->spec['slides'][1]['title']);
        $this->assertSame('pptx-edit-v1', $pres->generation_meta['prompt_version']);
    }

    // ===== Controller: lezioni =====

    public function test_docente_edit_dispatcha_con_istruzione(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready',
            'file_path' => 'x/y.pptx', 'spec' => $this->sampleSpec()]);

        $this->asProf($prof)->post(route('docente.lessons.presentation.edit', $lesson), ['instruction' => 'Nella slide 3 aggiungi X'])
            ->assertRedirect(route('docente.lessons.show', $lesson));

        Bus::assertDispatchedAfterResponse(GenerateLessonPresentationJob::class,
            fn (GenerateLessonPresentationJob $j) => $j->instruction === 'Nella slide 3 aggiungi X');
    }

    public function test_docente_edit_422_senza_spec(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'file_path' => 'x/y.pptx', 'spec' => null]);

        $this->asProf($prof)->post(route('docente.lessons.presentation.edit', $lesson), ['instruction' => 'qualcosa'])
            ->assertStatus(422);
        Bus::assertNotDispatchedAfterResponse(GenerateLessonPresentationJob::class);
    }

    public function test_docente_edit_istruzione_vuota_respinta(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'file_path' => 'x/y.pptx', 'spec' => $this->sampleSpec()]);

        $this->asProf($prof)->post(route('docente.lessons.presentation.edit', $lesson), ['instruction' => ''])
            ->assertSessionHasErrors('instruction');
    }

    public function test_docente_edit_ownership(): void
    {
        Bus::fake();
        $owner = $this->prof();
        $lesson = $this->lesson($owner);
        LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'file_path' => 'x/y.pptx', 'spec' => $this->sampleSpec()]);

        $this->asProf($this->prof())->post(route('docente.lessons.presentation.edit', $lesson), ['instruction' => 'x'])
            ->assertForbidden();
    }

    // ===== Controller: moduli =====

    public function test_admin_edit_dispatcha_con_istruzione(): void
    {
        Bus::fake();
        $module = $this->makeModule();
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'file_path' => 'x/y.pptx', 'spec' => $this->sampleSpec()]);

        $this->asAdmin()->post(route('admin.courses.modules.presentation.edit', [$module->course, $module]), ['instruction' => 'cambia slide 2'])
            ->assertRedirect();
        Bus::assertDispatchedAfterResponse(GenerateModulePresentationJob::class,
            fn (GenerateModulePresentationJob $j) => $j->instruction === 'cambia slide 2');
    }

    public function test_admin_edit_422_senza_spec(): void
    {
        Bus::fake();
        $module = $this->makeModule();
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'file_path' => 'x/y.pptx', 'spec' => null]);

        $this->asAdmin()->post(route('admin.courses.modules.presentation.edit', [$module->course, $module]), ['instruction' => 'x'])
            ->assertStatus(422);
    }
}
