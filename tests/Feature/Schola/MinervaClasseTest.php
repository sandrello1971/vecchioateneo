<?php

namespace Tests\Feature\Schola;

use App\Http\Controllers\Student\ChatController;
use App\Models\ClassStudent;
use App\Models\DocumentRag;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\EmbeddingService;
use App\Support\PgVector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class MinervaClasseTest extends TestCase
{
    use RefreshDatabase;

    private const DIM = 768;

    protected function setUp(): void
    {
        parent::setUp();
        atheneum_setting_put('schola.rag_min_similarity', 0.5);
    }

    // ===== helpers =====

    private function prof(?string $email = null): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => $email ?: 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function student(?string $email = null): Student
    {
        return Student::create([
            'name' => 'Studente', 'email' => $email ?: 'stu+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'student',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function schoolClass(Student $teacher, string $name = '3ªB'): SchoolClass
    {
        $subject = Subject::firstOrCreate(['name' => 'Fisica']);

        return SchoolClass::create([
            'teacher_id' => $teacher->id, 'name' => $name, 'subject_id' => $subject->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false,
        ]);
    }

    private function enroll(SchoolClass $class, Student $s, string $status = 'active'): void
    {
        ClassStudent::create([
            'school_class_id' => $class->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null,
        ]);
    }

    private function unit(int $i): array
    {
        $v = array_fill(0, self::DIM, 0.0);
        $v[$i] = 1.0;

        return $v;
    }

    private function chunk(array $attrs, array $vec): DocumentRag
    {
        $row = DocumentRag::create(array_merge(['title' => 'Materiale', 'content' => 'contenuto', 'chunk_index' => 0], $attrs));
        DB::update('UPDATE documents_rag SET embedding = ?::vector WHERE id = ?', [PgVector::toLiteral($vec), $row->id]);

        return $row;
    }

    /** Mocka EmbeddingService così la query embedda a unit($i), senza rete. */
    private function mockQueryVector(int $i): void
    {
        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embedOne')->andReturn($this->unit($i));
        $svc->shouldReceive('dimensions')->andReturn(self::DIM);
        $this->instance(EmbeddingService::class, $svc);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    private function fakeClaude(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Risposta basata sui materiali della classe.']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200)]);
    }

    // ===== GATE §5 =====

    public function test_out_of_kb_does_not_call_model_and_records_unanswered(): void
    {
        Http::fake(); // cattura qualsiasi richiesta: ne attendiamo ZERO
        $this->mockQueryVector(0); // query = unit(0)

        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        // Chunk di classe ortogonale alla query (similarità 0 < soglia 0.5).
        $this->chunk(['scope' => 'class', 'school_class_id' => $class->id, 'teacher_id' => $prof->id, 'content' => 'tema non pertinente'], $this->unit(1));

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Qualcosa fuori dai materiali?',
            'school_class_id' => $class->id,
        ]);

        $resp->assertOk()->assertJson(['gate' => 'empty', 'sources' => []]);

        // ZERO chiamate al modello.
        Http::assertNothingSent();

        // Record in unanswered_questions con best_similarity, classe, studente.
        $this->assertDatabaseHas('unanswered_questions', [
            'school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'open',
        ]);
        $uq = \App\Models\UnansweredQuestion::first();
        $this->assertNotNull($uq->best_similarity);
        $this->assertLessThan(0.5, (float) $uq->best_similarity);
    }

    public function test_in_kb_calls_model_and_returns_sources(): void
    {
        $this->fakeClaude();
        $this->mockQueryVector(0);

        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $this->chunk([
            'scope' => 'class', 'school_class_id' => $class->id, 'teacher_id' => $prof->id,
            'title' => 'Fotosintesi', 'content' => 'La fotosintesi avviene nei cloroplasti.',
            'metadata' => ['artifact_id' => 'art-1', 'type' => 'summary'],
        ], $this->unit(0));

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Dove avviene la fotosintesi?',
            'school_class_id' => $class->id,
        ]);

        $resp->assertOk()->assertJson(['gate' => 'answered']);
        $sources = $resp->json('sources');
        $this->assertNotEmpty($sources, 'Risposta con contesto DEVE avere fonti');
        $this->assertSame('Fotosintesi', $sources[0]['title']);
    }

    // ===== Enrollment =====

    public function test_pending_enrollment_is_forbidden(): void
    {
        $this->fakeClaude();
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student, 'pending');

        $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Ciao', 'school_class_id' => $class->id,
        ])->assertForbidden();

        $this->asUser($student)->get(route('student.classes.minerva', $class))->assertForbidden();
    }

    public function test_removed_enrollment_is_forbidden(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student, 'removed');

        $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Ciao', 'school_class_id' => $class->id,
        ])->assertForbidden();
    }

    // ===== Due classi: niente mescolanza =====

    public function test_two_classes_do_not_mix_when_class_specified(): void
    {
        $this->fakeClaude();
        $this->mockQueryVector(0);

        $prof = $this->prof();
        $classA = $this->schoolClass($prof, 'A');
        $classB = $this->schoolClass($prof, 'B');
        $student = $this->student();
        $this->enroll($classA, $student);
        $this->enroll($classB, $student);

        $this->chunk(['scope' => 'class', 'school_class_id' => $classA->id, 'teacher_id' => $prof->id, 'title' => 'MatA', 'content' => 'contenuto A', 'metadata' => ['artifact_id' => 'a']], $this->unit(0));
        $this->chunk(['scope' => 'class', 'school_class_id' => $classB->id, 'teacher_id' => $prof->id, 'title' => 'MatB', 'content' => 'contenuto B', 'metadata' => ['artifact_id' => 'b']], $this->unit(0));

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'domanda', 'school_class_id' => $classA->id,
        ]);

        $titles = collect($resp->json('sources'))->pluck('title')->all();
        $this->assertContains('MatA', $titles);
        $this->assertNotContains('MatB', $titles, 'Il corpus della classe B non deve comparire');
    }

    // ===== docente vede teacher_private, studente MAI =====

    public function test_teacher_sees_private_chunks_student_never(): void
    {
        $this->fakeClaude();
        $this->mockQueryVector(0);

        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        $this->chunk(['scope' => 'teacher_private', 'teacher_id' => $prof->id, 'title' => 'Privato', 'content' => 'materiale privato', 'metadata' => ['artifact_id' => 'p']], $this->unit(0));
        $this->chunk(['scope' => 'class', 'school_class_id' => $class->id, 'teacher_id' => $prof->id, 'title' => 'Pubblico', 'content' => 'materiale pubblicato', 'metadata' => ['artifact_id' => 'q']], $this->unit(0));

        // Docente: vede Privato + Pubblico
        $docResp = $this->asUser($prof)->postJson(route('student.minerva.ask'), ['question' => 'q', 'school_class_id' => $class->id]);
        $docTitles = collect($docResp->json('sources'))->pluck('title')->all();
        $this->assertContains('Privato', $docTitles);

        // Studente: MAI il teacher_private
        $stuResp = $this->asUser($student)->postJson(route('student.minerva.ask'), ['question' => 'q', 'school_class_id' => $class->id]);
        $stuTitles = collect($stuResp->json('sources'))->pluck('title')->all();
        $this->assertNotContains('Privato', $stuTitles);
        $this->assertContains('Pubblico', $stuTitles);
    }

    // ===== Citazioni con minutaggio =====

    public function test_citations_include_timestamp_for_segments(): void
    {
        $this->fakeClaude();
        $this->mockQueryVector(0);

        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        $this->chunk([
            'scope' => 'class', 'school_class_id' => $class->id, 'teacher_id' => $prof->id,
            'title' => 'Lezione video', 'content' => 'spiegazione al minuto giusto',
            'metadata' => ['artifact_id' => 'v', 'type' => 'transcript', 'start_seconds' => 75, 'end_seconds' => 90, 'source_url' => 'https://youtu.be/abc'],
        ], $this->unit(0));

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), ['question' => 'q', 'school_class_id' => $class->id]);

        $src = $resp->json('sources.0');
        $this->assertSame('1:15', $src['timestamp']);
        $this->assertSame(75, $src['seconds']);
        $this->assertStringContainsString('t=75', $src['url']);
    }

    // ===== Regressione mondo corsi =====

    public function test_corsi_path_unchanged_without_class_context(): void
    {
        $this->fakeClaude();
        $student = $this->student();

        $resp = $this->asUser($student)->postJson(route('student.minerva.ask'), [
            'question' => 'Domanda generica corsi', 'mode' => 'summary',
        ]);

        // Forma di risposta del mondo corsi (ha 'mode', NON 'gate').
        $resp->assertOk()->assertJsonStructure(['answer', 'mode']);
        $this->assertNull($resp->json('gate'));
        // Nessun effetto collaterale Schola.
        $this->assertDatabaseCount('unanswered_questions', 0);
        $this->assertDatabaseCount('chat_conversations', 0);
    }

    // ===== Render pagina chat =====

    public function test_class_chat_page_renders_for_active_student(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        $this->asUser($student)->get(route('student.classes.minerva', $class))
            ->assertOk()->assertSee('Minerva')->assertSee($class->name);
    }

    public function test_class_chat_page_renders_for_teacher(): void
    {
        $prof = $this->prof();
        $class = $this->schoolClass($prof);

        $this->asUser($prof)->get(route('docente.classes.minerva', $class))
            ->assertOk()->assertSee('Docente');
    }

    // ===== Prompt Schola separato da quello corsi =====

    public function test_schola_prompt_contains_constraint_and_differs_from_corsi(): void
    {
        $ctrl = app(ChatController::class);

        $schola = $ctrl->buildScholaSystemPrompt(false, 'CONTENUTO DI CLASSE');
        $this->assertStringContainsString('ESCLUSIVAMENTE', $schola);
        $this->assertStringContainsString('VIETATO integrare', $schola);
        $this->assertStringContainsString('materiali della classe', $schola);

        $corsi = $ctrl->buildMinervaSystemPrompt(['Corso X'], 'summary', false, 'CTX');
        // Il prompt corsi NON deve contenere il vincolo assoluto Schola.
        $this->assertStringNotContainsString('VINCOLO ASSOLUTO', $corsi);
        $this->assertStringNotContainsString('VIETATO integrare', $corsi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
