<?php

namespace Tests\Feature\Schola;

use App\Jobs\GenerateLessonPresentationJob;
use App\Models\ClassStudent;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Schola\LessonPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

// Fase 3 (P21) — presentazione .pptx della lezione: generazione (mock), failed,
// download gated (docente owner / studente della classe / 403 altrui), rigenerazione,
// storage privato.
class LessonPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolClass(Student $teacher): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => '3A',
            'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $class, Student $s, string $status = 'active'): void
    {
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null]);
    }

    private function lesson(Student $prof, string $status = 'ready'): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'Rivoluzione', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause',
            'position' => 0, 'generation_status' => $status, 'content' => $status === 'ready' ? '## Crisi\n\nTesto.' : null]);
    }

    private function publishLesson(Lesson $lesson, SchoolClass $class): LessonPublication
    {
        return LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);
    }

    private function readyPresentation(Lesson $lesson): LessonPresentation
    {
        $path = "lesson-presentations/{$lesson->id}/pres.pptx";
        Storage::disk('local')->put($path, 'PPTXBYTES');

        return LessonPresentation::create(['lesson_id' => $lesson->id, 'file_path' => $path,
            'status' => 'ready', 'generation_meta' => ['filename' => 'le-cause.pptx', 'slides' => 4]]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    // ===== Generazione =====

    public function test_generate_creates_presentation_and_dispatches(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);

        $this->asProf($prof)->post(route('docente.lessons.presentation.generate', $lesson))
            ->assertRedirect(route('docente.lessons.show', $lesson));

        $pres = LessonPresentation::where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($pres);
        $this->assertSame('generating', $pres->status);
        Bus::assertDispatchedAfterResponse(GenerateLessonPresentationJob::class);
    }

    public function test_generate_anti_double_submit(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'generating']);

        $this->asProf($prof)->post(route('docente.lessons.presentation.generate', $lesson))->assertRedirect();
        Bus::assertNotDispatchedAfterResponse(GenerateLessonPresentationJob::class);
    }

    public function test_generate_requires_ready_lesson(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof, status: 'draft');

        $this->asProf($prof)->post(route('docente.lessons.presentation.generate', $lesson))->assertStatus(422);
        $this->assertSame(0, LessonPresentation::count());
    }

    public function test_generate_ownership(): void
    {
        Bus::fake();
        $owner = $this->prof();
        $intruder = $this->prof();
        $lesson = $this->lesson($owner);

        $this->asProf($intruder)->post(route('docente.lessons.presentation.generate', $lesson))->assertForbidden();
    }

    public function test_job_builds_presentation_via_service(): void
    {
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'generating']);

        $this->mock(LessonPresentationService::class, function ($m) {
            $m->shouldReceive('build')->once()->andReturn([
                'file_path' => 'lesson-presentations/x/pres.pptx',
                'meta' => ['model' => 'claude-sonnet-4-5', 'slides' => 5, 'filename' => 'le-cause.pptx'],
                'spec' => ['theme' => ['ink' => '0A0A0A'], 'slides' => [['layout' => 'cover', 'title' => 'T']]],
            ]);
        });

        (new GenerateLessonPresentationJob($pres->id))->handle(app(LessonPresentationService::class));
        $pres->refresh();

        $this->assertSame('ready', $pres->status);
        $this->assertSame('lesson-presentations/x/pres.pptx', $pres->file_path);
        $this->assertSame(5, $pres->generation_meta['slides']);
        // S0: la spec completa viene persistita (abilita la correzione via prompt).
        $this->assertSame('cover', $pres->spec['slides'][0]['layout']);
    }

    public function test_job_failed_records_reason(): void
    {
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'generating']);

        $this->mock(LessonPresentationService::class, function ($m) {
            $m->shouldReceive('build')->andThrow(new \RuntimeException('render boom'));
        });

        (new GenerateLessonPresentationJob($pres->id))->handle(app(LessonPresentationService::class));
        $pres->refresh();

        $this->assertSame('failed', $pres->status);
        $this->assertStringContainsString('render boom', $pres->generation_meta['failure_reason']);
    }

    public function test_regenerate_dispatches(): void
    {
        Bus::fake();
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $this->readyPresentation($lesson);

        $this->asProf($prof)->post(route('docente.lessons.presentation.regenerate', $lesson))->assertRedirect();
        $this->assertSame('generating', LessonPresentation::where('lesson_id', $lesson->id)->first()->status);
        Bus::assertDispatchedAfterResponse(GenerateLessonPresentationJob::class);
    }

    // ===== Download gated =====

    public function test_owner_downloads(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $this->readyPresentation($lesson);

        $this->asProf($prof)->get(route('docente.lessons.presentation.download', $lesson))
            ->assertOk()->assertDownload('le-cause.pptx');
    }

    public function test_other_teacher_cannot_download(): void
    {
        Storage::fake('local');
        $owner = $this->prof();
        $intruder = $this->prof();
        $lesson = $this->lesson($owner);
        $this->readyPresentation($lesson);

        $this->asProf($intruder)->get(route('docente.lessons.presentation.download', $lesson))->assertForbidden();
    }

    public function test_download_only_when_ready(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'generating']);

        $this->asProf($prof)->get(route('docente.lessons.presentation.download', $lesson))->assertNotFound();
    }

    public function test_student_of_class_downloads(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $this->publishLesson($lesson, $class);
        // Bi-versione: lo studente vede solo la presentazione PUBBLICATA.
        $this->readyPresentation($lesson)->update(['published_at' => now()]);

        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$class, $lesson]))
            ->assertOk()->assertDownload('le-cause.pptx');
    }

    public function test_student_of_other_class_cannot_download(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $classA = $this->schoolClass($prof);
        $classB = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($classB, $student); // iscritto a B
        $lesson = $this->lesson($prof);
        $this->publishLesson($lesson, $classA); // pubblicata in A
        $this->readyPresentation($lesson);

        // Via classe B (sua) ma lezione non pubblicata lì → 403.
        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$classB, $lesson]))->assertForbidden();
        // Via classe A (non sua) → 403.
        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$classA, $lesson]))->assertForbidden();
    }

    public function test_student_cannot_generate_presentation(): void
    {
        // Non esiste alcuna rotta studente di generazione: solo download.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('student.classes.lesson.presentation.generate'));
    }

    // Render reale via python-pptx (solo se il venv è disponibile: saltato in CI).
    public function test_render_pptx_produces_real_file(): void
    {
        $python = config('services.pptx.python');
        if (!is_string($python) || !is_file($python)) {
            $this->markTestSkipped('python-pptx non disponibile in questo ambiente.');
        }

        $out = sys_get_temp_dir() . '/p21_' . uniqid() . '.pptx';
        app(LessonPresentationService::class)->renderPptx([
            'title' => 'Le cause', 'subtitle' => 'Rivoluzione · Storia', 'school' => 'Liceo', 'accent' => '55B1AE',
            'slides' => [
                ['title' => 'Crisi', 'bullets' => ['Bancarotta', 'Tasse']],
                ['title' => 'Società', 'bullets' => ['Tre stati']],
            ],
        ], $out);

        $this->assertFileExists($out);
        $this->assertGreaterThan(1000, filesize($out)); // un .pptx reale (zip OOXML)
        @unlink($out);
    }

    public function test_presentation_stored_in_private_disk(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = $this->readyPresentation($lesson);

        // Il path è sul disco PRIVATO 'local', sotto lesson-presentations/, mai 'public'.
        $this->assertStringStartsWith('lesson-presentations/', $pres->file_path);
        $this->assertStringNotContainsString('public', $pres->file_path);
        Storage::disk('local')->assertExists($pres->file_path);
    }
}
