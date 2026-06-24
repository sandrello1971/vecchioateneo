<?php

namespace Tests\Feature\Schola;

use App\Mail\TeacherInviteMail;
use App\Models\ImportBatch;
use App\Models\ProfessorSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeacherImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Student $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Subject::firstOrCreate(['name' => 'Fisica']);
        Subject::firstOrCreate(['name' => 'Storia']);
        $this->school = School::create(['name' => 'Liceo Galilei', 'slug' => 'galilei-' . uniqid(),
            'type' => 'liceo', 'status' => 'active']);
        $this->admin = Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $this->school->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function asAdmin(?Student $a = null): self
    {
        $a ??= $this->admin;
        return $this->withSession(['student_id' => $a->id, 'student_name' => $a->name, 'student_email' => $a->email]);
    }

    private function preview(string $csv): ImportBatch
    {
        $file = UploadedFile::fake()->createWithContent('docenti.csv', $csv);
        $this->asAdmin()->post(route('scuola.docenti.import.preview'), ['file' => $file])->assertOk();

        // status=previewed evita ambiguità di latest() quando i created_at coincidono
        return ImportBatch::where('school_id', $this->school->id)
            ->where('status', 'previewed')->latest()->firstOrFail();
    }

    private function commit(ImportBatch $batch, string $action = 'update'): \Illuminate\Testing\TestResponse
    {
        return $this->asAdmin()->post(route('scuola.docenti.import.commit'),
            ['batch_id' => $batch->id, 'duplicate_action' => $action]);
    }

    // ===== preview = dry-run, NON scrive =====

    public function test_preview_writes_nothing_but_batch(): void
    {
        $before = Student::count();
        $batch = $this->preview("nome,cognome,email,materie\nMario,Rossi,mario@s.it,Fisica|Storia\n");

        $this->assertSame($before, Student::count(), 'preview non deve creare studenti');
        $this->assertSame(0, ProfessorSubject::count());
        $this->assertSame('previewed', $batch->status);
        $this->assertSame(1, $batch->summary['valid']);
    }

    // ===== commit crea con school_id + materie =====

    public function test_commit_creates_professor_with_school_and_subjects(): void
    {
        Mail::fake();
        $batch = $this->preview("nome,cognome,email,materie\nMario,Rossi,mario@s.it,Fisica|Storia\n");

        $this->commit($batch)->assertRedirect(route('scuola.docenti.index'));

        $prof = Student::where('email', 'mario@s.it')->first();
        $this->assertNotNull($prof);
        $this->assertSame('professor', $prof->role);
        $this->assertSame($this->school->id, $prof->school_id);
        $this->assertTrue((bool) $prof->must_change_password);
        $this->assertEqualsCanonicalizing(['Fisica', 'Storia'], $prof->teachableSubjects->pluck('name')->all());
        $this->assertSame('committed', $batch->fresh()->status);
        Mail::assertQueued(TeacherInviteMail::class, 1);
    }

    // ===== idempotenza =====

    public function test_commit_is_idempotent(): void
    {
        Mail::fake();
        $csv = "nome,cognome,email,materie\nMario,Rossi,mario@s.it,Fisica\n";

        $this->commit($this->preview($csv));
        $this->assertSame(1, Student::where('email', 'mario@s.it')->count());

        // Seconda esecuzione (nuovo batch dallo stesso CSV): nessun duplicato
        $batch2 = $this->preview($csv);
        $this->assertSame('duplicate', $batch2->rows[0]['status']);
        $this->commit($batch2, 'update');

        $this->assertSame(1, Student::where('email', 'mario@s.it')->count());
        $this->assertSame(1, ProfessorSubject::whereIn('teacher_id',
            Student::where('email', 'mario@s.it')->pluck('id'))->count());
    }

    // ===== materia ignota segnalata, mai creata =====

    public function test_unknown_subject_is_flagged_not_created(): void
    {
        Mail::fake();
        $subjectsBefore = Subject::count();
        $batch = $this->preview("nome,cognome,email,materie\nLuca,Verdi,luca@s.it,Fisica|Alchimia\n");

        $this->assertSame(1, $batch->summary['unknown_subjects']);
        $this->assertContains('Alchimia', $batch->rows[0]['unknown']);

        $this->commit($batch);
        $this->assertSame($subjectsBefore, Subject::count(), 'le materie ignote non vengono create');
        $prof = Student::where('email', 'luca@s.it')->first();
        $this->assertEqualsCanonicalizing(['Fisica'], $prof->teachableSubjects->pluck('name')->all());
    }

    // ===== dedup con azione =====

    public function test_duplicate_action_skip_vs_update(): void
    {
        Mail::fake();
        // Docente già presente in questa scuola, senza materie
        $existing = Student::create(['name' => 'Anna Bianchi', 'email' => 'anna@s.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $this->school->id, 'is_active' => true, 'must_change_password' => false]);

        $csv = "nome,cognome,email,materie\nAnna,Bianchi,anna@s.it,Fisica\n";

        // skip → non tocca le materie
        $this->commit($this->preview($csv), 'skip');
        $this->assertSame(0, $existing->teachableSubjects()->count());

        // update → aggiunge le materie
        $this->commit($this->preview($csv), 'update');
        $this->assertEqualsCanonicalizing(['Fisica'], $existing->fresh()->teachableSubjects->pluck('name')->all());
    }

    // ===== conflitto cross-scuola =====

    public function test_cross_school_email_is_conflict_not_moved(): void
    {
        Mail::fake();
        $otherSchool = School::create(['name' => 'Altra', 'slug' => 'altra-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        $foreign = Student::create(['name' => 'Già Altrove', 'email' => 'altrove@s.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $otherSchool->id, 'is_active' => true, 'must_change_password' => false]);

        $batch = $this->preview("nome,cognome,email,materie\nGia,Altrove,altrove@s.it,Fisica\n");
        $this->assertSame('conflict', $batch->rows[0]['status']);
        $this->assertSame(1, $batch->summary['conflict']);

        $this->commit($batch);
        $this->assertSame($otherSchool->id, $foreign->fresh()->school_id, 'l\'account NON viene spostato');
    }

    // ===== delimitatori ; e , =====

    public function test_semicolon_delimiter_is_supported(): void
    {
        Mail::fake();
        $batch = $this->preview("nome;cognome;email;materie\nPaolo;Neri;paolo@s.it;Fisica|Storia\n");
        $this->assertSame(1, $batch->summary['valid']);
        $this->commit($batch);
        $this->assertEqualsCanonicalizing(['Fisica', 'Storia'],
            Student::where('email', 'paolo@s.it')->first()->teachableSubjects->pluck('name')->all());
    }

    // ===== tenancy =====

    public function test_cannot_commit_or_discard_another_schools_batch(): void
    {
        Mail::fake();
        $batch = $this->preview("nome,cognome,email,materie\nMario,Rossi,mario@s.it,Fisica\n");

        $otherSchool = School::create(['name' => 'Altra', 'slug' => 'altra-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        $otherAdmin = Student::create(['name' => 'Segr2', 'email' => 'sa2' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $otherSchool->id, 'is_active' => true, 'must_change_password' => false]);

        $this->asAdmin($otherAdmin)->post(route('scuola.docenti.import.commit'),
            ['batch_id' => $batch->id, 'duplicate_action' => 'update'])->assertForbidden();
        $this->asAdmin($otherAdmin)->post(route('scuola.docenti.import.discard', $batch))->assertForbidden();

        $this->assertSame('previewed', $batch->fresh()->status);
    }
}
