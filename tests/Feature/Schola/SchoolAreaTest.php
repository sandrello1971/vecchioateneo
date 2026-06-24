<?php

namespace Tests\Feature\Schola;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolAreaTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $name = 'Liceo Galilei'): School
    {
        return School::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'type' => 'liceo', 'status' => 'active']);
    }

    private function member(School $school, string $role, bool $mustChange = false): Student
    {
        $secretary = $role === 'school_admin';
        return Student::create(['name' => ucfirst($role), 'email' => $role . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => $secretary ? null : $role, 'is_secretary' => $secretary,
            'school_id' => $school->id, 'is_active' => true, 'must_change_password' => $mustChange]);
    }

    private function as(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    // ===== shell / gate =====

    public function test_only_school_admin_can_access_area(): void
    {
        $school = $this->school();
        $admin = $this->member($school, 'school_admin');
        $prof = $this->member($school, 'professor');
        $student = $this->member($school, 'student');

        // Non autenticato PRIMA di impostare qualsiasi sessione.
        $this->get(route('scuola.dashboard'))->assertRedirect(route('student.login'));
        $this->as($admin)->get(route('scuola.dashboard'))->assertOk();
        $this->as($prof)->get(route('scuola.dashboard'))->assertForbidden();
        $this->as($student)->get(route('scuola.dashboard'))->assertForbidden();
    }

    public function test_school_admin_without_school_is_denied(): void
    {
        $orphan = Student::create(['name' => 'X', 'email' => 'o' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => null, 'is_active' => true, 'must_change_password' => false]);
        $this->as($orphan)->get(route('scuola.dashboard'))->assertForbidden();
    }

    public function test_first_login_forces_password_change(): void
    {
        $admin = $this->member($this->school(), 'school_admin', mustChange: true);
        $this->as($admin)->get(route('scuola.dashboard'))->assertRedirect(route('student.change-password'));
    }

    // ===== dashboard scoped =====

    public function test_dashboard_counts_are_scoped_to_own_school(): void
    {
        $a = $this->school('Liceo A'); $b = $this->school('Liceo B');
        $adminA = $this->member($a, 'school_admin');
        $this->member($a, 'professor'); $this->member($a, 'student');
        // Scuola B: dati che NON devono comparire
        $this->member($b, 'professor'); $this->member($b, 'professor'); $this->member($b, 'student');

        $sub = Subject::firstOrCreate(['name' => 'Fisica']);
        SchoolClass::create(['school_id' => $a->id, 'teacher_id' => null, 'name' => 'A1', 'subject_id' => $sub->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => false, 'requires_approval' => true, 'is_archived' => false]);
        SchoolClass::create(['school_id' => $b->id, 'teacher_id' => null, 'name' => 'B1', 'subject_id' => $sub->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => false, 'requires_approval' => true, 'is_archived' => false]);

        $resp = $this->as($adminA)->get(route('scuola.dashboard'));
        $resp->assertOk()->assertSee('Liceo A')->assertDontSee('Liceo B');
    }

    // ===== anagrafica + branding =====

    public function test_anagrafica_update_saves_branding(): void
    {
        $school = $this->school();
        $admin = $this->member($school, 'school_admin');

        $this->as($admin)->from(route('scuola.anagrafica.edit'))
            ->patch(route('scuola.anagrafica.update'), [
                'name' => 'Liceo Rinominato', 'type' => 'altro', 'city' => 'Milano',
                'instance_name' => 'Brand Scuola X', 'assistant_name' => 'Atena',
            ])->assertRedirect(route('scuola.anagrafica.edit'));

        $school->refresh();
        $this->assertSame('Liceo Rinominato', $school->name);
        $this->assertSame('Brand Scuola X', $school->setting('instance_name'));
        $this->assertSame('Atena', $school->setting('assistant_name'));
    }

    public function test_branding_applies_to_scuola_and_school_docente_not_free_docente(): void
    {
        $school = $this->school();
        $school->update(['settings' => ['instance_name' => 'BrandSchoolX']]);
        $admin = $this->member($school, 'school_admin');
        $schoolProf = $this->member($school, 'professor');

        // Segreteria: layout scuola mostra il branding
        $this->as($admin)->get(route('scuola.dashboard'))->assertSee('BrandSchoolX');
        // Docente della scuola: layout docente mostra il branding della scuola
        $this->as($schoolProf)->get(route('docente.dashboard'))->assertOk()->assertSee('BrandSchoolX');

        // Docente LIBERO (school_id NULL): nessun branding di scuola
        $freeProf = Student::create(['name' => 'Libero', 'email' => 'free' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => null, 'is_active' => true, 'must_change_password' => false]);
        $this->as($freeProf)->get(route('docente.dashboard'))->assertOk()->assertDontSee('BrandSchoolX');
    }

    // ===== logo: storage privato + tenancy =====

    public function test_logo_upload_and_private_serving_is_tenant_isolated(): void
    {
        Storage::fake('local');
        $a = $this->school('A'); $b = $this->school('B');
        $adminA = $this->member($a, 'school_admin');
        $adminB = $this->member($b, 'school_admin');

        $this->as($adminA)->patch(route('scuola.anagrafica.update'), [
            'name' => 'Liceo A', 'type' => 'liceo',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertRedirect();

        $a->refresh();
        $this->assertNotEmpty($a->setting('logo_path'));
        Storage::disk('local')->assertExists($a->setting('logo_path'));

        // Utente della scuola A → vede il logo
        $this->as($adminA)->get(route('scuola.logo', $a))->assertOk();
        $profA = $this->member($a, 'professor');
        $this->as($profA)->get(route('scuola.logo', $a))->assertOk();

        // Utente di un'ALTRA scuola → 403 (tenancy)
        $this->as($adminB)->get(route('scuola.logo', $a))->assertForbidden();
    }

    // ===== login redirect =====

    public function test_login_redirects_school_admin_to_area(): void
    {
        $school = $this->school();
        Student::create(['name' => 'Segr', 'email' => 'segr@x.it', 'password' => bcrypt('secret123'),
            'role' => null, 'is_secretary' => true, 'school_id' => $school->id, 'is_active' => true, 'must_change_password' => false]);

        $this->post(route('student.login.post'), ['email' => 'segr@x.it', 'password' => 'secret123'])
            ->assertRedirect(route('scuola.dashboard'));
    }
}
