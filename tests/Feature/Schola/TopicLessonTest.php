<?php

namespace Tests\Feature\Schola;

use App\Models\Lesson;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingDocument;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

// Fase 3 (P18) — Argomenti, Lezioni, classificazione materiali.
// Struttura + proprietà/tenancy. Niente generazione (P19) né pubblicazione (P20).
class TopicLessonTest extends TestCase
{
    use RefreshDatabase;

    private Subject $storia;
    private Subject $fisica;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->storia = Subject::firstOrCreate(['name' => 'Storia']);
        $this->fisica = Subject::firstOrCreate(['name' => 'Fisica']);
    }

    private function freeProf(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => null, 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolProf(School $school): Student
    {
        return Student::create(['name' => 'ProfS', 'email' => 'ps' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $school->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function school(): School
    {
        return School::create(['name' => 'Liceo', 'slug' => 'liceo-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function makeDoc(Student $p, array $attrs = []): TeachingDocument
    {
        return TeachingDocument::create(array_merge([
            'teacher_id' => $p->id, 'title' => 'Doc', 'source_type' => 'text', 'status' => 'ready',
        ], $attrs));
    }

    // ===== Struttura argomento/lezione =====

    public function test_free_prof_creates_topic(): void
    {
        $p = $this->freeProf();

        $this->asProf($p)->post(route('docente.topics.store'), [
            'name' => 'La Rivoluzione francese', 'subject_id' => $this->storia->id,
        ])->assertRedirect();

        $topic = Topic::where('teacher_id', $p->id)->first();
        $this->assertNotNull($topic);
        $this->assertSame('La Rivoluzione francese', $topic->name);
        $this->assertNull($topic->school_id);
        $this->assertSame($this->storia->id, $topic->subject_id);
    }

    public function test_topic_position_increments(): void
    {
        $p = $this->freeProf();
        $this->asProf($p)->post(route('docente.topics.store'), ['name' => 'A', 'subject_id' => $this->storia->id]);
        $this->asProf($p)->post(route('docente.topics.store'), ['name' => 'B', 'subject_id' => $this->storia->id]);

        $positions = Topic::where('teacher_id', $p->id)->orderBy('position')->pluck('name')->all();
        $this->assertSame(['A', 'B'], $positions);
    }

    public function test_create_and_order_lessons(): void
    {
        $p = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);

        $this->asProf($p)->post(route('docente.lessons.store', $topic), ['title' => 'L1'])->assertRedirect();
        $this->asProf($p)->post(route('docente.lessons.store', $topic), ['title' => 'L2'])->assertRedirect();

        $lessons = Lesson::where('topic_id', $topic->id)->orderBy('position')->get();
        $this->assertSame(['L1', 'L2'], $lessons->pluck('title')->all());
        $this->assertSame('draft', $lessons->first()->generation_status);

        // Riordino: inverti.
        $this->asProf($p)->postJson(route('docente.lessons.reorder', $topic), [
            'order' => [$lessons[1]->id, $lessons[0]->id],
        ])->assertOk();

        $this->assertSame(['L2', 'L1'],
            Lesson::where('topic_id', $topic->id)->orderBy('position')->pluck('title')->all());
    }

    public function test_topic_reorder(): void
    {
        $p = $this->freeProf();
        $a = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'A', 'position' => 0]);
        $b = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'B', 'position' => 1]);

        $this->asProf($p)->postJson(route('docente.topics.reorder'), ['order' => [$b->id, $a->id]])->assertOk();

        $this->assertSame(['B', 'A'],
            Topic::where('teacher_id', $p->id)->orderBy('position')->pluck('name')->all());
    }

    // ===== Classificazione materiali =====

    public function test_assign_and_unassign_material(): void
    {
        $p = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $p->id, 'title' => 'L', 'position' => 0]);
        $doc = $this->makeDoc($p);

        $this->asProf($p)->post(route('docente.lessons.materials.assign', $lesson), [
            'document_id' => $doc->id,
        ])->assertRedirect();

        $this->assertSame($lesson->id, $doc->fresh()->lesson_id);

        $this->asProf($p)->delete(route('docente.lessons.materials.unassign', [$lesson, $doc]))->assertRedirect();
        $this->assertNull($doc->fresh()->lesson_id);
    }

    public function test_unclassified_materials_stay_in_pool(): void
    {
        $p = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $p->id, 'title' => 'L', 'position' => 0]);

        $classified = $this->makeDoc($p, ['title' => 'In lezione', 'lesson_id' => $lesson->id]);
        $pooled = $this->makeDoc($p, ['title' => 'Da organizzare']);

        $this->asProf($p)->get(route('docente.topics.show', $topic))
            ->assertOk()
            ->assertSee('Da organizzare')
            ->assertSee('In lezione');

        $this->assertNull($pooled->fresh()->lesson_id);
    }

    public function test_cannot_assign_other_teachers_material(): void
    {
        $owner = $this->freeProf();
        $other = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $owner->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $owner->id, 'title' => 'L', 'position' => 0]);
        $foreignDoc = $this->makeDoc($other);

        $this->asProf($owner)->post(route('docente.lessons.materials.assign', $lesson), [
            'document_id' => $foreignDoc->id,
        ])->assertNotFound();

        $this->assertNull($foreignDoc->fresh()->lesson_id);
    }

    public function test_deleting_lesson_returns_materials_to_pool(): void
    {
        $p = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $p->id, 'title' => 'L', 'position' => 0]);
        $doc = $this->makeDoc($p, ['lesson_id' => $lesson->id]);

        $this->asProf($p)->delete(route('docente.lessons.destroy', $lesson))->assertRedirect();

        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertNull($doc->fresh()->lesson_id);
    }

    public function test_deleting_topic_cascades_lessons_and_frees_materials(): void
    {
        $p = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $p->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $p->id, 'title' => 'L', 'position' => 0]);
        $doc = $this->makeDoc($p, ['lesson_id' => $lesson->id]);

        $this->asProf($p)->delete(route('docente.topics.destroy', $topic))->assertRedirect();

        $this->assertSoftDeleted('topics', ['id' => $topic->id]);
        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertNull($doc->fresh()->lesson_id);
    }

    // ===== Proprietà / tenancy =====

    public function test_cannot_view_other_teachers_topic(): void
    {
        $owner = $this->freeProf();
        $intruder = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $owner->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);

        $this->asProf($intruder)->get(route('docente.topics.show', $topic))->assertForbidden();
    }

    public function test_cannot_add_lesson_to_other_teachers_topic(): void
    {
        $owner = $this->freeProf();
        $intruder = $this->freeProf();
        $topic = Topic::create(['teacher_id' => $owner->id, 'subject_id' => $this->storia->id, 'name' => 'T', 'position' => 0]);

        $this->asProf($intruder)->post(route('docente.lessons.store', $topic), ['title' => 'X'])->assertForbidden();
        $this->assertSame(0, Lesson::where('topic_id', $topic->id)->count());
    }

    public function test_index_only_lists_own_topics(): void
    {
        $owner = $this->freeProf();
        $other = $this->freeProf();
        Topic::create(['teacher_id' => $owner->id, 'subject_id' => $this->storia->id, 'name' => 'Mio', 'position' => 0]);
        Topic::create(['teacher_id' => $other->id, 'subject_id' => $this->storia->id, 'name' => 'Altrui', 'position' => 0]);

        $this->asProf($owner)->get(route('docente.topics.index'))
            ->assertOk()
            ->assertSee('Mio')
            ->assertDontSee('Altrui');
    }

    // School teacher: può creare argomenti solo sulle proprie competenze.
    public function test_school_prof_restricted_to_teachable_subjects(): void
    {
        $school = $this->school();
        $p = $this->schoolProf($school);
        // competenza: solo Storia
        $p->teachableSubjects()->attach($this->storia->id, ['school_id' => $school->id]);

        // Materia di competenza → ok
        $this->asProf($p)->post(route('docente.topics.store'), [
            'name' => 'OK', 'subject_id' => $this->storia->id,
        ])->assertRedirect();
        $topic = Topic::where('teacher_id', $p->id)->first();
        $this->assertSame($school->id, $topic->school_id);

        // Materia non di competenza → 403
        $this->asProf($p)->post(route('docente.topics.store'), [
            'name' => 'NO', 'subject_id' => $this->fisica->id,
        ])->assertForbidden();

        $this->assertSame(1, Topic::where('teacher_id', $p->id)->count());
    }

    public function test_requires_professor_role(): void
    {
        $student = Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);

        // Il middleware `professor` blocca i non-docenti (403).
        $this->asProf($student)->get(route('docente.topics.index'))->assertForbidden();
    }
}
