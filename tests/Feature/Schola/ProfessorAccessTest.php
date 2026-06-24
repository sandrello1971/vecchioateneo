<?php

namespace Tests\Feature\Schola;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pacchetto 2 — ruolo professor: gate /docente, promozione admin, e separazione
 * netta professor ≠ instructor (i due ruoli non si confondono mai).
 */
class ProfessorAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentWithRole(?string $role): Student
    {
        return Student::create([
            'name' => 'Utente ' . ($role ?? 'plain'),
            'email' => ($role ?? 'plain') . '+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false, // evita il redirect di student.password nei test di gate
        ]);
    }

    /** Sessione "loggato come studente" (auth Schola = sessione student_id). */
    private function actingAsStudent(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id,
            'student_name' => $s->name,
            'student_email' => $s->email,
        ]);
    }

    public function test_docente_denied_to_guest(): void
    {
        $this->get('/docente')->assertRedirect(route('student.login'));
    }

    public function test_docente_denied_to_plain_student(): void
    {
        $student = $this->makeStudentWithRole('student');
        $this->actingAsStudent($student)->get('/docente')->assertForbidden();
    }

    public function test_docente_denied_to_instructor(): void
    {
        $instructor = $this->makeStudentWithRole('instructor');
        $this->actingAsStudent($instructor)->get('/docente')->assertForbidden();
    }

    public function test_docente_allowed_to_professor(): void
    {
        $prof = $this->makeStudentWithRole('professor');
        $this->actingAsStudent($prof)->get('/docente')
            ->assertOk()
            ->assertSee('Benvenuto')
            ->assertSee($prof->name);
    }

    public function test_admin_can_promote_student_to_professor(): void
    {
        $student = $this->makeStudentWithRole('student');

        $this->withSession([
            'admin_logged_in' => true,
            'admin_email' => 'admin@example.com',
        ])->patch(route('admin.students.update-system-role', $student), [
            'role' => 'professor',
        ])->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'role' => 'professor',
        ]);
    }

    public function test_professor_login_redirects_to_docente(): void
    {
        $prof = $this->makeStudentWithRole('professor');
        $prof->update(['password' => bcrypt('secret-pw'), 'must_change_password' => false]);

        $this->post('/learn/login', [
            'email' => $prof->email,
            'password' => 'secret-pw',
        ])->assertRedirect(route('docente.dashboard'));
    }

    /**
     * SEPARAZIONE DEI MONDI: un professor NON è un instructor. Non deve ottenere
     * gli accessi formatore (Knowledge Base, Documenti discenti) né essere trattato
     * come instructor a livello di ruolo. Lo scope RAG instructor_only è imposto da
     * RagService (pacchetto 6); qui blindiamo ruolo + rotte.
     */
    public function test_professor_does_not_get_instructor_access(): void
    {
        $prof = $this->makeStudentWithRole('professor');

        $this->assertTrue($prof->isProfessor());
        $this->assertFalse($prof->isInstructor());
        $this->assertFalse($prof->isAdmin());

        // Aree instructor: gate abort_unless(role==='instructor') → 403 per il professor
        $this->actingAsStudent($prof)->get(route('student.knowledge_base.index'))->assertForbidden();
        $this->actingAsStudent($prof)->get(route('student.instructor_documents.index'))->assertForbidden();
    }

    public function test_instructor_is_not_treated_as_professor(): void
    {
        $instructor = $this->makeStudentWithRole('instructor');
        $this->assertFalse($instructor->isProfessor());
        $this->actingAsStudent($instructor)->get('/docente')->assertForbidden();
    }
}
