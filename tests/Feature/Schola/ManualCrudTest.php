<?php

namespace Tests\Feature\Schola;

use App\Mail\SchoolAdminInviteMail;
use App\Mail\StudentInviteMail;
use App\Mail\TeacherInviteMail;
use App\Models\ClassStudent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManualCrudTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Subject $fisica;
    private Subject $storia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create(['name' => 'Liceo Galilei', 'slug' => 'galilei-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $this->fisica = Subject::firstOrCreate(['name' => 'Fisica']);
        $this->storia = Subject::firstOrCreate(['name' => 'Storia']);
    }

    private function admin(): Student
    {
        return Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $this->school->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function asSchoolAdmin(?Student $a = null): self
    {
        $a ??= $this->admin();
        return $this->withSession(['student_id' => $a->id, 'student_name' => $a->name, 'student_email' => $a->email]);
    }

    private function asPlatformAdmin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);
    }

    private function klass(string $name = '3A'): SchoolClass
    {
        return SchoolClass::create(['school_id' => $this->school->id, 'teacher_id' => null, 'name' => $name, 'subject_id' => null,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);
    }

    // ===== A. Admin — segreteria recuperabile =====

    public function test_admin_adds_secretary_shows_password_once_and_optional_email(): void
    {
        Mail::fake();
        $resp = $this->asPlatformAdmin()->post(route('admin.scuole.nominate', $this->school), [
            'name' => 'Maria', 'email' => 'maria@galilei.it', 'send_email' => '1',
        ]);
        $resp->assertRedirect()->assertSessionHas('temp_password');

        $sa = Student::where('email', 'maria@galilei.it')->first();
        $this->assertTrue((bool) $sa->is_secretary);
        $this->assertSame($this->school->id, $sa->school_id);
        $this->assertTrue((bool) $sa->must_change_password);
        Mail::assertQueued(SchoolAdminInviteMail::class, 1);
    }

    public function test_admin_resets_password_shows_once(): void
    {
        Mail::fake();
        $sa = $this->admin();
        $oldHash = $sa->password;

        $this->asPlatformAdmin()->post(route('admin.scuole.segreteria.reset', [$this->school, $sa]), ['send_email' => '1'])
            ->assertRedirect()->assertSessionHas('temp_password');

        $sa->refresh();
        $this->assertNotSame($oldHash, $sa->password); // password cambiata
        $this->assertTrue((bool) $sa->must_change_password);
        Mail::assertQueued(SchoolAdminInviteMail::class, 1);
    }

    public function test_admin_resend_invite_and_toggle_active(): void
    {
        Mail::fake();
        $sa = $this->admin();

        $this->asPlatformAdmin()->post(route('admin.scuole.segreteria.resend', [$this->school, $sa]))
            ->assertRedirect()->assertSessionHas('temp_password');
        Mail::assertQueued(SchoolAdminInviteMail::class, 1);

        $this->asPlatformAdmin()->patch(route('admin.scuole.segreteria.toggle', [$this->school, $sa]))->assertRedirect();
        $this->assertFalse((bool) $sa->fresh()->is_active);
        $this->asPlatformAdmin()->patch(route('admin.scuole.segreteria.toggle', [$this->school, $sa]))->assertRedirect();
        $this->assertTrue((bool) $sa->fresh()->is_active);
    }

    public function test_secretary_actions_are_platform_admin_only_and_tenant_scoped(): void
    {
        $sa = $this->admin();
        // anonimo / non-admin → redirect login admin
        $this->post(route('admin.scuole.segreteria.reset', [$this->school, $sa]))->assertRedirect();

        // segreteria di un'ALTRA scuola passata su questa scuola → 404
        $other = School::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        $foreignSa = Student::create(['name' => 'X', 'email' => 'x' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $other->id, 'is_active' => true, 'must_change_password' => false]);
        $this->asPlatformAdmin()->post(route('admin.scuole.segreteria.reset', [$this->school, $foreignSa]))->assertNotFound();
    }

    // ===== B. Scuola — inserimento singolo =====

    public function test_add_single_teacher_with_email_and_subjects_idempotent(): void
    {
        Mail::fake();
        $payload = ['nome' => 'Carla', 'cognome' => 'Neri', 'email' => 'carla@galilei.it',
            'materie' => [$this->fisica->id, $this->storia->id]];

        $this->asSchoolAdmin()->post(route('scuola.docenti.store'), $payload)->assertRedirect(route('scuola.docenti.index'));

        $t = Student::where('email', 'carla@galilei.it')->first();
        $this->assertSame('professor', $t->role);
        $this->assertSame($this->school->id, $t->school_id);
        $this->assertEqualsCanonicalizing(['Fisica', 'Storia'], $t->teachableSubjects->pluck('name')->all());
        Mail::assertQueued(TeacherInviteMail::class, 1);

        // idempotente: stesso email → aggiornato, niente duplicato
        $this->asSchoolAdmin()->post(route('scuola.docenti.store'), $payload)->assertRedirect();
        $this->assertSame(1, Student::where('email', 'carla@galilei.it')->count());
    }

    public function test_add_single_teacher_cross_school_conflict(): void
    {
        Mail::fake();
        $other = School::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        Student::create(['name' => 'Già', 'email' => 'gia@altrove.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $other->id, 'is_active' => true, 'must_change_password' => false]);

        $this->asSchoolAdmin()->from(route('scuola.docenti.create'))->post(route('scuola.docenti.store'), [
            'nome' => 'Gia', 'cognome' => 'Altrove', 'email' => 'gia@altrove.it',
        ])->assertRedirect(route('scuola.docenti.create'))->assertSessionHas('error');

        $this->assertSame($other->id, Student::where('email', 'gia@altrove.it')->first()->school_id);
    }

    public function test_add_single_student_with_email_sends_invite(): void
    {
        Mail::fake();
        $class = $this->klass();
        $this->asSchoolAdmin()->post(route('scuola.studenti.store'), [
            'nome' => 'Anna', 'cognome' => 'Russo', 'email' => 'anna@s.it',
            'data_nascita' => '2010-05-01', 'class_id' => $class->id, 'consent' => '1',
        ])->assertRedirect(route('scuola.studenti.index'));

        $s = Student::where('email', 'anna@s.it')->first();
        $this->assertSame('student', $s->role);
        $enr = ClassStudent::where('student_id', $s->id)->first();
        $this->assertSame('active', $enr->status);
        $this->assertNotNull($enr->consent_at);
        Mail::assertQueued(StudentInviteMail::class, 1);
    }

    public function test_add_single_student_without_email_shows_credentials_once(): void
    {
        Mail::fake();
        $class = $this->klass();
        $resp = $this->asSchoolAdmin()->post(route('scuola.studenti.store'), [
            'nome' => 'Luca', 'cognome' => 'Verdi', 'email' => '',
            'data_nascita' => '2009-03-02', 'class_id' => $class->id,
        ]);
        $resp->assertRedirect(route('scuola.studenti.index'))->assertSessionHas('single_credentials');

        $creds = session('single_credentials');
        $this->assertCount(1, $creds);
        $this->assertStringContainsString('luca.verdi', $creds[0]['username']);
        $this->assertNotEmpty($creds[0]['password']);

        $s = Student::whereNotNull('username')->where('school_id', $this->school->id)->first();
        $this->assertNull($s->email);
        $this->assertTrue((bool) $s->must_change_password);
        Mail::assertNothingQueued();
    }

    public function test_add_single_student_class_must_belong_to_school(): void
    {
        $other = School::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        $foreignClass = SchoolClass::create(['school_id' => $other->id, 'teacher_id' => null, 'name' => 'X', 'subject_id' => null,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);

        $this->asSchoolAdmin()->post(route('scuola.studenti.store'), [
            'nome' => 'Z', 'cognome' => 'Z', 'data_nascita' => '2010-01-01', 'class_id' => $foreignClass->id,
        ])->assertForbidden();
    }
}
