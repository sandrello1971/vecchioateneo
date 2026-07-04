<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Biblioteca docenti (§6): artefatti condivisi tra professori, semantica fork.
// Sola lettura sugli artefatti shared; il fork crea una COPIA INDIPENDENTE
// nell'archivio del richiedente (nessun riferimento vivo).
class TeacherLibraryController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    public function index(Request $request)
    {
        $query = TeachingArtifact::query()
            ->where('shared_with_teachers', true)
            ->where('status', 'ready')
            ->with(['teacher:id,name', 'subject:id,name']);

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }
        if ($request->filled('q')) {
            $query->where('title', 'ILIKE', '%' . $request->input('q') . '%');
        }

        $artifacts = $query->orderByDesc('created_at')->get();
        $subjects = Subject::orderBy('name')->get();

        // Materiali grezzi visibili in Biblioteca: di scuola (admin) + condivisi con la
        // scuola/materia. Importabili nel proprio pool (utilizzabili nelle lezioni).
        $teacher = \App\Models\Student::find($this->teacherId());
        $materials = \App\Models\TeachingDocument::visibleAsSharedTo($teacher)
            ->with(['teacher:id,name', 'subject:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return view('docente.biblioteca.index', compact('artifacts', 'subjects', 'materials'));
    }

    public function show(TeachingArtifact $artifact)
    {
        abort_unless($artifact->shared_with_teachers, 404);
        $artifact->load(['teacher:id,name', 'subject:id,name', 'teachingDocument']);

        $graph = null;
        $quiz = null;
        if ($artifact->type === 'conceptmap' && !empty($artifact->content)) {
            $decoded = json_decode($artifact->content, true);
            $graph = is_array($decoded) ? $decoded : ['nodes' => [], 'edges' => []];
        }
        if ($artifact->type === 'quiz' && $artifact->quiz_id) {
            $quiz = $artifact->quiz()->with('questions')->first();
        }

        return view('docente.biblioteca.show', [
            'artifact' => $artifact,
            'graph' => $graph,
            'quiz' => $quiz,
            'chain' => $this->originChain($artifact),
            'isOwner' => $artifact->teacher_id === $this->teacherId(),
        ]);
    }

    /**
     * Duplica l'artefatto nell'archivio del richiedente: copia profonda,
     * origin_artifact_id valorizzato (attribuzione), SENZA documento grezzo.
     * Per i quiz duplica quiz + domande. Il fork è un artefatto normale del
     * nuovo proprietario (modificabile/pubblicabile/condivisibile).
     */
    public function fork(TeachingArtifact $artifact)
    {
        abort_unless($artifact->shared_with_teachers, 403);
        $teacherId = $this->teacherId();

        $copy = DB::transaction(function () use ($artifact, $teacherId) {
            $newQuizId = ($artifact->type === 'quiz' && $artifact->quiz_id)
                ? $this->duplicateQuiz($artifact->quiz_id)
                : null;

            return TeachingArtifact::create([
                'teaching_document_id' => null, // il fork NON porta il documento grezzo
                'teacher_id' => $teacherId,
                'type' => $artifact->type,
                'title' => $artifact->title,
                'content' => $artifact->content,
                'quiz_id' => $newQuizId,
                'status' => 'ready',
                'generation_meta' => array_merge((array) $artifact->generation_meta, [
                    'forked_from' => $artifact->id,
                ]),
                'shared_with_teachers' => false,
                'origin_artifact_id' => $artifact->id,
                'subject_id' => $artifact->subject_id,
                'tags' => $artifact->tags,
            ]);
        });

        return redirect()->route('docente.artifacts.show', $copy)
            ->with('success', 'Copiato nella tua libreria: ora è tuo. Puoi modificarlo, pubblicarlo o condividerlo.');
    }

    private function duplicateQuiz(string $quizId): ?string
    {
        $orig = Quiz::with('questions')->find($quizId);
        if (!$orig) {
            return null;
        }

        $new = Quiz::create([
            'module_id' => null, 'course_id' => null,
            'title' => $orig->title,
            'description' => $orig->description,
            'passing_score' => $orig->passing_score,
            'is_active' => true,
            'randomize_questions' => $orig->randomize_questions,
            'show_results_immediately' => $orig->show_results_immediately,
        ]);

        foreach ($orig->questions as $q) {
            QuizQuestion::create([
                'quiz_id' => $new->id,
                'question' => $q->question,
                'type' => $q->type,
                'options' => $q->options,
                'correct_answer' => $q->correct_answer,
                'explanation' => $q->explanation,
                'points' => $q->points,
                'sort_order' => $q->sort_order,
            ]);
        }

        return $new->id;
    }

    /**
     * Catena di attribuzione risalendo origin_artifact_id (anche se l'originale
     * è stato soft-deleted: l'attribuzione sopravvive).
     *
     * @return array<int, array{title:string,author:?string,deleted:bool}>
     */
    private function originChain(TeachingArtifact $artifact): array
    {
        $chain = [];
        $current = $artifact;
        $guard = 0;

        while ($current->origin_artifact_id && $guard++ < 10) {
            $parent = TeachingArtifact::withTrashed()->with('teacher:id,name')->find($current->origin_artifact_id);
            if (!$parent) {
                break;
            }
            $chain[] = [
                'title' => $parent->title,
                'author' => $parent->teacher?->name,
                'deleted' => $parent->trashed(),
            ];
            $current = $parent;
        }

        return $chain;
    }
}
