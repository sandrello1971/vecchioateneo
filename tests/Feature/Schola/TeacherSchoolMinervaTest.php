<?php

namespace Tests\Feature\Schola;

use App\Models\DocumentRag;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherSchoolMinervaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        atheneum_setting_put('rag_vector_enabled_schola', false); // ILIKE deterministico
    }

    private function school(): School
    {
        return School::create(['name' => 'L', 'slug' => 's-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
    }

    private function prof(?School $s = null): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => 'p+' . uniqid() . '@e.com', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $s?->id, 'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function klass(School $s, string $name): SchoolClass
    {
        return SchoolClass::create([
            'school_id' => $s->id, 'name' => $name, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => false,
            'requires_approval' => false, 'is_archived' => false,
        ]);
    }

    private function chunk(array $cols): DocumentRag
    {
        return DocumentRag::create(array_merge([
            'title' => 'Fonte', 'content' => 'La energia.', 'chunk_index' => 0, 'metadata' => [],
        ], $cols));
    }

    public function test_scope_includes_own_taught_and_library_excludes_others(): void
    {
        $school = $this->school();
        $subject = Subject::create(['name' => 'Fisica ' . uniqid(), 'is_custom' => true]);
        $teacher = $this->prof($school);
        $other = $this->prof($school);

        $c1 = $this->klass($school, '5A');   // insegnata
        $c2 = $this->klass($school, '5B');   // NON insegnata
        TeachingAssignment::create([
            'school_id' => $school->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'school_class_id' => $c1->id, 'school_year' => '2026/2027',
        ]);

        $own = $this->chunk(['scope' => 'teacher_private', 'teacher_id' => $teacher->id, 'title' => 'Mio']);
        $taught = $this->chunk(['scope' => 'class', 'school_class_id' => $c1->id, 'title' => 'ClasseInsegnata']);
        $notTaught = $this->chunk(['scope' => 'class', 'school_class_id' => $c2->id, 'title' => 'ClasseNonInsegnata']);
        $otherPriv = $this->chunk(['scope' => 'teacher_private', 'teacher_id' => $other->id, 'title' => 'Altrui']);
        $library = $this->chunk([
            'scope' => 'teacher_shared', 'teacher_id' => $other->id, 'title' => 'Biblioteca',
            'metadata' => ['share_scope' => 'all', 'school_id' => $school->id, 'document_id' => 'd1'],
        ]);

        // classIds come nel controller: cattedre + classi possedute.
        $classIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('school_class_id')
            ->merge(SchoolClass::where('teacher_id', $teacher->id)->pluck('id'))->unique()->values()->all();

        $ids = app(RagService::class)
            ->searchClassScoped('energia', $classIds, $teacher->id, 20, null, true)
            ->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertContains($taught->id, $ids);
        $this->assertContains($library->id, $ids);
        $this->assertNotContains($notTaught->id, $ids, 'Classe non insegnata: fuori scope');
        $this->assertNotContains($otherPriv->id, $ids, 'Bozze private di altri docenti: escluse');
    }

    public function test_endpoint_requires_professor(): void
    {
        $student = Student::create([
            'name' => 'S', 'email' => 's+' . uniqid() . '@e.com', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->withSession(['student_id' => $student->id])
            ->post(route('docente.minerva.ask'), ['question' => 'ciao'])
            ->assertForbidden();
    }

    public function test_endpoint_answers_with_sources(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Risposta di prova.']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200)]);

        $school = $this->school();
        $teacher = $this->prof($school);
        $this->chunk(['scope' => 'teacher_private', 'teacher_id' => $teacher->id, 'title' => 'Mio materiale']);

        $res = $this->withSession(['student_id' => $teacher->id, 'student_name' => $teacher->name])
            ->post(route('docente.minerva.ask'), ['question' => 'energia'])
            ->assertOk()->json();

        $this->assertSame('answered', $res['gate']);
        $this->assertNotEmpty($res['sources']);
    }
}
