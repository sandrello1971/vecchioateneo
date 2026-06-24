<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quiz "pool + estrazione casuale per tentativo". Pool ampio configurabile; ogni
 * tentativo estrae K domande casuali (anti-copiatura), persistite in
 * selected_question_ids per stabilità e punteggio su K. Retrocompat: K=null → tutte.
 */
class QuizPoolTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'S ' . uniqid(), 'email' => 's+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'is_active' => true, 'is_demo' => false, 'must_change_password' => false,
        ]);
    }

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    /** Quiz MODULO (non esame: ripetibile, niente cap) con $n domande nel pool. */
    private function makePoolQuiz(int $n, ?int $perAttempt): Quiz
    {
        $course = $this->makeCourse();
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 0, 'is_active' => true]);
        $quiz = Quiz::create([
            'course_id' => $course->id, 'module_id' => $module->id, 'title' => 'Quiz pool',
            'passing_score' => 60, 'is_active' => true, 'questions_per_attempt' => $perAttempt,
        ]);
        for ($i = 1; $i <= $n; $i++) {
            QuizQuestion::create([
                'quiz_id' => $quiz->id, 'question' => "Domanda {$i}?", 'type' => 'multiple_choice',
                'options' => ["a{$i}", "b{$i}", "c{$i}", "d{$i}"], 'correct_answer' => "a{$i}",
                'explanation' => "exp {$i}", 'points' => 1, 'sort_order' => $i,
            ]);
        }

        return $quiz;
    }

    private function actingAsStudent(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name]);
    }

    // ============================================================
    // Estrazione per tentativo + anti-copiatura
    // ============================================================

    public function test_start_estrae_k_e_persiste_selected_ids(): void
    {
        $quiz = $this->makePoolQuiz(40, 15);
        $student = $this->makeStudent();

        $res = $this->actingAsStudent($student)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $res->assertJsonCount(15, 'questions');

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', $student->id)->first();
        $this->assertCount(15, $attempt->selected_question_ids);
        // Mai correct_answer nel payload.
        foreach ($res->json('questions') as $q) {
            $this->assertArrayHasKey('options', $q);
            $this->assertArrayNotHasKey('correct_answer', $q);
        }
    }

    public function test_due_studenti_due_set_diversi(): void
    {
        $quiz = $this->makePoolQuiz(40, 15);
        $a = $this->makeStudent();
        $b = $this->makeStudent();

        $this->actingAsStudent($a)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $this->actingAsStudent($b)->postJson(route('student.quiz.start', $quiz))->assertOk();

        $setA = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', $a->id)->first()->selected_question_ids;
        $setB = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', $b->id)->first()->selected_question_ids;

        $this->assertCount(15, $setA);
        $this->assertCount(15, $setB);
        $this->assertNotEquals($setA, $setB, 'Due studenti devono vedere set diversi (anti-copiatura).');
    }

    public function test_stabilita_refresh_stesse_domande(): void
    {
        $quiz = $this->makePoolQuiz(40, 15);
        $student = $this->makeStudent();

        $r1 = $this->actingAsStudent($student)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $attemptId1 = $r1->json('attempt_id');
        $set1 = collect($r1->json('questions'))->pluck('id')->all();

        // "Refresh": ri-chiamata di start() su tentativo aperto → stesso attempt, stesse K.
        $r2 = $this->actingAsStudent($student)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $this->assertSame($attemptId1, $r2->json('attempt_id'), 'Ripresa: stesso tentativo.');
        $this->assertSame($set1, collect($r2->json('questions'))->pluck('id')->all(), 'Stesse 15 domande.');
    }

    // ============================================================
    // Punteggio su K (non sul pool)
    // ============================================================

    public function test_punteggio_calcolato_su_k_non_sul_pool(): void
    {
        $quiz = $this->makePoolQuiz(40, 15); // passing_score 60
        $student = $this->makeStudent();

        $start = $this->actingAsStudent($student)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $attemptId = $start->json('attempt_id');
        $selected = collect($start->json('questions'))->pluck('id')->all();
        $byId = QuizQuestion::whereIn('id', $selected)->get()->keyBy('id');

        // Rispondi correttamente a 9 delle 15 (9/15 = 60% → passed con soglia 60).
        $answers = [];
        foreach (array_slice($selected, 0, 9) as $qid) {
            $answers[$qid] = $byId[$qid]->correct_answer;
        }
        foreach (array_slice($selected, 9) as $qid) {
            $answers[$qid] = 'sbagliata';
        }

        $res = $this->actingAsStudent($student)->postJson(route('student.quiz.submit', $quiz), [
            'attempt_id' => $attemptId, 'answers' => $answers,
        ])->assertOk();

        $this->assertSame(60, $res->json('score'), 'Score = 9/15 = 60%, NON su 40.');
        $this->assertTrue($res->json('passed'));

        // Persistito: 15 risposte (non 40).
        $attempt = QuizAttempt::find($attemptId);
        $this->assertSame(60, $attempt->score);
        $this->assertSame(15, $attempt->answers()->count());
    }

    // ============================================================
    // Retrocompat: questions_per_attempt = null → tutte
    // ============================================================

    public function test_retrocompat_null_somministra_tutte(): void
    {
        $quiz = $this->makePoolQuiz(8, null); // null = tutte
        $student = $this->makeStudent();

        $start = $this->actingAsStudent($student)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $start->assertJsonCount(8, 'questions');

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)->first();
        $this->assertCount(8, $attempt->selected_question_ids);

        // Score sull'intero set.
        $selected = collect($start->json('questions'))->pluck('id')->all();
        $byId = QuizQuestion::whereIn('id', $selected)->get()->keyBy('id');
        $answers = [];
        foreach ($selected as $qid) {
            $answers[$qid] = $byId[$qid]->correct_answer; // tutte corrette → 100%
        }
        $res = $this->actingAsStudent($student)->postJson(route('student.quiz.submit', $quiz), [
            'attempt_id' => $attempt->id, 'answers' => $answers,
        ])->assertOk();
        $this->assertSame(100, $res->json('score'));
    }

    public function test_retrocompat_attempt_legacy_senza_selezione_usa_tutte(): void
    {
        // Tentativo "vecchio": selected_question_ids = null → submit valuta su tutte.
        $quiz = $this->makePoolQuiz(5, null);
        $student = $this->makeStudent();
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id, 'student_id' => $student->id, 'started_at' => now(),
            'attempt_number' => 1, 'selected_question_ids' => null,
        ]);
        $byId = $quiz->questions()->get()->keyBy('id');
        $answers = $byId->mapWithKeys(fn ($q, $id) => [$id => $q->correct_answer])->all();

        $res = $this->actingAsStudent($student)->postJson(route('student.quiz.submit', $quiz), [
            'attempt_id' => $attempt->id, 'answers' => $answers,
        ])->assertOk();
        $this->assertSame(100, $res->json('score'));
        $this->assertSame(5, $attempt->fresh()->answers()->count());
    }

    // ============================================================
    // Validazione admin: K > pool → respinto
    // ============================================================

    private function asAdmin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@e.it']);
    }

    public function test_admin_rifiuta_per_attempt_maggiore_del_pool(): void
    {
        $quiz = $this->makePoolQuiz(10, null);

        $this->asAdmin()->from(route('admin.quizzes.edit', $quiz->id))
            ->put(route('admin.quizzes.update', $quiz->id), [
                'title' => $quiz->title, 'passing_score' => 60, 'questions_per_attempt' => 20,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($quiz->fresh()->questions_per_attempt, 'Non deve salvare un K > pool.');
    }

    public function test_admin_salva_per_attempt_valido(): void
    {
        $quiz = $this->makePoolQuiz(40, null);

        $this->asAdmin()->put(route('admin.quizzes.update', $quiz->id), [
            'title' => $quiz->title, 'passing_score' => 60, 'questions_per_attempt' => 15,
        ])->assertRedirect(route('admin.quizzes.index'));

        $this->assertSame(15, $quiz->fresh()->questions_per_attempt);
    }

    // ============================================================
    // Generazione pool a batch + dedup (Claude finto)
    // ============================================================

    /** Fake Claude: ogni chiamata ritorna $perCall domande UNICHE (contatore globale). */
    private function fakeClaudeUniqueBatches(int $perCall = 10): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $counter = 0;
        \Illuminate\Support\Facades\Http::fake([
            'api.anthropic.com/*' => function () use (&$counter, $perCall) {
                $questions = [];
                for ($i = 0; $i < $perCall; $i++) {
                    $counter++;
                    $questions[] = [
                        'question' => "Domanda univoca {$counter}?",
                        'options' => ['a', 'b', 'c', 'd'],
                        'correct_answer' => 'a',
                        'explanation' => 'e',
                    ];
                }

                return \Illuminate\Support\Facades\Http::response([
                    'content' => [['text' => json_encode(['questions' => $questions])]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
                ], 200);
            },
        ]);
    }

    public function test_generate_pool_40_batch_senza_duplicati(): void
    {
        $this->fakeClaudeUniqueBatches(10);
        $course = $this->makeCourse();
        Module::create(['course_id' => $course->id, 'title' => 'M', 'content' => str_repeat('contenuto del corso. ', 500), 'sort_order' => 0, 'is_active' => true]);

        $quiz = app(\App\Services\QuizGeneratorService::class)
            ->generateFromContent($course, str_repeat('contenuto. ', 2000), 40, 15);

        $this->assertNotNull($quiz);
        $count = $quiz->questions()->count();
        $this->assertSame(40, $count, 'Il pool deve avere 40 domande.');
        // Nessun duplicato (testo domanda).
        $texts = $quiz->questions()->pluck('question')->all();
        $this->assertSame(count($texts), count(array_unique($texts)), 'Nessuna domanda duplicata.');
        // questions_per_attempt persistito.
        $this->assertSame(15, $quiz->questions_per_attempt);
    }

    public function test_generate_pool_dedup_scarta_duplicati(): void
    {
        // Claude finto che ritorna SEMPRE le stesse 5 domande → il dedup le riduce
        // a 5 uniche e il loop si ferma (round senza nuove), niente duplicati nel pool.
        config(['services.anthropic.key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake([
            'api.anthropic.com/*' => \Illuminate\Support\Facades\Http::response([
                'content' => [['text' => json_encode(['questions' => array_map(fn ($i) => [
                    'question' => "Fissa {$i}?", 'options' => ['a', 'b', 'c', 'd'], 'correct_answer' => 'a', 'explanation' => 'e',
                ], range(1, 5))])]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);
        $course = $this->makeCourse();

        $quiz = app(\App\Services\QuizGeneratorService::class)
            ->generateFromContent($course, 'contenuto', 40, null);

        $this->assertNotNull($quiz);
        $texts = $quiz->questions()->pluck('question')->all();
        $this->assertSame(5, count($texts), 'Dedup: solo 5 uniche nonostante il target 40.');
        $this->assertSame(count($texts), count(array_unique($texts)));
    }

    // ============================================================
    // max_attempts: secondo tentativo → nuova estrazione
    // ============================================================

    public function test_secondo_tentativo_nuova_estrazione(): void
    {
        $quiz = $this->makePoolQuiz(40, 15);
        $student = $this->makeStudent();

        // 1° tentativo: start + submit.
        $s1 = $this->actingAsStudent($student)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $set1 = collect($s1->json('questions'))->pluck('id')->all();
        $this->actingAsStudent($student)->postJson(route('student.quiz.submit', $quiz), [
            'attempt_id' => $s1->json('attempt_id'), 'answers' => [],
        ])->assertOk();

        // 2° tentativo: nuova estrazione (set diverso).
        $s2 = $this->actingAsStudent($student)->postJson(route('student.quiz.start', $quiz))->assertOk();
        $set2 = collect($s2->json('questions'))->pluck('id')->all();

        $this->assertNotSame($s1->json('attempt_id'), $s2->json('attempt_id'));
        $this->assertNotEquals($set1, $set2, 'Il secondo tentativo deve estrarre 15 domande diverse.');
    }
}
