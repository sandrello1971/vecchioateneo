<?php

namespace Tests\Feature\Schola;

use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SchoolTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $name = 'Liceo Galilei'): School
    {
        return School::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'type' => 'liceo', 'status' => 'active']);
    }

    private function klass(School $s, string $name = '3A'): SchoolClass
    {
        $sub = Subject::firstOrCreate(['name' => 'Fisica']);
        return SchoolClass::create(['school_id' => $s->id, 'teacher_id' => null, 'name' => $name,
            'subject_id' => $sub->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => true, 'is_archived' => false]);
    }

    private function asAdmin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);
    }

    // ===== schema =====

    public function test_role_check_accepts_school_admin(): void
    {
        $school = $this->school();
        $a = Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $school->id, 'is_active' => true, 'must_change_password' => true]);
        $this->assertTrue($a->fresh()->isSchoolAdmin());
    }

    public function test_role_check_rejects_invalid_role(): void
    {
        $this->expectException(QueryException::class);
        Student::create(['name' => 'X', 'email' => 'x' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'bogus', 'is_active' => true, 'must_change_password' => false]);
    }

    public function test_school_class_teacher_id_is_nullable(): void
    {
        $class = $this->klass($this->school()); // teacher_id null
        $this->assertNull($class->fresh()->teacher_id);
    }

    // ===== tenancy: scope + access =====

    public function test_belongs_to_school_scope_isolates(): void
    {
        $a = $this->school('A'); $b = $this->school('B');
        $this->klass($a, 'A1'); $this->klass($a, 'A2'); $this->klass($b, 'B1');

        $this->assertSame(2, SchoolClass::forSchool($a->id)->count());
        $this->assertSame(1, SchoolClass::forSchool($b->id)->count());
        $this->assertEqualsCanonicalizing(['A1', 'A2'], SchoolClass::forSchool($a->id)->pluck('name')->all());
    }

    public function test_resolves_school_access_blocks_cross_school(): void
    {
        $a = $this->school('A'); $b = $this->school('B');
        $admin = Student::create(['name' => 'Segr A', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $a->id, 'is_active' => true, 'must_change_password' => false]);
        $this->withSession(['student_id' => $admin->id])->get('/'); // boot session
        session(['student_id' => $admin->id]);

        $probe = new class {
            use ResolvesSchoolAccess;
            public function schoolId() { return $this->currentSchoolId(); }
            public function same($r) { $this->assertSameSchool($r); }
        };

        $this->assertSame($a->id, $probe->schoolId());
        $probe->same((object) ['school_id' => $a->id]); // stessa scuola → ok

        $this->expectException(HttpException::class);
        $probe->same((object) ['school_id' => $b->id]); // altra scuola → 403
    }

    // ===== admin CRUD =====

    public function test_admin_creates_school(): void
    {
        $this->asAdmin()->post(route('admin.scuole.store'), [
            'name' => 'ITIS Fermi', 'type' => 'istituto_tecnico', 'city' => 'Roma',
        ])->assertRedirect();

        $school = School::where('name', 'ITIS Fermi')->first();
        $this->assertNotNull($school);
        $this->assertSame('itis-fermi', $school->slug);
        $this->assertSame('active', $school->status);
    }

    public function test_admin_nominates_first_school_admin(): void
    {
        $school = $this->school();

        $resp = $this->asAdmin()->post(route('admin.scuole.nominate', $school), [
            'name' => 'Maria Segreteria', 'email' => 'segreteria@galilei.it',
        ]);
        $resp->assertRedirect()->assertSessionHas('temp_password');

        $admin = Student::where('email', 'segreteria@galilei.it')->first();
        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->is_secretary); // segreteria = flag, non role
        $this->assertNull($admin->role);
        $this->assertSame($school->id, $admin->school_id);
        $this->assertTrue((bool) $admin->must_change_password);
    }

    public function test_admin_area_requires_admin_auth(): void
    {
        $this->get(route('admin.scuole.index'))->assertRedirect(); // no admin session → redirect login
    }

    public function test_nominate_attaches_secretary_to_existing_free_account(): void
    {
        // Identità multi-contesto: nominare la segreteria su un'email esistente
        // SENZA scuola (es. corsista) AGGANCIA il flag, non blocca.
        $school = $this->school();
        $existing = Student::create(['name' => 'Esiste', 'email' => 'dup@x.it', 'password' => bcrypt('secret123'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
        $oldHash = $existing->password;

        $this->asAdmin()->post(route('admin.scuole.nominate', $school), ['name' => 'Y', 'email' => 'dup@x.it'])
            ->assertRedirect(route('admin.scuole.show', $school));

        $existing->refresh();
        $this->assertTrue((bool) $existing->is_secretary);     // capacità segreteria agganciata
        $this->assertSame($school->id, $existing->school_id);
        $this->assertSame('student', $existing->role);         // role NON sovrascritto
        $this->assertSame($oldHash, $existing->password);      // password NON resettata

        // Email di un'ALTRA scuola → bloccata.
        $other = School::create(['name' => 'Altra', 'slug' => 'altra-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        Student::create(['name' => 'Z', 'email' => 'z@x.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $other->id, 'is_active' => true, 'must_change_password' => false]);
        $this->asAdmin()->post(route('admin.scuole.nominate', $school), ['name' => 'Z', 'email' => 'z@x.it'])
            ->assertRedirect()->assertSessionHas('error');
    }
}
