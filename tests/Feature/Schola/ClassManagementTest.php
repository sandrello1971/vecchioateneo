<?php

namespace Tests\Feature\Schola;

use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfessor(): Student
    {
        return Student::create([
            'name' => 'Prof ' . uniqid(), 'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'), 'role' => 'professor',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Stud ' . uniqid(), 'email' => 'stud+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'), 'role' => 'student',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function subjectId(): string
    {
        return Subject::firstOrCreate(['name' => 'Fisica'], ['is_custom' => false])->id;
    }

    private function makeClass(Student $prof, array $attrs = []): SchoolClass
    {
        return SchoolClass::create(array_merge([
            'teacher_id' => $prof->id, 'name' => '3ªB', 'subject_id' => $this->subjectId(),
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => true, 'is_archived' => false,
        ], $attrs));
    }

    private function asStudent(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email,
        ]);
    }

    public function test_invite_code_format_is_unambiguous_7_chars(): void
    {
        $code = SchoolClass::generateInviteCode();
        $this->assertSame(7, strlen($code));
        $this->assertSame(0, preg_match('/[0O1IL]/', $code), 'niente caratteri ambigui');
    }

    public function test_professor_creates_class(): void
    {
        $prof = $this->makeProfessor();

        $this->asStudent($prof)->post(route('docente.classes.store'), [
            'name' => '4ªA', 'subject_id' => $this->subjectId(),
            'school_year' => '2026/2027', 'requires_approval' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('school_classes', [
            'teacher_id' => $prof->id, 'name' => '4ªA', 'requires_approval' => true,
        ]);
    }

    public function test_join_with_approval_sets_pending(): void
    {
        $class = $this->makeClass($this->makeProfessor(), ['requires_approval' => true]);
        $student = $this->makeStudent();

        $this->asStudent($student)->post(route('student.classes.join.store'), [
            'invite_code' => $class->invite_code,
        ])->assertRedirect(route('student.classes.index'));

        $this->assertDatabaseHas('class_students', [
            'school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'pending',
        ]);
    }

    public function test_join_without_approval_sets_active(): void
    {
        $class = $this->makeClass($this->makeProfessor(), ['requires_approval' => false]);
        $student = $this->makeStudent();

        $this->asStudent($student)->post(route('student.classes.join.store'), [
            'invite_code' => $class->invite_code,
        ])->assertRedirect();

        $this->assertDatabaseHas('class_students', [
            'school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active',
        ]);
    }

    public function test_disabled_code_is_rejected_non_enumerable(): void
    {
        $class = $this->makeClass($this->makeProfessor(), ['invite_enabled' => false]);
        $student = $this->makeStudent();

        $this->asStudent($student)->post(route('student.classes.join.store'), [
            'invite_code' => $class->invite_code,
        ])->assertSessionHasErrors(['invite_code' => 'Codice non valido o non più attivo.']);

        $this->assertDatabaseMissing('class_students', [
            'school_class_id' => $class->id, 'student_id' => $student->id,
        ]);
    }

    public function test_archived_class_is_rejected(): void
    {
        $class = $this->makeClass($this->makeProfessor(), ['is_archived' => true]);
        $student = $this->makeStudent();

        $this->asStudent($student)->post(route('student.classes.join.store'), [
            'invite_code' => $class->invite_code,
        ])->assertSessionHasErrors(['invite_code' => 'Codice non valido o non più attivo.']);

        $this->assertDatabaseMissing('class_students', [
            'school_class_id' => $class->id, 'student_id' => $student->id,
        ]);
    }

    public function test_regenerate_invalidates_previous_code(): void
    {
        $prof = $this->makeProfessor();
        $class = $this->makeClass($prof, ['requires_approval' => false]);
        $oldCode = $class->invite_code;

        $this->asStudent($prof)->post(route('docente.classes.regenerate-code', $class))->assertRedirect();

        $class->refresh();
        $this->assertNotSame($oldCode, $class->invite_code);

        // Il vecchio codice non funziona più
        $student = $this->makeStudent();
        $this->asStudent($student)->post(route('student.classes.join.store'), [
            'invite_code' => $oldCode,
        ])->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('class_students', ['student_id' => $student->id]);

        // Il nuovo codice funziona
        $student2 = $this->makeStudent();
        $this->asStudent($student2)->post(route('student.classes.join.store'), [
            'invite_code' => $class->invite_code,
        ])->assertRedirect();
        $this->assertDatabaseHas('class_students', ['student_id' => $student2->id, 'status' => 'active']);
    }

    public function test_teacher_isolation(): void
    {
        $a = $this->makeProfessor();
        $b = $this->makeProfessor();
        $classA = $this->makeClass($a);

        // B non vede né modifica la classe di A
        $this->asStudent($b)->get(route('docente.classes.show', $classA))->assertForbidden();
        $this->asStudent($b)->patch(route('docente.classes.update', $classA), [
            'name' => 'Hack',
        ])->assertForbidden();
        $this->asStudent($b)->post(route('docente.classes.regenerate-code', $classA))->assertForbidden();

        // A invece può
        $this->asStudent($a)->get(route('docente.classes.show', $classA))->assertOk();
    }

    public function test_roster_approve_and_remove_owner_only(): void
    {
        $prof = $this->makeProfessor();
        $class = $this->makeClass($prof);
        $student = $this->makeStudent();
        $enr = ClassStudent::create([
            'school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'pending',
        ]);

        // Docente estraneo non può
        $this->asStudent($this->makeProfessor())
            ->patch(route('docente.classes.roster.update', [$class, $enr]), ['action' => 'approve'])
            ->assertForbidden();

        // Il proprietario approva
        $this->asStudent($prof)
            ->patch(route('docente.classes.roster.update', [$class, $enr]), ['action' => 'approve'])
            ->assertRedirect();
        $this->assertDatabaseHas('class_students', ['id' => $enr->id, 'status' => 'active']);
    }

    public function test_birth_date_required_in_code_registration_flow(): void
    {
        $class = $this->makeClass($this->makeProfessor(), ['requires_approval' => false]);

        // Guest (nessuna sessione) registra senza birth_date → errore
        $this->post(route('student.classes.join.store'), [
            'invite_code' => $class->invite_code,
            'name' => 'Nuovo Studente',
            'email' => 'nuovo+' . uniqid() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('birth_date');

        $this->assertDatabaseMissing('class_students', ['school_class_id' => $class->id]);
    }

    public function test_guest_registration_with_code_creates_student_and_enrolls(): void
    {
        $class = $this->makeClass($this->makeProfessor(), ['requires_approval' => false]);
        $email = 'nuovo+' . uniqid() . '@example.com';

        $this->post(route('student.classes.join.store'), [
            'invite_code' => $class->invite_code,
            'name' => 'Nuovo Studente', 'email' => $email,
            'password' => 'password123', 'password_confirmation' => 'password123',
            'birth_date' => '2008-04-15',
        ])->assertRedirect();

        $this->assertDatabaseHas('students', ['email' => $email, 'role' => 'student']);
        $student = Student::where('email', $email)->first();
        $this->assertNotNull($student->birth_date);
        $this->assertDatabaseHas('class_students', [
            'school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active',
        ]);
    }

    public function test_join_throttle(): void
    {
        $student = $this->makeStudent();

        // 8 tentativi consentiti/min, il 9° è bloccato dal throttle
        for ($i = 0; $i < 8; $i++) {
            $this->asStudent($student)->post(route('student.classes.join.store'), ['invite_code' => 'BADCODE']);
        }
        $this->asStudent($student)->post(route('student.classes.join.store'), ['invite_code' => 'BADCODE'])
            ->assertSessionHasErrors(['invite_code' => 'Troppi tentativi. Riprova tra un minuto.']);
    }
}
