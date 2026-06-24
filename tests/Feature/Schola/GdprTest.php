<?php

namespace Tests\Feature\Schola;

use App\Jobs\ExportSchoolDataJob;
use App\Models\ClassStudent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GdprTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create(['name' => 'Liceo', 'slug' => 'liceo-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
    }

    private function admin(?School $s = null): Student
    {
        $s ??= $this->school;
        return Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $s->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function as(Student $u): self
    {
        return $this->withSession(['student_id' => $u->id, 'student_name' => $u->name, 'student_email' => $u->email]);
    }

    private function klass(string $year, bool $archived): SchoolClass
    {
        return SchoolClass::create(['school_id' => $this->school->id, 'teacher_id' => null, 'name' => 'C' . uniqid(),
            'subject_id' => null, 'school_year' => $year, 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => $archived]);
    }

    private function student(string $name): Student
    {
        return Student::create(['name' => $name, 'email' => \Illuminate\Support\Str::slug($name) . '@s.it', 'password' => bcrypt('x'),
            'role' => 'student', 'school_id' => $this->school->id, 'birth_date' => '2009-01-01',
            'is_active' => true, 'must_change_password' => false]);
    }

    // ===== retention =====

    public function test_retention_requires_options_and_defaults_to_dry_run(): void
    {
        $this->artisan('schola:retention')->assertExitCode(1); // mancano le opzioni

        $old = $this->klass('2025/2026', archived: true);
        $gone = $this->student('Andato Via');
        ClassStudent::create(['school_class_id' => $old->id, 'student_id' => $gone->id, 'status' => 'active', 'approved_at' => now()]);

        // dry-run di default: NON scrive
        $this->artisan('schola:retention', ['--school' => $this->school->slug, '--school-year' => '2025/2026'])
            ->assertExitCode(0);
        $this->assertSame('Andato Via', $gone->fresh()->name);
    }

    public function test_retention_force_anonymizes_only_left_students(): void
    {
        $old = $this->klass('2025/2026', archived: true);
        $current = $this->klass('2026/2027', archived: false);

        $gone = $this->student('Andato Via');
        ClassStudent::create(['school_class_id' => $old->id, 'student_id' => $gone->id, 'status' => 'active', 'approved_at' => now()]);

        $staying = $this->student('Resta Qui');
        ClassStudent::create(['school_class_id' => $old->id, 'student_id' => $staying->id, 'status' => 'active', 'approved_at' => now()]);
        ClassStudent::create(['school_class_id' => $current->id, 'student_id' => $staying->id, 'status' => 'active', 'approved_at' => now()]);

        $teacher = Student::create(['name' => 'Prof Resta', 'email' => 'prof@s.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $this->school->id, 'is_active' => true, 'must_change_password' => false]);

        $this->artisan('schola:retention', ['--school' => $this->school->slug, '--school-year' => '2025/2026', '--force' => true])
            ->assertExitCode(0);

        // Uscito → anonimizzato
        $gone->refresh();
        $this->assertSame('Studente anonimizzato', $gone->name);
        $this->assertNull($gone->email);
        $this->assertFalse((bool) $gone->is_active);

        // Chi resta e il docente → invariati
        $this->assertSame('Resta Qui', $staying->fresh()->name);
        $this->assertSame('Prof Resta', $teacher->fresh()->name);
    }

    // ===== export =====

    public function test_export_dispatches_and_writes_scoped_file(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        Bus::fake();
        $this->as($admin)->post(route('scuola.privacy.export'))->assertRedirect();
        Bus::assertDispatchedAfterResponse(ExportSchoolDataJob::class);

        // Eseguendo il job: scrive il file della SOLA scuola.
        $other = School::create(['name' => 'Altra', 'slug' => 'altra-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        Student::create(['name' => 'Estraneo', 'email' => 'estraneo@x.it', 'password' => bcrypt('x'),
            'role' => 'student', 'school_id' => $other->id, 'is_active' => true, 'must_change_password' => false]);
        $this->student('Mio Studente');

        (new ExportSchoolDataJob($this->school->id))->handle(app(\App\Services\Schola\SchoolDataExportService::class));
        Storage::disk('local')->assertExists(ExportSchoolDataJob::path($this->school->id));
        $json = Storage::disk('local')->get(ExportSchoolDataJob::path($this->school->id));
        $this->assertStringContainsString('Mio Studente', $json);
        $this->assertStringNotContainsString('Estraneo', $json); // niente dati di altre scuole
    }

    public function test_export_download_is_scoped_to_own_school(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        Storage::disk('local')->put(ExportSchoolDataJob::path($this->school->id), '{"ok":1}');

        $this->as($admin)->get(route('scuola.privacy.export.download'))->assertOk();

        // Admin di un'altra scuola senza export → 404 (mai il file altrui)
        $other = School::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        $this->as($this->admin($other))->get(route('scuola.privacy.export.download'))->assertNotFound();
    }

    // ===== DPA =====

    public function test_dpa_can_be_marked_and_revoked(): void
    {
        $admin = $this->admin();
        $this->assertNull($this->school->dpa_signed_at);

        $this->as($admin)->post(route('scuola.privacy.dpa'))->assertRedirect();
        $this->assertNotNull($this->school->fresh()->dpa_signed_at);

        $this->as($admin)->post(route('scuola.privacy.dpa'))->assertRedirect();
        $this->assertNull($this->school->fresh()->dpa_signed_at);
    }
}
