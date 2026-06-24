<?php

namespace Tests\Feature\Schola;

use App\Models\ClassStudent;
use App\Models\DocumentRag;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\LessonTeacherNote;
use App\Models\StudentLessonNote;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\UnansweredQuestion;
use App\Services\EmbeddingService;
use App\Support\PgVector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

// Fase 3 (P20b) — fruizione studente delle lezioni: visibilità, isolamento,
// Minerva di lezione (gate §5 + citazioni minutaggio), appunti per paragrafo.
class StudentLessonFruitionTest extends TestCase
{
    use RefreshDatabase;

    private const DIM = 768;

    protected function setUp(): void
    {
        parent::setUp();
        atheneum_setting_put('schola.rag_min_similarity', 0.5);
    }

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolClass(Student $teacher, string $name = '3A'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name,
            'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $class, Student $s, string $status = 'active'): void
    {
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null]);
    }

    private function lesson(Student $prof, string $title = 'Le cause', string $content = '## Intro

La rivoluzione del 1789.'): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'Rivoluzione', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => $title,
            'position' => 0, 'generation_status' => 'ready', 'content' => $content]);
    }

    private function publish(Lesson $lesson, SchoolClass $class): LessonPublication
    {
        return LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function unit(int $i): array
    {
        $v = array_fill(0, self::DIM, 0.0);
        $v[$i] = 1.0;

        return $v;
    }

    private function chunk(array $attrs, array $vec): DocumentRag
    {
        $row = DocumentRag::create(array_merge(['title' => 'Lezione', 'content' => 'contenuto', 'chunk_index' => 0], $attrs));
        DB::update('UPDATE documents_rag SET embedding = ?::vector WHERE id = ?', [PgVector::toLiteral($vec), $row->id]);

        return $row;
    }

    private function mockQueryVector(int $i): void
    {
        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embedOne')->andReturn($this->unit($i));
        $svc->shouldReceive('dimensions')->andReturn(self::DIM);
        $this->instance(EmbeddingService::class, $svc);
    }

    private function fakeClaude(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Risposta basata sui materiali della classe.']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200)]);
    }

    // ===== Visibilità =====

    public function test_student_sees_published_lesson_of_active_class(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        $this->asUser($student)->get(route('student.classes.show', $class))
            ->assertOk()->assertSee('Le cause')->assertSee('Rivoluzione');

        $this->asUser($student)->get(route('student.classes.lesson.show', [$class, $lesson]))
            ->assertOk()->assertSee('Intro');
    }

    public function test_unpublished_lesson_is_forbidden(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof); // NON pubblicata

        $this->asUser($student)->get(route('student.classes.lesson.show', [$class, $lesson]))
            ->assertForbidden();
        $this->asUser($student)->get(route('student.classes.show', $class))
            ->assertOk()->assertDontSee('Le cause');
    }

    public function test_non_active_enrollment_cannot_open_lesson(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        $pending = $this->student();
        $this->enroll($class, $pending, 'pending');
        $this->asUser($pending)->get(route('student.classes.lesson.show', [$class, $lesson]))->assertForbidden();

        $stranger = $this->student(); // nessuna iscrizione
        $this->asUser($stranger)->get(route('student.classes.lesson.show', [$class, $lesson]))->assertForbidden();
    }

    public function test_lesson_of_other_class_not_visible(): void
    {
        $prof = $this->prof();
        $classA = $this->schoolClass($prof, '3A');
        $classB = $this->schoolClass($prof, '3B');
        $student = $this->student();
        $this->enroll($classB, $student); // iscritto SOLO a B
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $classA); // pubblicata in A

        // Lo studente di B non vede la lezione di A né via show né via lista.
        $this->asUser($student)->get(route('student.classes.lesson.show', [$classB, $lesson]))->assertForbidden();
        $this->asUser($student)->get(route('student.classes.show', $classB))->assertOk()->assertDontSee('Le cause');
    }

    // ===== Minerva di lezione: gate §5 + isolamento =====

    public function test_lesson_minerva_out_of_kb_no_model_and_unanswered(): void
    {
        Http::fake(); // attendiamo ZERO chiamate
        $this->mockQueryVector(0);

        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);
        // Chunk della lezione ortogonale alla query → sotto soglia.
        $this->chunk(['scope' => 'class', 'school_class_id' => $class->id, 'teacher_id' => $prof->id,
            'metadata' => ['lesson_id' => $lesson->id, 'type' => 'lesson']], $this->unit(1));

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Domanda fuori dai materiali',
            'school_class_id' => $class->id,
            'lesson_id' => $lesson->id,
        ]);

        $resp->assertOk()->assertJson(['gate' => 'empty', 'sources' => []]);
        Http::assertNothingSent();
        $this->assertDatabaseHas('unanswered_questions', [
            'school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'open',
        ]);
    }

    public function test_lesson_minerva_in_kb_answers_with_minutaggio_citation(): void
    {
        $this->fakeClaude();
        $this->mockQueryVector(0);

        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);
        // Chunk-trascrizione della lezione con minutaggio + source video.
        $this->chunk([
            'scope' => 'class', 'school_class_id' => $class->id, 'teacher_id' => $prof->id,
            'title' => 'Video lezione', 'content' => 'La crisi finanziaria del 1789.',
            'metadata' => ['lesson_id' => $lesson->id, 'type' => 'transcript',
                'start_seconds' => 65, 'end_seconds' => 80, 'source_url' => 'https://youtu.be/abc'],
        ], $this->unit(0));

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Parlami della crisi',
            'school_class_id' => $class->id,
            'lesson_id' => $lesson->id,
        ]);

        $resp->assertOk()->assertJson(['gate' => 'answered']);
        $sources = $resp->json('sources');
        $this->assertNotEmpty($sources);
        $this->assertSame('1:05', $sources[0]['timestamp']);              // 65s → mm:ss
        $this->assertSame(65, $sources[0]['seconds']);
        $this->assertStringContainsString('t=65', $sources[0]['url']);    // deep-link youtube
    }

    public function test_lesson_minerva_does_not_leak_other_class_chunks(): void
    {
        Http::fake();
        $this->mockQueryVector(0);

        $prof = $this->prof();
        $classA = $this->schoolClass($prof, '3A');
        $classB = $this->schoolClass($prof, '3B');
        $student = $this->student();
        $this->enroll($classB, $student);
        $lessonB = $this->lesson($prof);
        $this->publish($lessonB, $classB);

        // Chunk pertinente MA di un'altra classe (A): non deve emergere.
        $this->chunk(['scope' => 'class', 'school_class_id' => $classA->id, 'teacher_id' => $prof->id,
            'title' => 'Segreto A', 'content' => 'Contenuto della classe A.',
            'metadata' => ['lesson_id' => $lessonB->id, 'type' => 'lesson']], $this->unit(0));

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Contenuto', 'school_class_id' => $classB->id, 'lesson_id' => $lessonB->id,
        ]);

        $resp->assertOk()->assertJson(['gate' => 'empty']);
        Http::assertNothingSent();
    }

    public function test_lesson_minerva_requires_lesson_published_to_class(): void
    {
        $this->fakeClaude();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof); // non pubblicata su questa classe

        $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'x', 'school_class_id' => $class->id, 'lesson_id' => $lesson->id,
        ])->assertForbidden();
    }

    // ===== Appunti per paragrafo =====

    public function test_student_can_save_list_delete_personal_note(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        // Salva
        $this->asUser($student)->postJson(route('student.classes.lesson.notes.save', [$class, $lesson]), [
            'anchor' => 'p-001', 'content' => 'Mio appunto',
        ])->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('student_lesson_notes', [
            'student_id' => $student->id, 'lesson_id' => $lesson->id, 'anchor' => 'p-001', 'content' => 'Mio appunto',
        ]);

        // Lista
        $this->asUser($student)->getJson(route('student.classes.lesson.notes.list', [$class, $lesson]))
            ->assertOk()->assertJsonFragment(['anchor' => 'p-001', 'content' => 'Mio appunto']);

        // Toggle-off (content vuoto cancella)
        $this->asUser($student)->postJson(route('student.classes.lesson.notes.save', [$class, $lesson]), [
            'anchor' => 'p-001', 'content' => '',
        ])->assertOk()->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('student_lesson_notes', ['student_id' => $student->id, 'anchor' => 'p-001']);
    }

    public function test_notes_are_private_to_owner(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $owner = $this->student();
        $other = $this->student();
        $this->enroll($class, $owner);
        $this->enroll($class, $other);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        $note = StudentLessonNote::create(['student_id' => $owner->id, 'lesson_id' => $lesson->id, 'anchor' => 'p-001', 'content' => 'Privato']);

        // L'altro studente non vede la nota dell'owner nella propria lista.
        $this->asUser($other)->getJson(route('student.classes.lesson.notes.list', [$class, $lesson]))
            ->assertOk()->assertJsonMissing(['content' => 'Privato']);

        // Né può cancellarla.
        $this->asUser($other)->deleteJson(route('student.classes.lesson.notes.delete', $note))->assertForbidden();
        $this->assertDatabaseHas('student_lesson_notes', ['id' => $note->id]);
    }

    public function test_notes_forbidden_without_active_enrollment(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);
        $stranger = $this->student();

        $this->asUser($stranger)->postJson(route('student.classes.lesson.notes.save', [$class, $lesson]), [
            'anchor' => 'p-001', 'content' => 'x',
        ])->assertForbidden();
    }

    // ===== Note del DOCENTE (didattiche, visibili a tutti) =====

    public function test_teacher_notes_visible_to_all_students(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $a = $this->student();
        $b = $this->student();
        $this->enroll($class, $a);
        $this->enroll($class, $b);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        // Il docente crea una nota per il paragrafo p-001.
        $this->asProf($prof)->postJson(route('docente.lessons.teacher-notes.save', $lesson), [
            'anchor' => 'p-001', 'content' => 'NOTA-DOCENTE-VISIBILE',
        ])->assertOk();

        // Entrambi gli studenti la vedono nella pagina lezione.
        $this->asUser($a)->get(route('student.classes.lesson.show', [$class, $lesson]))
            ->assertOk()->assertSee('NOTA-DOCENTE-VISIBILE');
        $this->asUser($b)->get(route('student.classes.lesson.show', [$class, $lesson]))
            ->assertOk()->assertSee('NOTA-DOCENTE-VISIBILE');
    }

    public function test_teacher_notes_crud_and_ownership(): void
    {
        $prof = $this->prof();
        $other = $this->prof();
        $lesson = $this->lesson($prof);

        // Crea
        $this->asProf($prof)->postJson(route('docente.lessons.teacher-notes.save', $lesson), [
            'anchor' => 'p-002', 'content' => 'Spiega meglio qui',
        ])->assertOk();
        $this->assertDatabaseHas('lesson_teacher_notes', ['lesson_id' => $lesson->id, 'anchor' => 'p-002', 'content' => 'Spiega meglio qui']);

        // Un altro docente NON può scrivere note sulla lezione altrui.
        $this->asProf($other)->postJson(route('docente.lessons.teacher-notes.save', $lesson), [
            'anchor' => 'p-003', 'content' => 'intruso',
        ])->assertForbidden();
        $this->asProf($other)->getJson(route('docente.lessons.teacher-notes.list', $lesson))->assertForbidden();

        // Toggle-off
        $this->asProf($prof)->postJson(route('docente.lessons.teacher-notes.save', $lesson), [
            'anchor' => 'p-002', 'content' => '',
        ])->assertOk()->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('lesson_teacher_notes', ['lesson_id' => $lesson->id, 'anchor' => 'p-002']);
    }

    // ===== Privacy: il docente NON vede MAI le note personali dello studente =====

    public function test_teacher_never_sees_student_personal_notes(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        // Lo studente scrive un appunto PERSONALE.
        StudentLessonNote::create(['student_id' => $student->id, 'lesson_id' => $lesson->id,
            'anchor' => 'p-001', 'content' => 'SEGRETO-PERSONALE']);

        // 1) Lista note docente: contiene SOLO note docente, mai le personali.
        $this->asProf($prof)->getJson(route('docente.lessons.teacher-notes.list', $lesson))
            ->assertOk()->assertJsonMissing(['content' => 'SEGRETO-PERSONALE']);

        // 2) Pagina lezione lato docente: niente nota personale.
        $this->asProf($prof)->get(route('docente.lessons.show', $lesson))
            ->assertOk()->assertDontSee('SEGRETO-PERSONALE');

        // 3) Cruscotto attività della classe (§8.1): niente nota personale.
        $this->asProf($prof)->get(route('docente.classes.activity', $class))
            ->assertOk()->assertDontSee('SEGRETO-PERSONALE');
    }

    public function test_student_page_does_not_embed_other_students_personal_note(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $a = $this->student();
        $b = $this->student();
        $this->enroll($class, $a);
        $this->enroll($class, $b);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        StudentLessonNote::create(['student_id' => $b->id, 'lesson_id' => $lesson->id,
            'anchor' => 'p-001', 'content' => 'NOTA-DI-B']);

        // A apre la lezione: la nota personale di B non è nella pagina.
        $this->asUser($a)->get(route('student.classes.lesson.show', [$class, $lesson]))
            ->assertOk()->assertDontSee('NOTA-DI-B');
        // E nemmeno nel proprio elenco note.
        $this->asUser($a)->getJson(route('student.classes.lesson.notes.list', [$class, $lesson]))
            ->assertOk()->assertJsonMissing(['content' => 'NOTA-DI-B']);
    }
}
