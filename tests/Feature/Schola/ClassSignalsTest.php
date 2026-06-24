<?php

namespace Tests\Feature\Schola;

use App\Models\ArtifactPublication;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ClassStudent;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentArtifactView;
use App\Models\StudentGeneratedArtifact;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Models\UnansweredQuestion;
use App\Services\EmbeddingService;
use App\Services\Schola\ClassSignalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ClassSignalsTest extends TestCase
{
    use RefreshDatabase;

    private const DIM = 768;

    // ===== helpers =====

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(string $name = 'Stu'): Student
    {
        return Student::create(['name' => $name, 'email' => 's+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function klass(Student $teacher): SchoolClass
    {
        $sub = Subject::firstOrCreate(['name' => 'Fisica']);
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => '3B', 'subject_id' => $sub->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $c, Student $s, string $status = 'active'): void
    {
        ClassStudent::create(['school_class_id' => $c->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null]);
    }

    private function publish(Student $prof, SchoolClass $c, array $artAttrs = []): ArtifactPublication
    {
        $art = TeachingArtifact::create(array_merge(['teacher_id' => $prof->id, 'type' => 'summary',
            'title' => 'Materiale', 'content' => 'x', 'status' => 'ready'], $artAttrs));
        return ArtifactPublication::create(['teaching_artifact_id' => $art->id, 'school_class_id' => $c->id,
            'students_can_generate' => true, 'published_at' => now(), 'rag_status' => 'ready']);
    }

    private function recordView(ArtifactPublication $p, Student $s, ?Carbon $when = null): void
    {
        StudentArtifactView::create(['artifact_publication_id' => $p->id, 'student_id' => $s->id,
            'first_viewed_at' => $when ?? now(), 'last_viewed_at' => $when ?? now(), 'view_count' => 1]);
    }

    private function signals(): ClassSignalsService
    {
        return app(ClassSignalsService::class);
    }

    private function unit(int $i): array { $v = array_fill(0, self::DIM, 0.0); $v[$i] = 1.0; return $v; }

    // ===== coverage =====

    public function test_coverage_by_publication(): void
    {
        $prof = $this->prof(); $c = $this->klass($prof);
        $a = $this->student(); $b = $this->student(); $cc = $this->student(); $d = $this->student();
        foreach ([$a, $b, $cc, $d] as $s) $this->enroll($c, $s);
        $pub = $this->publish($prof, $c);
        $this->recordView($pub, $a); $this->recordView($pub, $b); // 2/4 = 50%

        $cov = $this->signals()->coverageByPublication($c);
        $this->assertCount(1, $cov);
        $this->assertSame(2, $cov[0]['opened']);
        $this->assertSame(4, $cov[0]['total']);
        $this->assertSame(50, $cov[0]['pct']);
    }

    // ===== quiz pain points =====

    public function test_quiz_pain_points_aggregate(): void
    {
        $prof = $this->prof(); $c = $this->klass($prof);
        $a = $this->student(); $b = $this->student();
        $this->enroll($c, $a); $this->enroll($c, $b);

        $quiz = Quiz::create(['module_id' => null, 'course_id' => null, 'title' => 'Q']);
        $q1 = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q1?', 'type' => 'multiple_choice',
            'options' => ['a', 'b'], 'correct_answer' => 'a', 'points' => 1, 'sort_order' => 1]);
        $q2 = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q2?', 'type' => 'multiple_choice',
            'options' => ['a', 'b'], 'correct_answer' => 'a', 'points' => 1, 'sort_order' => 2]);
        $this->publish($prof, $c, ['type' => 'quiz', 'content' => null, 'quiz_id' => $quiz->id]);

        // due tentativi: A 50 (Q1 sbagliata), B 100
        $atA = QuizAttempt::create(['quiz_id' => $quiz->id, 'student_id' => $a->id, 'started_at' => now(),
            'completed_at' => now(), 'score' => 50, 'passed' => false, 'abandoned' => false]);
        QuizAnswer::create(['attempt_id' => $atA->id, 'question_id' => $q1->id, 'answer' => 'b', 'is_correct' => false]);
        QuizAnswer::create(['attempt_id' => $atA->id, 'question_id' => $q2->id, 'answer' => 'a', 'is_correct' => true]);
        $atB = QuizAttempt::create(['quiz_id' => $quiz->id, 'student_id' => $b->id, 'started_at' => now(),
            'completed_at' => now(), 'score' => 100, 'passed' => true, 'abandoned' => false]);
        QuizAnswer::create(['attempt_id' => $atB->id, 'question_id' => $q1->id, 'answer' => 'a', 'is_correct' => true]);
        QuizAnswer::create(['attempt_id' => $atB->id, 'question_id' => $q2->id, 'answer' => 'a', 'is_correct' => true]);

        $pp = $this->signals()->quizPainPoints($c);
        $this->assertCount(1, $pp);
        $this->assertSame(2, $pp[0]['attempts']);
        $this->assertSame(75, $pp[0]['avg_score']);
        $this->assertSame(1, $pp[0]['distribution']['low']);  // score 50
        $this->assertSame(1, $pp[0]['distribution']['high']); // score 100
        $this->assertSame('Q1?', $pp[0]['top_wrong'][0]['question']);
        $this->assertSame(1, $pp[0]['top_wrong'][0]['wrong']);
    }

    // ===== student activity + inactive =====

    public function test_student_activity_and_inactive(): void
    {
        $prof = $this->prof(); $c = $this->klass($prof);
        $active = $this->student('Attivo'); $stale = $this->student('Lontano'); $never = $this->student('Mai');
        foreach ([$active, $stale, $never] as $s) $this->enroll($c, $s);
        $pub = $this->publish($prof, $c);

        $this->recordView($pub, $active, now());
        $this->recordView($pub, $stale, now()->subDays(10));
        // 'never' non ha viste

        // attività varia per 'active'
        $conv = ChatConversation::create(['student_id' => $active->id, 'school_class_id' => $c->id, 'is_active' => true, 'title' => 't', 'course_id' => null]);
        ChatMessage::create(['conversation_id' => $conv->id, 'role' => 'user', 'content' => 'q']);
        StudentGeneratedArtifact::create(['student_id' => $active->id, 'artifact_publication_id' => $pub->id, 'type' => 'mindmap', 'status' => 'ready']);

        $act = collect($this->signals()->studentActivity($c))->keyBy('name');
        $this->assertSame(1, $act['Attivo']['chat_messages']);
        $this->assertSame(1, $act['Attivo']['generations']);
        $this->assertSame(1, $act['Attivo']['views']);
        $this->assertNull($act['Mai']['last_visit']);

        $inactive = collect($this->signals()->inactiveStudents($c, 7))->pluck('name')->all();
        $this->assertContains('Lontano', $inactive);
        $this->assertContains('Mai', $inactive);
        $this->assertNotContains('Attivo', $inactive);
    }

    // ===== clustering domande scoperte =====

    private function unanswered(SchoolClass $c, Student $s, string $text): void
    {
        UnansweredQuestion::create(['school_class_id' => $c->id, 'student_id' => $s->id,
            'question' => $text, 'best_similarity' => 0.1, 'status' => 'open']);
    }

    public function test_question_clustering_with_embeddings(): void
    {
        $prof = $this->prof(); $c = $this->klass($prof); $s = $this->student(); $this->enroll($c, $s);
        $this->unanswered($c, $s, 'Cos\'è la fotosintesi?');
        $this->unanswered($c, $s, 'Come funziona la fotosintesi?');
        $this->unanswered($c, $s, 'Chi ha vinto la guerra dei trent\'anni?');

        // mock: prime due simili (unit0), terza diversa (unit1)
        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embed')->once()->andReturn([$this->unit(0), $this->unit(0), $this->unit(1)]);
        $this->instance(EmbeddingService::class, $svc);
        atheneum_setting_put('schola.question_cluster_threshold', 0.6);

        $clusters = app(ClassSignalsService::class)->openQuestionClusters($c);
        $this->assertCount(2, $clusters);
        $this->assertTrue($clusters[0]['clustered']);
        $counts = collect($clusters)->pluck('count')->sort()->values()->all();
        $this->assertSame([1, 2], $counts);
        // studente NON anonimo
        $this->assertNotNull($clusters[0]['questions'][0]['student_name']);
    }

    public function test_question_clustering_falls_back_flat_without_embeddings(): void
    {
        $prof = $this->prof(); $c = $this->klass($prof); $s = $this->student(); $this->enroll($c, $s);
        $this->unanswered($c, $s, 'Domanda A');
        $this->unanswered($c, $s, 'Domanda B');

        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embed')->andThrow(new RuntimeException('videoai down'));
        $this->instance(EmbeddingService::class, $svc);

        $clusters = app(ClassSignalsService::class)->openQuestionClusters($c);
        $this->assertCount(2, $clusters); // lista piatta: un cluster per domanda
        $this->assertFalse($clusters[0]['clustered']);
    }

    // ===== controller: owner-only + azioni =====

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    public function test_activity_and_questions_views_owner_only(): void
    {
        $a = $this->prof(); $b = $this->prof();
        $c = $this->klass($a);

        $this->asProf($a)->get(route('docente.classes.activity', $c))->assertOk();
        $this->asProf($a)->get(route('docente.classes.questions', $c))->assertOk();
        $this->asProf($b)->get(route('docente.classes.activity', $c))->assertForbidden();
        $this->asProf($b)->get(route('docente.classes.questions', $c))->assertForbidden();
    }

    public function test_question_update_single_and_cluster(): void
    {
        $prof = $this->prof(); $c = $this->klass($prof); $s = $this->student(); $this->enroll($c, $s);
        $this->unanswered($c, $s, 'A'); $this->unanswered($c, $s, 'B');
        $q1 = UnansweredQuestion::where('question', 'A')->first();
        $q2 = UnansweredQuestion::where('question', 'B')->first();

        // singola → addressed
        $this->asProf($prof)->patch(route('docente.questions.update', $q1), ['status' => 'addressed'])->assertRedirect();
        $this->assertSame('addressed', $q1->fresh()->status);

        // cluster → dismissed (entrambe, ma q1 già addressed → resta nel set? whereIn entrambe → dismissed)
        $this->asProf($prof)->post(route('docente.classes.questions.bulk', $c), [
            'question_ids' => [$q1->id, $q2->id], 'status' => 'dismissed',
        ])->assertRedirect();
        $this->assertSame('dismissed', $q1->fresh()->status);
        $this->assertSame('dismissed', $q2->fresh()->status);
    }

    public function test_question_update_owner_only(): void
    {
        $a = $this->prof(); $b = $this->prof();
        $c = $this->klass($a); $s = $this->student(); $this->enroll($c, $s);
        $this->unanswered($c, $s, 'A');
        $q = UnansweredQuestion::first();

        $this->asProf($b)->patch(route('docente.questions.update', $q), ['status' => 'addressed'])->assertForbidden();
        $this->asProf($b)->post(route('docente.classes.questions.bulk', $c), ['question_ids' => [$q->id], 'status' => 'addressed'])->assertForbidden();
    }

    // ===== dashboard =====

    public function test_dashboard_shows_cross_class_summary(): void
    {
        $prof = $this->prof(); $c = $this->klass($prof);
        $pending = $this->student('DaApprovare'); $this->enroll($c, $pending, 'pending');
        TeachingDocument::create(['teacher_id' => $prof->id, 'title' => 'Doc rotto', 'source_type' => 'pdf', 'status' => 'failed', 'failure_reason' => 'boom']);
        $active = $this->student(); $this->enroll($c, $active);
        $this->unanswered($c, $active, 'Domanda aperta');

        $resp = $this->asProf($prof)->get(route('docente.dashboard'));
        $resp->assertOk()
            ->assertSee('DaApprovare')
            ->assertSee('Doc rotto')
            ->assertSee('Domande scoperte aperte');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
