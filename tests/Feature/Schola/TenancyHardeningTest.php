<?php

namespace Tests\Feature\Schola;

use App\Models\ClassStudent;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * IDOR sweep multi-scuola (P16, §2): uno school_admin / docente di una scuola
 * non deve MAI leggere o mutare risorse di un'altra scuola.
 */
class TenancyHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $name): School
    {
        return School::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'type' => 'liceo', 'status' => 'active']);
    }

    private function member(School $s, string $role): Student
    {
        // Segreteria = flag (non role) dal pacchetto identità multi-contesto.
        $secretary = $role === 'school_admin';
        return Student::create(['name' => ucfirst($role), 'email' => $role . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => $secretary ? null : $role, 'is_secretary' => $secretary,
            'school_id' => $s->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function as(Student $u): self
    {
        return $this->withSession(['student_id' => $u->id, 'student_name' => $u->name, 'student_email' => $u->email]);
    }

    /** Costruisce una scuola "A" completa di risorse parametrizzate. */
    private function fullSchool(): array
    {
        $fisica = Subject::firstOrCreate(['name' => 'Fisica']);
        $school = $this->school('Scuola A');
        $admin = $this->member($school, 'school_admin');
        $teacher = $this->member($school, 'professor');
        $student = $this->member($school, 'student');

        $class = SchoolClass::create(['school_id' => $school->id, 'teacher_id' => null, 'name' => '3A', 'subject_id' => null,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active', 'approved_at' => now()]);
        $cattedra = TeachingAssignment::create(['school_id' => $school->id, 'teacher_id' => $teacher->id,
            'subject_id' => $fisica->id, 'school_class_id' => $class->id, 'school_year' => '2026/2027']);

        $profBatch = ImportBatch::create(['school_id' => $school->id, 'created_by' => $admin->id, 'type' => 'professors',
            'status' => 'previewed', 'summary' => [], 'rows' => []]);
        $stuBatch = ImportBatch::create(['school_id' => $school->id, 'created_by' => $admin->id, 'type' => 'students',
            'status' => 'previewed', 'summary' => [], 'rows' => []]);

        $school->update(['settings' => ['logo_path' => 'school-logos/' . $school->id . '/logo.png']]);
        Storage::disk('local')->put('school-logos/' . $school->id . '/logo.png', 'PNGDATA');

        return compact('school', 'admin', 'teacher', 'student', 'class', 'cattedra', 'fisica', 'profBatch', 'stuBatch');
    }

    public function test_foreign_school_admin_cannot_touch_school_a_resources(): void
    {
        $a = $this->fullSchool();
        $bSchool = $this->school('Scuola B');
        $b = $this->member($bSchool, 'school_admin');

        // GET parametrizzati
        $this->as($b)->get(route('scuola.classi.show', $a['class']))->assertForbidden();
        $this->as($b)->get(route('scuola.logo', $a['school']))->assertForbidden();
        $this->as($b)->get(route('scuola.studenti.import.result', $a['stuBatch']))->assertForbidden();
        $this->as($b)->get(route('scuola.studenti.import.credentials', $a['stuBatch']))->assertForbidden();

        // Mutazioni parametrizzate
        $this->as($b)->patch(route('scuola.classi.update', $a['class']), ['name' => 'hack', 'school_year' => '2026/2027'])->assertForbidden();
        $this->as($b)->post(route('scuola.classi.students', $a['class']), ['action' => 'add', 'student_id' => $a['student']->id])->assertForbidden();
        $this->as($b)->post(route('scuola.classi.cattedre.store', $a['class']), ['teacher_id' => $a['teacher']->id, 'subject_id' => $a['fisica']->id])->assertForbidden();
        $this->as($b)->delete(route('scuola.cattedre.destroy', $a['cattedra']))->assertForbidden();
        $this->as($b)->post(route('scuola.docenti.import.commit'), ['batch_id' => $a['profBatch']->id, 'duplicate_action' => 'update'])->assertForbidden();
        $this->as($b)->post(route('scuola.docenti.import.discard', $a['profBatch']))->assertForbidden();
        $this->as($b)->post(route('scuola.studenti.import.commit'), ['batch_id' => $a['stuBatch']->id, 'duplicate_action' => 'update'])->assertForbidden();
        $this->as($b)->post(route('scuola.studenti.import.discard', $a['stuBatch']))->assertForbidden();

        // Stato invariato dopo gli attacchi
        $this->assertSame('3A', $a['class']->fresh()->name);
        $this->assertNotNull($a['cattedra']->fresh());
    }

    public function test_non_parameterized_lists_are_scoped_to_own_school(): void
    {
        $a = $this->fullSchool();
        $bSchool = $this->school('Scuola B');
        $b = $this->member($bSchool, 'school_admin');
        $this->member($bSchool, 'professor'); // un docente di B

        // L'admin di B vede solo i propri docenti/classi, non quelli di A.
        $this->as($b)->get(route('scuola.docenti.index'))->assertOk()->assertDontSee($a['teacher']->email);
        $this->as($b)->get(route('scuola.classi.index'))->assertOk()->assertDontSee('3A');
        $this->as($b)->get(route('scuola.privacy.index'))->assertOk(); // audit import della sola B
    }

    public function test_school_teacher_cannot_see_or_publish_other_school_class(): void
    {
        $a = $this->fullSchool();
        $bSchool = $this->school('Scuola B');
        $teacherB = $this->member($bSchool, 'professor');

        // Indice classi di B: non vede la 3A di A
        $this->as($teacherB)->get(route('docente.classes.index'))->assertOk()->assertDontSee('3A');
        // Vista classe di A → 403 (niente cattedra)
        $this->as($teacherB)->get(route('docente.classes.show', $a['class']))->assertForbidden();
        // Minerva classe di A → 403
        $this->as($teacherB)->get(route('docente.classes.minerva', $a['class']))->assertForbidden();
        // Pubblicazione su classe di A → 403
        $art = TeachingArtifact::create(['teacher_id' => $teacherB->id, 'type' => 'summary', 'title' => 'X',
            'content' => 'c', 'status' => 'ready']);
        $this->as($teacherB)->from(route('docente.artifacts.show', $art))
            ->post(route('docente.artifacts.publish', $art), ['class_ids' => [$a['class']->id]])->assertForbidden();
    }
}
