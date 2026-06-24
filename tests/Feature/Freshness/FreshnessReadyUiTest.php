<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\Material;
use App\Services\InstructorManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

/**
 * F-c — Wiring UI del flag confirmOverwrite + feedback esito estrazione.
 * L'avviso pre-conferma scatta solo sui corsi con storia di apply; il controller passa
 * confirmOverwrite=true solo quando l'utente spunta la conferma; il feedback riflette
 * l'esito (generato / 0-blocchi / in-attesa-conferma / invalidazioni).
 */
class FreshnessReadyUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        return ['admin_logged_in' => true, 'admin_email' => 'a@ente.it'];
    }

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function withHistory(Course $course): void
    {
        CourseChangelog::create([
            'course_id' => $course->id, 'kind' => 'apply', 'content_source' => 'instructor',
            'version_from' => '1.0', 'version_to' => '2.0', 'summary' => 'dato di mercato aggiornato al 2026',
        ]);
    }

    /** Mock del service col solo esito da esporre (niente pandoc/HTTP nei test di wiring). */
    private function bindMock(?array $sync): Mockery\MockInterface
    {
        $mock = Mockery::mock(InstructorManualService::class);
        $mock->lastSourceSync = $sync;
        $this->app->instance(InstructorManualService::class, $mock);

        return $mock;
    }

    private function fakeDocx(): UploadedFile
    {
        return UploadedFile::fake()->create('manuale.docx', 50);
    }

    // ---- Avviso: mostrato solo con storia ----

    public function test_avviso_mostrato_su_corso_con_storia(): void
    {
        $course = $this->makeCourse();
        $this->withHistory($course);

        $this->withSession($this->admin())
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertSee('Corso con aggiornamenti', false) // header avviso (substring senza apostrofo)
            ->assertSee('name="confirm_overwrite"', false); // checkbox di conferma presente
    }

    public function test_nessun_avviso_su_corso_pristino(): void
    {
        $course = $this->makeCourse();

        $this->withSession($this->admin())
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertDontSee('Corso con aggiornamenti', false);
    }

    // ---- Wiring: confirmOverwrite passato al service solo dopo conferma ----

    public function test_senza_conferma_passa_confirmOverwrite_false(): void
    {
        $course = $this->makeCourse();
        $mock = $this->bindMock(['status' => 'generated', 'version' => '2.0', 'blocks' => 9, 'invalidated' => 0]);
        $mock->shouldReceive('uploadAndImport')->once()
            ->with(Mockery::type(UploadedFile::class), Mockery::type(Course::class), 'M', null, null, false)
            ->andReturn(new Material());

        $this->withSession($this->admin())
            ->post(route('admin.courses.instructor-materials.store', $course), ['title' => 'M', 'docx' => $this->fakeDocx()])
            ->assertRedirect();
    }

    public function test_con_conferma_passa_confirmOverwrite_true(): void
    {
        $course = $this->makeCourse();
        $mock = $this->bindMock(['status' => 'generated', 'version' => '3.0', 'blocks' => 9, 'invalidated' => 2]);
        $mock->shouldReceive('uploadAndImport')->once()
            ->with(Mockery::type(UploadedFile::class), Mockery::type(Course::class), 'M', null, null, true)
            ->andReturn(new Material());

        $this->withSession($this->admin())
            ->post(route('admin.courses.instructor-materials.store', $course),
                ['title' => 'M', 'docx' => $this->fakeDocx(), 'confirm_overwrite' => '1'])
            ->assertRedirect();
    }

    // ---- Feedback esito ----

    public function test_feedback_generato_e_freshness_ready(): void
    {
        $course = $this->makeCourse();
        $mock = $this->bindMock(['status' => 'generated', 'version' => '2.0', 'blocks' => 9, 'invalidated' => 0]);
        $mock->shouldReceive('uploadAndImport')->andReturn(new Material());

        $this->withSession($this->admin())
            ->post(route('admin.courses.instructor-materials.store', $course), ['title' => 'M', 'docx' => $this->fakeDocx()])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Sorgente strutturato generato (v2.0, 9 blocchi)')
                && str_contains($m, 'freshness-ready'));
    }

    public function test_feedback_segnala_proposte_invalidate(): void
    {
        $course = $this->makeCourse();
        $mock = $this->bindMock(['status' => 'generated', 'version' => '3.0', 'blocks' => 9, 'invalidated' => 3]);
        $mock->shouldReceive('uploadAndImport')->andReturn(new Material());

        $this->withSession($this->admin())
            ->post(route('admin.courses.instructor-materials.store', $course),
                ['title' => 'M', 'docx' => $this->fakeDocx(), 'confirm_overwrite' => '1'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '3 proposte in coda sono state invalidate'));
    }

    public function test_feedback_zero_blocchi(): void
    {
        $course = $this->makeCourse();
        $mock = $this->bindMock(['status' => 'empty']);
        $mock->shouldReceive('uploadAndImport')->andReturn(new Material());

        $this->withSession($this->admin())
            ->post(route('admin.courses.instructor-materials.store', $course), ['title' => 'M', 'docx' => $this->fakeDocx()])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'heading non riconosciuti nel manuale (0 blocchi)'));
    }

    public function test_feedback_in_attesa_conferma(): void
    {
        $course = $this->makeCourse();
        $mock = $this->bindMock(['status' => 'awaiting_confirmation']);
        $mock->shouldReceive('uploadAndImport')->andReturn(new Material());

        $this->withSession($this->admin())
            ->post(route('admin.courses.instructor-materials.store', $course), ['title' => 'M', 'docx' => $this->fakeDocx()])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'in attesa di conferma sovrascrittura'));
    }
}
