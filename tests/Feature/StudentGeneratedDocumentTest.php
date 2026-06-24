<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Module;
use App\Models\ModuleDocument;
use App\Models\Student;
use App\Services\ModuleDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P29 Fase 3 — download lato studente/instructor del PDF generato (modulo +
 * dispensa corso): generazione on-access sincrona con lock, accesso = gate del
 * corso (iscritto/teaches/auto_enroll), demo lucchettato. Additivo.
 */
class StudentGeneratedDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Mario Rossi',
            'email' => 'mario+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'is_active' => true,
            'is_demo' => false,
            'must_change_password' => false,
        ], $attrs));
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'name' => 'Corso AI',
            'slug' => 'corso-ai-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function addModule(Course $course, string $content = '<h2>Intro</h2><p>Testo del modulo.</p>', int $sort = 0): Module
    {
        return Module::create([
            'course_id' => $course->id,
            'title' => 'Modulo ' . ($sort + 1),
            'content' => $content,
            'sort_order' => $sort,
            'is_active' => true,
        ]);
    }

    private function enroll(Student $student, Course $course): void
    {
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
    }

    private function teach(Student $instructor, Course $course): void
    {
        $instructor->taughtCourses()->attach($course->id);
    }

    private function actingAsStudent(Student $student): self
    {
        return $this->withSession([
            'student_id' => $student->id,
            'student_email' => $student->email,
            'student_name' => $student->name,
        ]);
    }

    private function moduleUrl(Course $c, Module $m): string
    {
        return route('student.module.document.download', [$c->slug, $m]);
    }

    private function courseUrl(Course $c): string
    {
        return route('student.course.document.download', $c->slug);
    }

    // ============================================================
    // Generazione on-access (modulo)
    // ============================================================

    public function test_studente_iscritto_scarica_modulo_generato_on_access(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        // Nessun ModuleDocument prima del primo accesso.
        $this->assertSame(0, ModuleDocument::count());

        $res = $this->actingAsStudent($student)->get($this->moduleUrl($course, $module));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));

        $doc = ModuleDocument::where('module_id', $module->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame('ready', $doc->status);
        $this->assertSame($module->currentContentHash(), $doc->content_hash);
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_secondo_accesso_non_rigenera_se_fresh(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->get($this->moduleUrl($course, $module))->assertOk();
        $doc = ModuleDocument::where('module_id', $module->id)->first();
        $firstPath = $doc->file_path;
        $firstUpdated = $doc->updated_at;

        // Spia: il service NON deve ricostruire al secondo accesso (fresh).
        $spy = \Mockery::spy(ModuleDocumentService::class, [app(\App\Services\CourseSourcePdfBuilder::class)])->makePartial();
        $this->app->instance(ModuleDocumentService::class, $spy);

        $this->actingAsStudent($student)->get($this->moduleUrl($course, $module))->assertOk();
        $spy->shouldNotHaveReceived('buildDocumentForModule');

        $doc->refresh();
        $this->assertSame($firstPath, $doc->file_path);
        $this->assertEquals($firstUpdated, $doc->updated_at);
    }

    public function test_accesso_rigenera_se_stale(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->get($this->moduleUrl($course, $module))->assertOk();
        $oldHash = ModuleDocument::where('module_id', $module->id)->first()->content_hash;

        // Il contenuto cambia → al prossimo accesso il documento è stale e va rigenerato.
        $module->update(['content' => '<h2>Contenuto nuovo</h2><p>Aggiornato.</p>']);
        $this->actingAsStudent($student)->get($this->moduleUrl($course, $module))->assertOk();

        $doc = ModuleDocument::where('module_id', $module->id)->first();
        $this->assertNotSame($oldHash, $doc->content_hash);
        $this->assertSame($module->fresh()->currentContentHash(), $doc->content_hash);
    }

    public function test_modulo_senza_contenuto_404(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course, content: '');
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->get($this->moduleUrl($course, $module))->assertNotFound();
        $this->assertSame(0, ModuleDocument::count());
    }

    // ============================================================
    // Accesso: instructor, auto_enroll, non-iscritto, demo, cross-course
    // ============================================================

    public function test_instructor_che_insegna_scarica_anche_senza_iscrizione(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $instructor = $this->makeStudent(['is_instructor' => true]);
        $this->teach($instructor, $course); // insegna ma NON iscritto

        $this->actingAsStudent($instructor)->get($this->moduleUrl($course, $module))->assertOk();
    }

    public function test_auto_enroll_scarica(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $student = $this->makeStudent(['auto_enroll_all_courses' => true]);

        $this->actingAsStudent($student)->get($this->moduleUrl($course, $module))->assertOk();
    }

    public function test_studente_non_iscritto_403(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $student = $this->makeStudent(); // non iscritto

        $this->actingAsStudent($student)->get($this->moduleUrl($course, $module))->assertForbidden();
        $this->assertSame(0, ModuleDocument::count());
    }

    public function test_demo_lucchettato_403_e_non_genera(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $demo = $this->makeStudent(['is_demo' => true, 'auto_enroll_all_courses' => true]);

        $this->actingAsStudent($demo)->get($this->moduleUrl($course, $module))->assertForbidden();
        $this->assertSame(0, ModuleDocument::count());
    }

    public function test_modulo_di_altro_corso_404(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $altro = $this->makeCourse();
        $module = $this->addModule($course);
        $student = $this->makeStudent();
        $this->enroll($student, $altro);

        // iscritto ad 'altro', chiede il modulo di 'course' via slug di 'altro' → 404
        $this->actingAsStudent($student)
            ->get(route('student.module.document.download', [$altro->slug, $module]))
            ->assertNotFound();
    }

    public function test_non_autenticato_redirect_login(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course);

        $this->get($this->moduleUrl($course, $module))->assertRedirect('/learn/login');
    }

    // ============================================================
    // Dispensa corso
    // ============================================================

    public function test_dispensa_corso_generata_on_access(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $this->addModule($course, '<h2>Uno</h2><p>A.</p>', 0);
        $this->addModule($course, '<h2>Due</h2><p>B.</p>', 1);
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        $res = $this->actingAsStudent($student)->get($this->courseUrl($course));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));

        $cd = CourseDocument::where('course_id', $course->id)->first();
        $this->assertSame('ready', $cd->status);
        $this->assertSame($course->currentContentHash(), $cd->content_hash);
        $this->assertSame(2, $cd->generation_meta['modules']);
    }

    public function test_dispensa_corso_stale_dopo_aggiunta_modulo(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $this->addModule($course, '<h2>Uno</h2><p>A.</p>', 0);
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->get($this->courseUrl($course))->assertOk();
        $oldHash = CourseDocument::where('course_id', $course->id)->first()->content_hash;

        $this->addModule($course, '<h2>Due</h2><p>B.</p>', 1); // cambia hash aggregato
        $this->actingAsStudent($student)->get($this->courseUrl($course))->assertOk();

        $cd = CourseDocument::where('course_id', $course->id)->first();
        $this->assertNotSame($oldHash, $cd->content_hash);
        $this->assertSame(2, $cd->generation_meta['modules']);
    }

    public function test_dispensa_corso_senza_contenuto_404(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $this->addModule($course, content: '', sort: 0);
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->get($this->courseUrl($course))->assertNotFound();
        $this->assertSame(0, CourseDocument::count());
    }

    // ============================================================
    // Vista: partizione materials + lucchetto demo
    // ============================================================

    public function test_vista_modulo_mostra_documento_e_nasconde_documentali(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        // Un material documentale (docx) e uno non (canvas).
        \App\Models\Material::create([
            'module_id' => $module->id, 'title' => 'Slide caricate', 'file_type' => 'docx',
            'file_path' => 'materials/x.docx', 'is_downloadable' => true, 'is_instructor_only' => false, 'sort_order' => 0,
        ]);
        \App\Models\Material::create([
            'module_id' => $module->id, 'title' => 'Lavagna', 'file_type' => 'canvas',
            'file_path' => 'materials/c.html', 'is_downloadable' => false, 'is_instructor_only' => false, 'sort_order' => 1,
        ]);
        $student = $this->makeStudent();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->get(route('student.module.show', [$course->slug, $module]))
            ->assertOk()
            ->assertSee('Documento del modulo')      // blocco generato presente
            ->assertSee('Scarica PDF')
            ->assertSee('Lavagna')                    // canvas resta
            ->assertDontSee('Slide caricate');        // docx nascosto
    }

    public function test_vista_modulo_demo_mostra_lucchetto(): void
    {
        Storage::fake('local');
        $course = $this->makeCourse();
        $module = $this->addModule($course);
        $demo = $this->makeStudent(['is_demo' => true, 'auto_enroll_all_courses' => true]);

        $this->actingAsStudent($demo)->get(route('student.module.show', [$course->slug, $module]))
            ->assertOk()
            ->assertSee('Solo versione completa');
    }
}
