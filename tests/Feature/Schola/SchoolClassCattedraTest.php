<?php

namespace Tests\Feature\Schola;

use App\Models\ArtifactPublication;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SchoolClassCattedraTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Subject $fisica;
    private Subject $storia;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake(); // i job di ingestion RAG non devono girare
        $this->school = School::create(['name' => 'Liceo', 'slug' => 'liceo-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $this->fisica = Subject::firstOrCreate(['name' => 'Fisica']);
        $this->storia = Subject::firstOrCreate(['name' => 'Storia']);
    }

    private function prof(?School $school = null): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $school?->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolAdmin(?School $school = null): Student
    {
        $school ??= $this->school;
        return Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $school->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(?School $school = null): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'school_id' => $school?->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolClass(?School $school = null, string $name = '3A'): SchoolClass
    {
        $school ??= $this->school;
        return SchoolClass::create(['school_id' => $school->id, 'teacher_id' => null, 'name' => $name, 'subject_id' => null,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);
    }

    private function freeClass(Student $owner, string $name = 'Libera'): SchoolClass
    {
        return SchoolClass::create(['school_id' => null, 'teacher_id' => $owner->id, 'name' => $name, 'subject_id' => $this->fisica->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false]);
    }

    private function cattedra(Student $teacher, SchoolClass $class, ?Subject $subject = null): TeachingAssignment
    {
        return TeachingAssignment::create(['school_id' => $class->school_id, 'teacher_id' => $teacher->id,
            'subject_id' => ($subject ?? $this->fisica)->id, 'school_class_id' => $class->id, 'school_year' => $class->school_year]);
    }

    private function artifact(Student $teacher, ?Subject $subject = null): TeachingArtifact
    {
        return TeachingArtifact::create(['teacher_id' => $teacher->id, 'type' => 'summary', 'title' => 'A',
            'content' => 'c', 'status' => 'ready', 'subject_id' => ($subject ?? $this->fisica)->id]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function asAdmin(Student $a): self
    {
        return $this->withSession(['student_id' => $a->id, 'student_name' => $a->name, 'student_email' => $a->email]);
    }

    private function publish(Student $prof, TeachingArtifact $art, array $classIds): \Illuminate\Testing\TestResponse
    {
        return $this->asProf($prof)->from(route('docente.artifacts.show', $art))
            ->post(route('docente.artifacts.publish', $art), ['class_ids' => $classIds]);
    }

    // ===== cattedre (segreteria) =====

    public function test_secretary_assigns_and_removes_cattedra(): void
    {
        $admin = $this->schoolAdmin();
        $prof = $this->prof($this->school);
        $class = $this->schoolClass();

        $this->asAdmin($admin)->post(route('scuola.classi.cattedre.store', $class), [
            'teacher_id' => $prof->id, 'subject_id' => $this->fisica->id,
        ])->assertRedirect();

        $a = TeachingAssignment::where('teacher_id', $prof->id)->where('school_class_id', $class->id)->first();
        $this->assertNotNull($a);

        $this->asAdmin($admin)->delete(route('scuola.cattedre.destroy', $a))->assertRedirect();
        $this->assertNull($a->fresh());
    }

    public function test_secretary_assigns_and_removes_students_in_roster(): void
    {
        $admin = $this->schoolAdmin();
        $class = $this->schoolClass();
        $stu = $this->student($this->school);

        $this->asAdmin($admin)->post(route('scuola.classi.students', $class), ['action' => 'add', 'student_id' => $stu->id])->assertRedirect();
        $this->assertSame('active', ClassStudent::where('school_class_id', $class->id)->where('student_id', $stu->id)->first()->status);

        $this->asAdmin($admin)->post(route('scuola.classi.students', $class), ['action' => 'remove', 'student_id' => $stu->id])->assertRedirect();
        $this->assertNull(ClassStudent::where('school_class_id', $class->id)->where('student_id', $stu->id)->first());
    }

    // ===== docente vede solo classi-cattedra =====

    public function test_teacher_sees_only_classes_with_cattedra(): void
    {
        $prof = $this->prof($this->school);
        $withCattedra = $this->schoolClass($this->school, 'Mia 3A');
        $withoutCattedra = $this->schoolClass($this->school, 'Altrui 5B');
        $this->cattedra($prof, $withCattedra);

        $this->asProf($prof)->get(route('docente.classes.index'))->assertOk()
            ->assertSee('Mia 3A')->assertDontSee('Altrui 5B');
    }

    // ===== pubblicazione: cattedra vs 403 =====

    public function test_publish_to_school_class_requires_cattedra(): void
    {
        $prof = $this->prof($this->school);
        $class = $this->schoolClass();
        $art = $this->artifact($prof);

        // senza cattedra → 403
        $this->publish($prof, $art, [$class->id])->assertForbidden();
        $this->assertSame(0, ArtifactPublication::count());

        // con cattedra → ok
        $this->cattedra($prof, $class);
        $this->publish($prof, $art, [$class->id])->assertRedirect();
        $this->assertSame(1, ArtifactPublication::where('school_class_id', $class->id)->count());
    }

    public function test_publish_subject_mismatch_warns_but_publishes(): void
    {
        $prof = $this->prof($this->school);
        $class = $this->schoolClass();
        $this->cattedra($prof, $class, $this->fisica);          // cattedra di Fisica
        $art = $this->artifact($prof, $this->storia);            // artefatto di Storia

        $this->publish($prof, $art, [$class->id])
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(1, ArtifactPublication::count());      // pubblica comunque
    }

    // ===== classe LIBERA: proprietà invariata (regressione fetta 1) =====

    public function test_free_class_publish_is_ownership_based_unchanged(): void
    {
        $owner = $this->prof(null);          // docente libero
        $other = $this->prof(null);
        $ownClass = $this->freeClass($owner);
        $art = $this->artifact($owner);

        // proprietario → ok (nessuna cattedra coinvolta)
        $this->publish($owner, $art, [$ownClass->id])->assertRedirect();
        $this->assertSame(1, ArtifactPublication::count());

        // altro docente sulla classe libera altrui → 403
        $artOther = $this->artifact($other);
        $this->publish($other, $artOther, [$ownClass->id])->assertForbidden();
    }

    // ===== roster docente read-only su classi di scuola =====

    public function test_teacher_cannot_edit_school_class_roster(): void
    {
        $prof = $this->prof($this->school);
        $class = $this->schoolClass();
        $this->cattedra($prof, $class);
        $stu = $this->student($this->school);
        $enr = ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $stu->id, 'status' => 'active', 'approved_at' => now()]);

        // anche con cattedra, il roster delle classi di scuola è solo segreteria
        $this->asProf($prof)->patch(route('docente.classes.roster.update', [$class, $enr]), ['action' => 'remove'])
            ->assertForbidden();
    }

    public function test_free_class_roster_still_editable_by_owner(): void
    {
        $owner = $this->prof(null);
        $class = $this->freeClass($owner);
        $stu = $this->student(null);
        $enr = ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $stu->id, 'status' => 'pending']);

        $this->asProf($owner)->patch(route('docente.classes.roster.update', [$class, $enr]), ['action' => 'approve'])
            ->assertRedirect();
        $this->assertSame('active', $enr->fresh()->status);
    }

    // ===== Crea classe gate =====

    public function test_create_class_hidden_for_school_teacher_without_derogation(): void
    {
        $prof = $this->prof($this->school);
        // default allow_professor_create_classes = false
        $this->asProf($prof)->post(route('docente.classes.store'), [
            'name' => 'X', 'subject_id' => $this->fisica->id, 'school_year' => '2026/2027',
        ])->assertForbidden();

        // con deroga → consentito
        $this->school->update(['allow_professor_create_classes' => true]);
        $this->asProf($prof->fresh())->post(route('docente.classes.store'), [
            'name' => 'X', 'subject_id' => $this->fisica->id, 'school_year' => '2026/2027',
        ])->assertRedirect();
    }

    public function test_free_teacher_can_still_create_class(): void
    {
        $owner = $this->prof(null);
        $this->asProf($owner)->post(route('docente.classes.store'), [
            'name' => 'Libera', 'subject_id' => $this->fisica->id, 'school_year' => '2026/2027',
        ])->assertRedirect();
        $this->assertSame(1, SchoolClass::where('teacher_id', $owner->id)->count());
    }

    // ===== Minerva: cattedra dà accesso =====

    public function test_minerva_class_access_via_cattedra(): void
    {
        $prof = $this->prof($this->school);
        $class = $this->schoolClass();

        $this->asProf($prof)->get(route('docente.classes.minerva', $class))->assertForbidden();
        $this->cattedra($prof, $class);
        $this->asProf($prof)->get(route('docente.classes.minerva', $class))->assertOk();
    }

    // ===== tenancy multi-scuola =====

    public function test_tenancy_secretary_cannot_touch_other_school_class(): void
    {
        $class = $this->schoolClass(); // scuola A
        $otherSchool = School::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'type' => 'altro', 'status' => 'active']);
        $otherAdmin = $this->schoolAdmin($otherSchool);

        $this->asAdmin($otherAdmin)->get(route('scuola.classi.show', $class))->assertForbidden();
        $this->asAdmin($otherAdmin)->patch(route('scuola.classi.update', $class), ['name' => 'hack', 'school_year' => '2026/2027'])->assertForbidden();
        $this->asAdmin($otherAdmin)->post(route('scuola.classi.cattedre.store', $class), ['teacher_id' => $this->prof($this->school)->id, 'subject_id' => $this->fisica->id])->assertForbidden();
    }
}
