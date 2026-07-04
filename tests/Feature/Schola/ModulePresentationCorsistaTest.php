<?php

namespace Tests\Feature\Schola;

use App\Models\Course;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blocco B / B3 — esposizione corsista delle presentazioni dei MODULI. Il corsista
 * iscritto vede/scarica SOLO la versione pubblicata; le bozze restano nascoste; un
 * non iscritto non accede (no leak). Guardrail come il punto 3 del Blocco A.
 */
class ModulePresentationCorsistaTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::create(['name' => 'Cors', 'email' => 'c' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function course(): Course
    {
        return Course::create(['name' => 'Corso AI', 'slug' => 'c-' . Str::lower(Str::random(8)), 'is_active' => true]);
    }

    private function module(Course $course): Module
    {
        return Module::create(['course_id' => $course->id, 'title' => 'Modulo 1', 'content' => '## x', 'sort_order' => 0, 'is_active' => true]);
    }

    private function enroll(Student $s, Course $c): void
    {
        $s->courses()->attach($c->id, ['is_active' => true]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    private function makePresentation(Module $module, ?string $publishedAt): ModulePresentation
    {
        $pres = ModulePresentation::create([
            'module_id' => $module->id, 'status' => 'ready', 'source' => 'generated',
            'spec' => ['slides' => [['layout' => 'cover']]],
            'generation_meta' => ['filename' => 'modulo-1.pptx', 'slides' => 1],
            'published_at' => $publishedAt,
        ]);
        $path = "module-presentations/{$module->id}/{$pres->id}.pptx";
        Storage::disk('local')->put($path, 'PPTX-' . $pres->id);
        $pres->update(['file_path' => $path]);

        return $pres->refresh();
    }

    private function dl(Course $course, Module $module): string
    {
        return route('student.module.presentation.download', [$course->slug, $module]);
    }

    // 1. corsista iscritto + PUBBLICATA → scarica
    public function test_corsista_iscritto_scarica_la_pubblicata(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $module = $this->module($course);
        $student = $this->student();
        $this->enroll($student, $course);
        $this->makePresentation($module, publishedAt: now());

        $this->asUser($student)->get($this->dl($course, $module))
            ->assertOk()->assertDownload('modulo-1.pptx');
    }

    // 2. solo BOZZA (non pubblicata) → corsista NON la vede
    public function test_corsista_non_vede_la_bozza(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $module = $this->module($course);
        $student = $this->student();
        $this->enroll($student, $course);
        $this->makePresentation($module, publishedAt: null); // bozza

        $this->asUser($student)->get($this->dl($course, $module))->assertNotFound();
    }

    // 3. corsista NON iscritto → 403 (no leak)
    public function test_non_iscritto_403(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $module = $this->module($course);
        $this->makePresentation($module, publishedAt: now());

        $this->asUser($this->student())->get($this->dl($course, $module))->assertForbidden();
    }

    // 4. bi-versione: pubblicata + bozza → il corsista scarica la PUBBLICATA (non la bozza)
    public function test_corsista_vede_sempre_la_pubblicata_non_la_bozza(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $module = $this->module($course);
        $student = $this->student();
        $this->enroll($student, $course);
        $published = $this->makePresentation($module, publishedAt: now());
        $draft = $this->makePresentation($module, publishedAt: null);

        $resp = $this->asUser($student)->get($this->dl($course, $module))->assertOk();
        // Il file scaricato è quello della PUBBLICATA, non della bozza.
        $this->assertSame('PPTX-' . $published->id, $resp->streamedContent());
        $this->assertNotSame('PPTX-' . $draft->id, $resp->streamedContent());
    }
}
