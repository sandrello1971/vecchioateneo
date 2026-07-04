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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * S3 — upload / cancella / sostituisci + campo source. Il render per il conteggio
 * slide è in try/catch (file fake → 0), quindi i test non dipendono da LibreOffice.
 */
class SlidePresentationUploadTest extends TestCase
{
    use RefreshDatabase;

    private const PPTX_MIME = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

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

    private function pptx(string $name = 'deck.pptx', int $kb = 200): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, self::PPTX_MIME);
    }

    // ===== Upload lezioni =====

    public function test_upload_crea_presentazione_uploaded(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);

        $this->asProf($prof)->post(route('docente.lessons.presentation.upload', $lesson), ['presentation' => $this->pptx()])
            ->assertRedirect(route('docente.lessons.show', $lesson));

        $pres = LessonPresentation::where('lesson_id', $lesson->id)->firstOrFail();
        $this->assertSame('uploaded', $pres->source);
        $this->assertSame('ready', $pres->status);
        $this->assertNull($pres->spec, 'una presentazione caricata non ha spec (niente correzione via prompt)');
        $this->assertSame('deck.pptx', $pres->generation_meta['original_filename']);
        Storage::disk('local')->assertExists($pres->file_path);
    }

    public function test_upload_sostituisce_e_invalida_anteprima(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        // presentazione generata preesistente + PNG di anteprima in cache
        $old = "lesson-presentations/{$lesson->id}/pres.pptx";
        Storage::disk('local')->put($old, 'OLD');
        Storage::disk('local')->put("lesson-presentations/{$lesson->id}/pres/slide_1.png", 'PNG');
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'source' => 'generated',
            'file_path' => $old, 'spec' => ['slides' => [['layout' => 'cover']]], 'generation_meta' => ['slides' => 1]]);

        $this->asProf($prof)->post(route('docente.lessons.presentation.upload', $lesson), ['presentation' => $this->pptx()])
            ->assertRedirect();

        $pres->refresh();
        $this->assertSame('uploaded', $pres->source);
        $this->assertNull($pres->spec);
        Storage::disk('local')->assertMissing("lesson-presentations/{$lesson->id}/pres/slide_1.png");
    }

    public function test_upload_estensione_sbagliata_respinta(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);

        $this->asProf($prof)->post(route('docente.lessons.presentation.upload', $lesson),
            ['presentation' => UploadedFile::fake()->create('note.txt', 50, 'text/plain')])
            ->assertSessionHasErrors('presentation');
        $this->assertSame(0, LessonPresentation::count());
    }

    public function test_upload_troppo_grande_respinto(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);

        $this->asProf($prof)->post(route('docente.lessons.presentation.upload', $lesson),
            ['presentation' => $this->pptx('big.pptx', 60000)]) // 60 MB > 50
            ->assertSessionHasErrors('presentation');
    }

    public function test_upload_ownership(): void
    {
        Storage::fake('local');
        $owner = $this->prof();
        $lesson = $this->lesson($owner);

        $this->asProf($this->prof())->post(route('docente.lessons.presentation.upload', $lesson), ['presentation' => $this->pptx()])
            ->assertForbidden();
    }

    // ===== Delete lezioni =====

    public function test_destroy_rimuove_record_file_e_cache(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $path = "lesson-presentations/{$lesson->id}/pres.pptx";
        Storage::disk('local')->put($path, 'PPTX');
        Storage::disk('local')->put("lesson-presentations/{$lesson->id}/pres/slide_1.png", 'PNG');
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'file_path' => $path, 'generation_meta' => ['slides' => 1]]);

        $this->asProf($prof)->delete(route('docente.lessons.presentation.destroy', $lesson))->assertRedirect();

        $this->assertDatabaseMissing('lesson_presentations', ['id' => $pres->id]);
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertMissing("lesson-presentations/{$lesson->id}/pres/slide_1.png");
    }

    public function test_destroy_404_se_assente(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);

        $this->asProf($prof)->delete(route('docente.lessons.presentation.destroy', $lesson))->assertNotFound();
    }

    // ===== Moduli: rispetta UNIQUE(module_id) =====

    public function test_upload_modulo_sostituisce_unico_record(): void
    {
        Storage::fake('local');
        $module = $this->makeModule();
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'source' => 'generated',
            'file_path' => "module-presentations/{$module->id}/pres.pptx", 'spec' => ['slides' => [['layout' => 'cover']]]]);

        $this->asAdmin()->post(route('admin.courses.modules.presentation.upload', [$module->course, $module]), ['presentation' => $this->pptx()])
            ->assertRedirect();

        $this->assertSame(1, ModulePresentation::where('module_id', $module->id)->count(), 'UNIQUE(module_id): resta un solo record');
        $this->assertSame('uploaded', ModulePresentation::where('module_id', $module->id)->first()->source);
    }

    public function test_destroy_modulo(): void
    {
        Storage::fake('local');
        $module = $this->makeModule();
        $path = "module-presentations/{$module->id}/pres.pptx";
        Storage::disk('local')->put($path, 'PPTX');
        $pres = ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'file_path' => $path]);

        $this->asAdmin()->delete(route('admin.courses.modules.presentation.destroy', [$module->course, $module]))->assertRedirect();

        $this->assertDatabaseMissing('module_presentations', ['id' => $pres->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
