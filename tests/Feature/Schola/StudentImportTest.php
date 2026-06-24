<?php

namespace Tests\Feature\Schola;

use App\Mail\StudentInviteMail;
use App\Models\ClassStudent;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Student $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create(['name' => 'Liceo Galilei', 'slug' => 'galilei', 'type' => 'liceo', 'status' => 'active']);
        $this->admin = Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $this->school->id, 'is_active' => true, 'must_change_password' => false]);
        $this->existingClass('3A');
    }

    private function existingClass(string $name): SchoolClass
    {
        return SchoolClass::create(['school_id' => $this->school->id, 'teacher_id' => null, 'name' => $name,
            'subject_id' => null, 'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);
    }

    private function asAdmin(?Student $a = null): self
    {
        $a ??= $this->admin;
        return $this->withSession(['student_id' => $a->id, 'student_name' => $a->name, 'student_email' => $a->email]);
    }

    private function preview(string $csv): ImportBatch
    {
        $file = UploadedFile::fake()->createWithContent('studenti.csv', $csv);
        $this->asAdmin()->post(route('scuola.studenti.import.preview'), ['file' => $file])->assertOk();

        return ImportBatch::where('school_id', $this->school->id)->where('status', 'previewed')->latest()->firstOrFail();
    }

    private function commit(ImportBatch $batch, array $opts = []): \Illuminate\Testing\TestResponse
    {
        return $this->asAdmin()->post(route('scuola.studenti.import.commit'), array_merge([
            'batch_id' => $batch->id, 'duplicate_action' => 'update',
        ], $opts));
    }

    private const H = "nome,cognome,email,data_nascita,classe";

    // ===== preview dry-run =====

    public function test_preview_writes_no_students(): void
    {
        $before = Student::count();
        $batch = $this->preview(self::H . "\nMario,Rossi,mario@s.it,2010-05-01,3A\n");

        $this->assertSame($before, Student::count());
        $this->assertSame('previewed', $batch->status);
        $this->assertSame(1, $batch->summary['valid']);
    }

    public function test_missing_birthdate_is_row_error(): void
    {
        $batch = $this->preview(self::H . "\nMario,Rossi,mario@s.it,,3A\n");
        $this->assertSame('error', $batch->rows[0]['status']);
        $this->assertSame(1, $batch->summary['error']);
    }

    public function test_minor_count_in_report(): void
    {
        $csv = self::H . "\nMin,Ore,min@s.it,2012-01-01,3A\nMag,Iore,mag@s.it,2000-01-01,3A\n";
        $batch = $this->preview($csv);
        $this->assertSame(1, $batch->summary['minors']);
    }

    // ===== commit: email vs no-email credenziali duali =====

    public function test_commit_email_student_sends_invite(): void
    {
        Mail::fake();
        $this->commit($this->preview(self::H . "\nMario,Rossi,mario@s.it,2010-05-01,3A\n"))
            ->assertRedirect();

        $stu = Student::where('email', 'mario@s.it')->first();
        $this->assertNotNull($stu);
        $this->assertSame('student', $stu->role);
        $this->assertSame($this->school->id, $stu->school_id);
        $this->assertNull($stu->username);
        $this->assertTrue((bool) $stu->must_change_password);
        Mail::assertQueued(StudentInviteMail::class, 1);
    }

    public function test_commit_no_email_student_generates_username(): void
    {
        Mail::fake();
        $this->commit($this->preview(self::H . "\nLuca,Verdi,,2009-03-02,3A\n"))->assertRedirect();

        $stu = Student::where('role', 'student')->where('school_id', $this->school->id)->whereNotNull('username')->first();
        $this->assertNotNull($stu);
        $this->assertSame('luca.verdi.galilei', $stu->username);
        $this->assertNull($stu->email);
        $this->assertTrue((bool) $stu->must_change_password);
        Mail::assertNothingQueued();
    }

    public function test_enrollment_is_active_without_invite_code(): void
    {
        Mail::fake();
        $this->commit($this->preview(self::H . "\nMario,Rossi,mario@s.it,2010-05-01,3A\n"));

        $stu = Student::where('email', 'mario@s.it')->first();
        $enr = ClassStudent::where('student_id', $stu->id)->first();
        $this->assertSame('active', $enr->status);
        $this->assertNotNull($enr->approved_at);
    }

    public function test_consent_at_is_populated_when_provided(): void
    {
        Mail::fake();
        $csv = "nome,cognome,email,data_nascita,classe,consenso\nMario,Rossi,mario@s.it,2010-05-01,3A,si\n";
        $this->commit($this->preview($csv));

        $stu = Student::where('email', 'mario@s.it')->first();
        $this->assertNotNull(ClassStudent::where('student_id', $stu->id)->first()->consent_at);
    }

    // ===== classi da creare =====

    public function test_missing_class_created_only_with_confirmation(): void
    {
        Mail::fake();
        $csv = self::H . "\nMario,Rossi,mario@s.it,2010-05-01,5Z\n";

        // senza conferma → riga saltata, classe non creata
        $this->commit($this->preview($csv), ['create_missing_classes' => 0]);
        $this->assertFalse(SchoolClass::where('school_id', $this->school->id)->where('name', '5Z')->exists());
        $this->assertNull(Student::where('email', 'mario@s.it')->first());

        // con conferma → classe creata + iscrizione
        $this->commit($this->preview($csv), ['create_missing_classes' => 1]);
        $class = SchoolClass::where('school_id', $this->school->id)->where('name', '5Z')->first();
        $this->assertNotNull($class);
        $stu = Student::where('email', 'mario@s.it')->first();
        $this->assertSame('active', ClassStudent::where('student_id', $stu->id)->where('school_class_id', $class->id)->first()->status);
    }

    // ===== idempotenza =====

    public function test_commit_is_idempotent(): void
    {
        Mail::fake();
        $csv = self::H . "\nMario,Rossi,mario@s.it,2010-05-01,3A\nLuca,Verdi,,2009-03-02,3A\n";

        $this->commit($this->preview($csv));
        $this->assertSame(2, Student::where('role', 'student')->count());

        $batch2 = $this->preview($csv);
        $this->assertSame(2, $batch2->summary['duplicate']);
        $this->commit($batch2);
        $this->assertSame(2, Student::where('role', 'student')->count());
        $this->assertSame(2, ClassStudent::count());
    }

    // ===== credenziali one-time =====

    public function test_generated_credentials_are_shown_once_then_consumed(): void
    {
        Mail::fake();
        $batch = $this->preview(self::H . "\nLuca,Verdi,,2009-03-02,3A\n");
        $this->commit($batch)->assertRedirect(route('scuola.studenti.import.result', $batch));

        // result page mostra username
        $this->asAdmin()->get(route('scuola.studenti.import.result', $batch))->assertOk()->assertSee('luca.verdi.galilei');
        // download CSV
        $this->asAdmin()->get(route('scuola.studenti.import.credentials', $batch))->assertOk();
        // consumate: secondo download 404
        $this->asAdmin()->get(route('scuola.studenti.import.credentials', $batch))->assertNotFound();
    }

    // ===== tenancy =====

    public function test_tenancy_blocks_cross_school_commit_and_credentials(): void
    {
        Mail::fake();
        $batch = $this->preview(self::H . "\nLuca,Verdi,,2009-03-02,3A\n");

        $other = School::create(['name' => 'Altra', 'slug' => 'altra', 'type' => 'altro', 'status' => 'active']);
        $otherAdmin = Student::create(['name' => 'S2', 'email' => 'sa2' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $other->id, 'is_active' => true, 'must_change_password' => false]);

        $this->asAdmin($otherAdmin)->post(route('scuola.studenti.import.commit'),
            ['batch_id' => $batch->id, 'duplicate_action' => 'update'])->assertForbidden();
        $this->asAdmin($otherAdmin)->get(route('scuola.studenti.import.result', $batch))->assertForbidden();
    }

    // ===== login duale =====

    public function test_dual_login_email_and_username(): void
    {
        Mail::fake();
        // studente con username (no email)
        $byUser = Student::create(['name' => 'Solo Username', 'username' => 'solo.username.galilei',
            'password' => bcrypt('secret123'), 'role' => 'student', 'school_id' => $this->school->id,
            'is_active' => true, 'must_change_password' => false]);
        // studente con email
        Student::create(['name' => 'Con Email', 'email' => 'conemail@s.it', 'password' => bcrypt('secret123'),
            'role' => 'student', 'school_id' => $this->school->id, 'is_active' => true, 'must_change_password' => false]);

        // login via username
        $this->post(route('student.login.post'), ['email' => 'solo.username.galilei', 'password' => 'secret123'])
            ->assertRedirect();
        $this->assertSame($byUser->id, session('student_id'));

        // login via email (regressione)
        $this->flushSession();
        $this->post(route('student.login.post'), ['email' => 'conemail@s.it', 'password' => 'secret123'])
            ->assertRedirect();

        // credenziali errate
        $this->flushSession();
        $this->post(route('student.login.post'), ['email' => 'solo.username.galilei', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }
}
