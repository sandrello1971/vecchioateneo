<?php

namespace Tests\Feature\Schola;

use App\Mail\TeacherInviteMail;
use App\Models\ProfessorSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// Segreteria: modifica docente (edit/update + reset password + attiva/disattiva),
// scoped sulla PROPRIA scuola (tenancy).
class TeacherEditTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Student $admin;
    private Subject $fisica;
    private Subject $storia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fisica = Subject::firstOrCreate(['name' => 'Fisica']);
        $this->storia = Subject::firstOrCreate(['name' => 'Storia']);
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

    private function makeTeacher(School $school, array $subjectIds = [], array $attrs = []): Student
    {
        $t = Student::create(array_merge([
            'name' => 'Prof Test', 'email' => 'prof' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $school->id, 'is_active' => true, 'must_change_password' => false,
        ], $attrs));
        foreach ($subjectIds as $sid) {
            ProfessorSubject::create(['teacher_id' => $t->id, 'subject_id' => $sid, 'school_id' => $school->id]);
        }
        return $t;
    }

    public function test_edit_page_loads_for_own_teacher(): void
    {
        $t = $this->makeTeacher($this->school, [$this->fisica->id]);

        $this->asAdmin()->get(route('scuola.docenti.edit', $t))
            ->assertOk()
            ->assertSee($t->email);
    }

    public function test_update_changes_name_email_and_syncs_subjects(): void
    {
        $t = $this->makeTeacher($this->school, [$this->fisica->id]);

        $this->asAdmin()->patch(route('scuola.docenti.update', $t), [
            'name'    => 'Nuovo Nome',
            'email'   => 'nuovo@example.it',
            'materie' => [$this->storia->id], // Fisica via, Storia dentro
        ])->assertRedirect(route('scuola.docenti.index'));

        $t->refresh();
        $this->assertSame('Nuovo Nome', $t->name);
        $this->assertSame('nuovo@example.it', $t->email);
        $this->assertEqualsCanonicalizing(['Storia'], $t->teachableSubjects->pluck('name')->all());
    }

    public function test_update_can_clear_all_subjects(): void
    {
        $t = $this->makeTeacher($this->school, [$this->fisica->id, $this->storia->id]);

        $this->asAdmin()->patch(route('scuola.docenti.update', $t), [
            'name'  => $t->name,
            'email' => $t->email,
            // niente materie
        ])->assertRedirect();

        $this->assertSame(0, $t->fresh()->teachableSubjects()->count());
    }

    public function test_reset_password_sets_flag_and_sends_invite(): void
    {
        Mail::fake();
        $t = $this->makeTeacher($this->school);
        $oldHash = $t->password;

        $this->asAdmin()->post(route('scuola.docenti.reset-password', $t))->assertRedirect();

        $t->refresh();
        $this->assertTrue($t->must_change_password);
        $this->assertNotSame($oldHash, $t->password);
        Mail::assertQueued(TeacherInviteMail::class);
    }

    public function test_toggle_active_flips_state(): void
    {
        $t = $this->makeTeacher($this->school, [], ['is_active' => true]);

        $this->asAdmin()->patch(route('scuola.docenti.toggle', $t))->assertRedirect();
        $this->assertFalse($t->fresh()->is_active);

        $this->asAdmin()->patch(route('scuola.docenti.toggle', $t))->assertRedirect();
        $this->assertTrue($t->fresh()->is_active);
    }

    // ===== Tenancy: solo la propria scuola =====

    public function test_cannot_edit_teacher_of_another_school(): void
    {
        $other = School::create(['name' => 'Altra', 'slug' => 'altra-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $foreign = $this->makeTeacher($other, [$this->fisica->id]);

        $this->asAdmin()->get(route('scuola.docenti.edit', $foreign))->assertForbidden();
        $this->asAdmin()->patch(route('scuola.docenti.update', $foreign), [
            'name' => 'X', 'email' => 'x@x.it',
        ])->assertForbidden();
        $this->asAdmin()->post(route('scuola.docenti.reset-password', $foreign))->assertForbidden();
        $this->asAdmin()->patch(route('scuola.docenti.toggle', $foreign))->assertForbidden();
    }

    public function test_cannot_edit_non_professor_account(): void
    {
        // Studente della stessa scuola (passa il check scuola, fallisce il check ruolo).
        $student = $this->makeTeacher($this->school, [], ['role' => 'student']);

        $this->asAdmin()->get(route('scuola.docenti.edit', $student))->assertNotFound();
    }
}
