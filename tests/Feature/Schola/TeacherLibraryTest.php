<?php

namespace Tests\Feature\Schola;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function prof(string $name = 'Prof'): Student
    {
        return Student::create(['name' => $name, 'email' => 'p+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function artifact(Student $prof, array $attrs = [], ?TeachingDocument $doc = null): TeachingArtifact
    {
        return TeachingArtifact::create(array_merge([
            'teaching_document_id' => $doc?->id, 'teacher_id' => $prof->id, 'type' => 'summary',
            'title' => 'Riassunto', 'content' => '## Sintesi', 'status' => 'ready',
        ], $attrs));
    }

    private function doc(Student $prof, string $sourceType): TeachingDocument
    {
        return TeachingDocument::create(['teacher_id' => $prof->id, 'title' => 'Doc',
            'source_type' => $sourceType, 'status' => 'ready', 'extracted_text' => 'testo']);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function share(Student $p, TeachingArtifact $a, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->asProf($p)->from(route('docente.artifacts.show', $a))
            ->patch(route('docente.artifacts.sharing', $a), array_merge(['shared' => '1'], $extra));
    }

    // ===== Blocco copyright =====

    public function test_transcript_from_photos_or_pdf_cannot_be_shared(): void
    {
        $prof = $this->prof();
        foreach (['photos', 'pdf'] as $src) {
            $art = $this->artifact($prof, ['type' => 'transcript'], $this->doc($prof, $src));
            $this->share($prof, $art, ['rights_ack' => '1'])->assertRedirect()->assertSessionHas('error');
            $this->assertFalse((bool) $art->fresh()->shared_with_teachers);
        }
    }

    public function test_transcript_from_text_can_be_shared(): void
    {
        $prof = $this->prof();
        $art = $this->artifact($prof, ['type' => 'transcript'], $this->doc($prof, 'text'));
        $this->share($prof, $art, ['rights_ack' => '1'])->assertRedirect();
        $this->assertTrue((bool) $art->fresh()->shared_with_teachers);
    }

    // ===== Checkbox responsabilità prima condivisione =====

    public function test_first_share_requires_rights_ack_then_persists(): void
    {
        $prof = $this->prof();
        $a1 = $this->artifact($prof, ['title' => 'Primo']);

        // Prima condivisione SENZA ack → bloccata
        $this->share($prof, $a1)->assertSessionHas('needs_ack', true);
        $this->assertFalse((bool) $a1->fresh()->shared_with_teachers);
        $this->assertNull($prof->fresh()->library_rights_ack_at);

        // Con ack → condiviso + ack persistito
        $this->share($prof, $a1, ['rights_ack' => '1'])->assertRedirect();
        $this->assertTrue((bool) $a1->fresh()->shared_with_teachers);
        $this->assertNotNull($prof->fresh()->library_rights_ack_at);

        // Seconda condivisione: NON serve più l'ack
        $a2 = $this->artifact($prof, ['title' => 'Secondo']);
        $this->share($prof, $a2)->assertRedirect();
        $this->assertTrue((bool) $a2->fresh()->shared_with_teachers);
    }

    public function test_sharing_toggle_owner_only(): void
    {
        $a = $this->prof(); $b = $this->prof();
        $art = $this->artifact($a);
        $this->asProf($b)->patch(route('docente.artifacts.sharing', $art), ['shared' => '1', 'rights_ack' => '1'])
            ->assertForbidden();
    }

    // ===== Biblioteca: visibilità + ricerca =====

    public function test_only_shared_artifacts_visible_in_library(): void
    {
        $a = $this->prof();
        $shared = $this->artifact($a, ['title' => 'Condiviso', 'shared_with_teachers' => true]);
        $private = $this->artifact($a, ['title' => 'Privato', 'shared_with_teachers' => false]);
        $other = $this->prof('Altro');

        $resp = $this->asProf($other)->get(route('docente.biblioteca.index'));
        $resp->assertOk()->assertSee('Condiviso')->assertDontSee('Privato');

        // show: il privato dà 404, lo shared 200
        $this->asProf($other)->get(route('docente.biblioteca.show', $private))->assertNotFound();
        $this->asProf($other)->get(route('docente.biblioteca.show', $shared))->assertOk();
    }

    public function test_library_search_by_subject_and_type(): void
    {
        $a = $this->prof();
        $fisica = Subject::firstOrCreate(['name' => 'Fisica']);
        $storia = Subject::firstOrCreate(['name' => 'Storia']);
        $this->artifact($a, ['title' => 'QuizFisica', 'type' => 'quiz', 'content' => null, 'shared_with_teachers' => true, 'subject_id' => $fisica->id]);
        $this->artifact($a, ['title' => 'RiassuntoStoria', 'shared_with_teachers' => true, 'subject_id' => $storia->id]);

        $this->asProf($a)->get(route('docente.biblioteca.index', ['subject_id' => $fisica->id]))
            ->assertSee('QuizFisica')->assertDontSee('RiassuntoStoria');
        $this->asProf($a)->get(route('docente.biblioteca.index', ['type' => 'summary']))
            ->assertSee('RiassuntoStoria')->assertDontSee('QuizFisica');
    }

    // ===== Fork =====

    public function test_fork_is_independent_deep_copy(): void
    {
        $author = $this->prof('Autore');
        $forker = $this->prof('Forker');
        $doc = $this->doc($author, 'text');
        $orig = $this->artifact($author, ['title' => 'Originale', 'content' => 'contenuto originale', 'shared_with_teachers' => true], $doc);

        $this->asProf($forker)->post(route('docente.biblioteca.fork', $orig))->assertRedirect();

        $copy = TeachingArtifact::where('teacher_id', $forker->id)->where('origin_artifact_id', $orig->id)->first();
        $this->assertNotNull($copy);
        $this->assertSame('contenuto originale', $copy->content);
        $this->assertNull($copy->teaching_document_id, 'Il fork NON porta il documento grezzo');
        $this->assertFalse((bool) $copy->shared_with_teachers);

        // Modifica + soft-delete dell'originale NON toccano il fork
        $orig->update(['content' => 'MODIFICATO']);
        $orig->delete();
        $copy->refresh();
        $this->assertSame('contenuto originale', $copy->content);
        $this->assertFalse($copy->trashed());
    }

    public function test_fork_quiz_duplicates_questions(): void
    {
        $author = $this->prof(); $forker = $this->prof('F');
        $quiz = Quiz::create(['module_id' => null, 'course_id' => null, 'title' => 'Q orig']);
        foreach (range(1, 3) as $i) {
            QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => "D$i?", 'type' => 'multiple_choice',
                'options' => ['a', 'b'], 'correct_answer' => 'a', 'points' => 1, 'sort_order' => $i]);
        }
        $orig = $this->artifact($author, ['type' => 'quiz', 'content' => null, 'quiz_id' => $quiz->id, 'shared_with_teachers' => true]);

        $this->asProf($forker)->post(route('docente.biblioteca.fork', $orig))->assertRedirect();

        $copy = TeachingArtifact::where('teacher_id', $forker->id)->where('origin_artifact_id', $orig->id)->first();
        $this->assertNotNull($copy->quiz_id);
        $this->assertNotSame($quiz->id, $copy->quiz_id, 'Quiz duplicato, non condiviso');
        $newQuiz = Quiz::find($copy->quiz_id);
        $this->assertSame(3, $newQuiz->questions()->count());
        $this->assertNull($newQuiz->module_id);
        $this->assertNull($newQuiz->course_id);
    }

    public function test_fork_chain_attribution_shown(): void
    {
        $a = $this->prof('Anna'); $b = $this->prof('Bruno'); $c = $this->prof('Carla');
        $x = $this->artifact($a, ['title' => 'X', 'shared_with_teachers' => true]);

        // B forka X → Y, poi condivide Y
        $this->asProf($b)->post(route('docente.biblioteca.fork', $x))->assertRedirect();
        $y = TeachingArtifact::where('teacher_id', $b->id)->where('origin_artifact_id', $x->id)->firstOrFail();
        $y->update(['shared_with_teachers' => true]);

        // C apre Y in biblioteca → vede attribuzione Bruno + catena origin X di Anna
        $resp = $this->asProf($c)->get(route('docente.biblioteca.show', $y));
        $resp->assertOk()->assertSee('Bruno')->assertSee('X')->assertSee('Anna');

        // C forka Y → Z (origin Y)
        $this->asProf($c)->post(route('docente.biblioteca.fork', $y))->assertRedirect();
        $z = TeachingArtifact::where('teacher_id', $c->id)->where('origin_artifact_id', $y->id)->firstOrFail();
        $this->assertSame($y->id, $z->origin_artifact_id);
    }

    public function test_fork_requires_shared_artifact(): void
    {
        $a = $this->prof(); $b = $this->prof();
        $private = $this->artifact($a, ['shared_with_teachers' => false]);
        $this->asProf($b)->post(route('docente.biblioteca.fork', $private))->assertForbidden();
    }
}
