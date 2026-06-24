<?php

namespace Tests\Feature\Schola;

use App\Models\Course;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Identità multi-contesto: un account (una email) può essere SIMULTANEAMENTE
 * corsista, professore (libero o di scuola) e segreteria, senza che un ruolo
 * cancelli l'altro. Matrice gate + aggancio + switch di contesto.
 */
class IdentityMultiContextTest extends TestCase
{
    use RefreshDatabase;

    private Subject $fisica;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Mail::fake();
        $this->fisica = Subject::firstOrCreate(['name' => 'Fisica']);
    }

    private function school(string $name = 'Liceo'): School
    {
        return School::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'type' => 'liceo', 'status' => 'active']);
    }

    private function make(array $attrs): Student
    {
        return Student::create(array_merge([
            'name' => 'U', 'email' => 'u' . uniqid() . '@e.it', 'password' => bcrypt('secret123'),
            'is_active' => true, 'must_change_password' => false,
        ], $attrs));
    }

    private function course(): Course
    {
        return Course::create(['name' => 'C' . uniqid(), 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function enrollCourse(Student $s): void
    {
        $s->courses()->attach($this->course()->id, ['enrolled_at' => now(), 'is_active' => true]);
    }

    private function schoolClass(School $school): SchoolClass
    {
        return SchoolClass::create(['school_id' => $school->id, 'teacher_id' => null, 'name' => '3A', 'subject_id' => null,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);
    }

    private function as(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    // ===== matrice dei gate =====

    public function test_gate_matrix(): void
    {
        // corsista puro
        $corsista = $this->make(['role' => 'student']);
        $this->enrollCourse($corsista);
        $this->as($corsista)->get(route('student.dashboard'))->assertOk();
        $this->as($corsista)->get(route('docente.dashboard'))->assertForbidden();
        $this->as($corsista)->get(route('scuola.dashboard'))->assertForbidden();

        // professore libero
        $libero = $this->make(['role' => 'professor', 'school_id' => null]);
        $this->as($libero)->get(route('docente.dashboard'))->assertOk();
        $this->as($libero)->get(route('scuola.dashboard'))->assertForbidden();
        $this->as($libero)->get(route('student.dashboard'))->assertOk(); // /learn è solo-sessione

        // professore di scuola (con cattedra)
        $school = $this->school();
        $profScuola = $this->make(['role' => 'professor', 'school_id' => $school->id]);
        $class = $this->schoolClass($school);
        TeachingAssignment::create(['school_id' => $school->id, 'teacher_id' => $profScuola->id,
            'subject_id' => $this->fisica->id, 'school_class_id' => $class->id, 'school_year' => '2026/2027']);
        $this->as($profScuola)->get(route('docente.dashboard'))->assertOk();

        // segreteria (flag)
        $segr = $this->make(['role' => null, 'is_secretary' => true, 'school_id' => $school->id]);
        $this->as($segr)->get(route('scuola.dashboard'))->assertOk();
        $this->as($segr)->get(route('docente.dashboard'))->assertForbidden();
    }

    public function test_corsista_and_professor_together(): void
    {
        $both = $this->make(['role' => 'professor', 'school_id' => null]);
        $this->enrollCourse($both);

        $this->as($both)->get(route('docente.dashboard'))->assertOk();      // professore
        $this->as($both)->get(route('student.dashboard'))->assertOk();      // corsista
        $this->assertTrue($both->fresh()->hasCourseAccess());
    }

    // ===== professore + segreteria insieme (la limitazione rimossa) =====

    public function test_professor_and_secretary_together(): void
    {
        $school = $this->school();
        // un professore esistente
        $prof = $this->make(['role' => 'professor', 'school_id' => $school->id]);
        $admin = $this->make(['role' => null, 'is_secretary' => true, 'school_id' => $school->id]);

        // l'admin piattaforma aggancia la segreteria all'account del professore
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@e.it'])
            ->post(route('admin.scuole.nominate', $school), ['name' => $prof->name, 'email' => $prof->email])
            ->assertRedirect();

        $prof->refresh();
        $this->assertSame('professor', $prof->role);   // role professore preservato
        $this->assertTrue((bool) $prof->is_secretary);  // + capacità segreteria

        $this->as($prof)->get(route('docente.dashboard'))->assertOk();  // entrambi i contesti
        $this->as($prof)->get(route('scuola.dashboard'))->assertOk();
    }

    public function test_professor_secretary_corsista_all_three(): void
    {
        $school = $this->school();
        $u = $this->make(['role' => 'professor', 'school_id' => $school->id, 'is_secretary' => true]);
        $this->enrollCourse($u);

        $this->as($u)->get(route('docente.dashboard'))->assertOk();
        $this->as($u)->get(route('scuola.dashboard'))->assertOk();
        $this->as($u)->get(route('student.dashboard'))->assertOk();
    }

    // ===== aggancio (import/single) preserva la corsista-ness =====

    public function test_add_teacher_attaches_to_existing_corsista_preserving_courses(): void
    {
        $school = $this->school();
        $admin = $this->make(['role' => null, 'is_secretary' => true, 'school_id' => $school->id]);
        $corsista = $this->make(['role' => 'student', 'email' => 'corsista@x.it', 'school_id' => null]);
        $this->enrollCourse($corsista);
        $courseCount = $corsista->courses()->count();

        $this->as($admin)->post(route('scuola.docenti.store'), [
            'nome' => 'Cors', 'cognome' => 'Ista', 'email' => 'corsista@x.it', 'materie' => [$this->fisica->id],
        ])->assertRedirect(route('scuola.docenti.index'));

        $corsista->refresh();
        $this->assertSame('professor', $corsista->role);            // promosso a docente
        $this->assertSame($school->id, $corsista->school_id);       // agganciato alla scuola
        $this->assertSame($courseCount, $corsista->courses()->count()); // iscrizioni corsi PRESERVATE
        $this->assertEqualsCanonicalizing(['Fisica'], $corsista->teachableSubjects->pluck('name')->all());
        $this->as($corsista)->get(route('docente.dashboard'))->assertOk();
    }

    public function test_add_teacher_blocks_only_other_school(): void
    {
        $schoolA = $this->school('A'); $schoolB = $this->school('B');
        $admin = $this->make(['role' => null, 'is_secretary' => true, 'school_id' => $schoolA->id]);
        $this->make(['role' => 'professor', 'email' => 'altrove@x.it', 'school_id' => $schoolB->id]);

        $this->as($admin)->from(route('scuola.docenti.create'))->post(route('scuola.docenti.store'), [
            'nome' => 'X', 'cognome' => 'Y', 'email' => 'altrove@x.it',
        ])->assertRedirect(route('scuola.docenti.create'))->assertSessionHas('error');
    }

    public function test_add_student_attaches_to_existing_corsista(): void
    {
        $school = $this->school();
        $admin = $this->make(['role' => null, 'is_secretary' => true, 'school_id' => $school->id]);
        $class = $this->schoolClass($school);
        $corsista = $this->make(['role' => 'student', 'email' => 'cor2@x.it', 'school_id' => null]);
        $this->enrollCourse($corsista);
        $courseCount = $corsista->courses()->count();

        $this->as($admin)->post(route('scuola.studenti.store'), [
            'nome' => 'Cor', 'cognome' => 'Due', 'email' => 'cor2@x.it',
            'data_nascita' => '2010-01-01', 'class_id' => $class->id,
        ])->assertRedirect(route('scuola.studenti.index'));

        $corsista->refresh();
        $this->assertSame($school->id, $corsista->school_id);
        $this->assertSame($courseCount, $corsista->courses()->count());
        $this->assertSame('active', \App\Models\ClassStudent::where('student_id', $corsista->id)->first()->status);
    }

    // ===== switch di contesto nei layout =====

    public function test_context_switch_links_present(): void
    {
        $school = $this->school();
        $u = $this->make(['role' => 'professor', 'school_id' => $school->id, 'is_secretary' => true]);
        $this->enrollCourse($u);

        $this->as($u)->get(route('docente.dashboard'))->assertOk()->assertSee('I miei corsi')->assertSee('Segreteria');
        $this->as($u)->get(route('student.dashboard'))->assertOk()->assertSee('Area docente');
        $this->as($u)->get(route('scuola.dashboard'))->assertOk()->assertSee('Area docente');
    }

    // ===== promote blindato =====

    public function test_promote_to_professor_preserves_courses(): void
    {
        $corsista = $this->make(['role' => 'student']);
        $this->enrollCourse($corsista);
        $count = $corsista->courses()->count();

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@e.it'])
            ->patch(route('admin.students.update-system-role', $corsista), ['role' => 'professor'])
            ->assertRedirect();

        $corsista->refresh();
        $this->assertSame('professor', $corsista->role);
        $this->assertSame($count, $corsista->courses()->count()); // corsista-ness preservata
        $this->as($corsista)->get(route('docente.dashboard'))->assertOk();
    }

    // ===== redirect post-login =====

    public function test_login_redirect_per_context(): void
    {
        $school = $this->school();
        $cases = [
            [$this->make(['role' => 'student', 'email' => 'l1@x.it']), 'student.dashboard'],
            [$this->make(['role' => 'professor', 'email' => 'l2@x.it']), 'docente.dashboard'],
            [$this->make(['role' => null, 'is_secretary' => true, 'school_id' => $school->id, 'email' => 'l3@x.it']), 'scuola.dashboard'],
        ];
        foreach ($cases as [$u, $route]) {
            $this->flushSession();
            $this->post(route('student.login.post'), ['email' => $u->email, 'password' => 'secret123'])
                ->assertRedirect(route($route));
        }
    }
}
