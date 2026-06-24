<?php

namespace Tests\Feature;

use App\Mail\StudentWelcomeMail;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InstructorMultiRoleTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name'                 => 'Mario Rossi',
            'email'                => 'mario+' . uniqid() . '@example.com',
            'password'             => bcrypt('secret-pw'),
            'is_active'            => true,
            'must_change_password' => false,
        ], $attrs));
    }

    private function actingAsAdmin(): self
    {
        return $this->withSession([
            'admin_logged_in' => true,
            'admin_email'     => 'admin@example.com',
        ]);
    }

    // ---- Modello: capacità formatore ----

    public function test_is_instructor_true_via_legacy_role(): void
    {
        $s = $this->makeStudent(['role' => 'instructor']);
        $this->assertTrue($s->isInstructor());
    }

    public function test_is_instructor_true_via_flag_with_other_role(): void
    {
        // Docente Schola E formatore insieme.
        $s = $this->makeStudent(['role' => 'professor', 'is_instructor' => true]);
        $this->assertTrue($s->isInstructor());
        $this->assertTrue($s->isProfessor());
    }

    public function test_is_instructor_false_by_default(): void
    {
        $s = $this->makeStudent(['role' => 'student']);
        $this->assertFalse($s->isInstructor());
    }

    // ---- Pagina "Aggiungi formatore": crea ----

    public function test_store_creates_new_instructor_and_sends_invite(): void
    {
        Mail::fake();

        $this->actingAsAdmin()
            ->post(route('admin.instructors.store'), [
                'name'       => 'Nuova Formatrice',
                'email'      => 'nuova.formatrice@example.com',
                'send_email' => '1',
            ])
            ->assertRedirect();

        $s = Student::where('email', 'nuova.formatrice@example.com')->first();
        $this->assertNotNull($s);
        $this->assertSame('instructor', $s->role);
        $this->assertTrue($s->is_instructor);
        $this->assertTrue($s->must_change_password);
        $this->assertTrue($s->isInstructor());

        Mail::assertSent(StudentWelcomeMail::class);
    }

    public function test_store_does_not_send_email_when_flag_off(): void
    {
        Mail::fake();

        $this->actingAsAdmin()->post(route('admin.instructors.store'), [
            'name'  => 'Senza Mail',
            'email' => 'senza.mail@example.com',
        ])->assertRedirect();

        Mail::assertNothingSent();
    }

    // ---- Pagina "Aggiungi formatore": promuovi esistente (caso ste@) ----

    public function test_store_promotes_existing_professor_without_duplicate(): void
    {
        Mail::fake();
        $prof = $this->makeStudent([
            'email' => 'ste@example.me',
            'role'  => 'professor',
        ]);

        $this->actingAsAdmin()->post(route('admin.instructors.store'), [
            'name'  => 'Stefano',
            'email' => 'ste@example.me',
        ])->assertRedirect(route('admin.instructors.show', $prof));

        // Nessun duplicato.
        $this->assertSame(1, Student::where('email', 'ste@example.me')->count());

        $prof->refresh();
        $this->assertTrue($prof->is_instructor);   // capacità aggiunta
        $this->assertSame('professor', $prof->role); // ruolo preservato
        $this->assertTrue($prof->isInstructor());
        $this->assertTrue($prof->isProfessor());

        // Nessuna mail di benvenuto/credenziali su promozione.
        Mail::assertNothingSent();
    }

    public function test_store_reactivates_inactive_existing_account(): void
    {
        $s = $this->makeStudent(['email' => 'dormiente@example.com', 'role' => 'student', 'is_active' => false]);

        $this->actingAsAdmin()->post(route('admin.instructors.store'), [
            'name'  => 'Dormiente',
            'email' => 'dormiente@example.com',
        ])->assertRedirect();

        $s->refresh();
        $this->assertTrue($s->is_active);
        $this->assertTrue($s->is_instructor);
    }

    // ---- Guard show non va 404 per professor+formatore ----

    public function test_show_does_not_404_for_professor_with_instructor_capability(): void
    {
        $s = $this->makeStudent(['role' => 'professor', 'is_instructor' => true]);

        $this->actingAsAdmin()
            ->get(route('admin.instructors.show', $s))
            ->assertOk();
    }

    public function test_show_404_for_plain_student(): void
    {
        $s = $this->makeStudent(['role' => 'student']);

        $this->actingAsAdmin()
            ->get(route('admin.instructors.show', $s))
            ->assertNotFound();
    }

    // ---- Lista formatori include i promossi via flag ----

    public function test_index_includes_flag_only_instructor(): void
    {
        $flagOnly = $this->makeStudent([
            'name'          => 'Promosso Flag',
            'role'          => 'professor',
            'is_instructor' => true,
        ]);

        $this->actingAsAdmin()
            ->get(route('admin.instructors.index'))
            ->assertOk()
            ->assertSee('Promosso Flag');
    }

    // ---- Permessi sistema: toggle capacità + anti-lockout ----

    public function test_update_system_role_toggles_instructor_capability(): void
    {
        $s = $this->makeStudent(['role' => 'professor']);

        $this->actingAsAdmin()->patch(route('admin.students.update-system-role', $s->id), [
            'role'          => 'professor',
            'is_instructor' => '1',
        ])->assertRedirect();
        $this->assertTrue($s->fresh()->is_instructor);

        $this->actingAsAdmin()->patch(route('admin.students.update-system-role', $s->id), [
            'role' => 'professor',
        ])->assertRedirect();
        $this->assertFalse($s->fresh()->is_instructor);
    }

    public function test_self_lockout_guard_blocks_admin_demoting_self(): void
    {
        $admin = $this->makeStudent(['email' => 'admin@example.com', 'role' => 'admin']);

        $this->actingAsAdmin()->patch(route('admin.students.update-system-role', $admin->id), [
            'role' => 'student',
        ]);

        // Ruolo admin preservato (anti-lockout).
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
