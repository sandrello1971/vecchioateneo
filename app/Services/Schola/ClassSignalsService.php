<?php

namespace App\Services\Schola;

use App\Models\ArtifactPublication;
use App\Models\ChatMessage;
use App\Models\ClassStudent;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentArtifactView;
use App\Models\StudentGeneratedArtifact;
use App\Models\TeachingDocument;
use App\Models\UnansweredQuestion;
use App\Services\EmbeddingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Aggregazioni dei segnali di classe per il cruscotto docente.
 *
 * PRINCIPIO (CLAUDE.md "Agente proattivo"): questi dati sono l'input del futuro
 * agente proattivo. Tutte le aggregazioni stanno QUI, in metodi puri e
 * testabili — MAI query inline nei controller. Cruscotto e agente leggono dagli
 * stessi metodi.
 */
class ClassSignalsService
{
    public function __construct(private EmbeddingService $embeddings) {}

    // ===== Roster =====

    /** @return Collection<int,string> id degli studenti con iscrizione ATTIVA */
    public function activeStudentIds(SchoolClass $class): Collection
    {
        return ClassStudent::where('school_class_id', $class->id)
            ->where('status', 'active')
            ->pluck('student_id');
    }

    // ===== Copertura =====

    /**
     * Per ogni pubblicazione: quanti studenti attivi l'hanno aperta vs roster.
     *
     * @return array<int, array{publication_id:string,title:string,type:string,published_at:?string,opened:int,total:int,pct:int}>
     */
    public function coverageByPublication(SchoolClass $class): array
    {
        $activeIds = $this->activeStudentIds($class);
        $total = $activeIds->count();

        $publications = $class->publications()
            ->whereHas('artifact', fn ($q) => $q->where('status', 'ready'))
            ->with('artifact:id,type,title')
            ->orderByDesc('published_at')
            ->get();

        return $publications->map(function (ArtifactPublication $pub) use ($activeIds, $total) {
            $opened = StudentArtifactView::where('artifact_publication_id', $pub->id)
                ->whereIn('student_id', $activeIds)
                ->count();

            return [
                'publication_id' => $pub->id,
                'title' => $pub->artifact?->title ?? '—',
                'type' => $pub->artifact?->type ?? '—',
                'published_at' => $pub->published_at?->toDateString(),
                'opened' => $opened,
                'total' => $total,
                'pct' => $total > 0 ? (int) round($opened / $total * 100) : 0,
            ];
        })->all();
    }

    // ===== Quiz: punti critici =====

    /**
     * Mappa publication_id => [quiz_id...] per la classe: quiz pubblicati
     * (artefatto quiz) + quiz di autoverifica auto-generati dagli studenti (§8.1).
     *
     * @return array<string, array<int,string>>
     */
    private function quizIdsByPublication(SchoolClass $class): array
    {
        $map = [];

        $publications = $class->publications()->with('artifact:id,type,quiz_id')->get();
        $pubIds = $publications->pluck('id');

        foreach ($publications as $pub) {
            $ids = [];
            if ($pub->artifact && $pub->artifact->type === 'quiz' && $pub->artifact->quiz_id) {
                $ids[] = $pub->artifact->quiz_id;
            }
            $map[$pub->id] = ['title' => $pub->artifact?->title ?? '—', 'quiz_ids' => $ids];
        }

        // Quiz auto-generati raggruppati per pubblicazione.
        StudentGeneratedArtifact::whereIn('artifact_publication_id', $pubIds)
            ->where('type', 'quiz')
            ->whereNotNull('quiz_id')
            ->get(['artifact_publication_id', 'quiz_id'])
            ->each(function ($g) use (&$map) {
                if (isset($map[$g->artifact_publication_id])) {
                    $map[$g->artifact_publication_id]['quiz_ids'][] = $g->quiz_id;
                }
            });

        return $map;
    }

    /**
     * Risultati quiz aggregati per pubblicazione: tentativi, media, distribuzione,
     * domande più sbagliate. Solo studenti attivi, tentativi completati (no abbandono).
     *
     * @return array<int, array>
     */
    public function quizPainPoints(SchoolClass $class): array
    {
        $activeIds = $this->activeStudentIds($class);
        $out = [];

        foreach ($this->quizIdsByPublication($class) as $pubId => $info) {
            $quizIds = array_values(array_unique($info['quiz_ids']));
            if (empty($quizIds)) {
                continue;
            }

            $attempts = QuizAttempt::whereIn('quiz_id', $quizIds)
                ->whereIn('student_id', $activeIds)
                ->whereNotNull('completed_at')
                ->where('abandoned', false)
                ->get(['id', 'score']);

            if ($attempts->isEmpty()) {
                continue;
            }

            $scores = $attempts->pluck('score')->map(fn ($s) => (int) $s);
            $dist = ['low' => 0, 'mid' => 0, 'high' => 0]; // <60 / 60-79 / >=80
            foreach ($scores as $s) {
                $dist[$s < 60 ? 'low' : ($s < 80 ? 'mid' : 'high')]++;
            }

            // Domande più sbagliate tra i tentativi in scope.
            $topWrong = QuizAnswer::query()
                ->whereIn('attempt_id', $attempts->pluck('id'))
                ->selectRaw('question_id,
                    count(*) as total,
                    count(*) filter (where is_correct = false) as wrong')
                ->groupBy('question_id')
                ->havingRaw('count(*) filter (where is_correct = false) > 0')
                ->orderByRaw('count(*) filter (where is_correct = false) desc')
                ->limit(5)
                ->with('question:id,question')
                ->get()
                ->map(fn ($r) => [
                    'question' => $r->question?->question ?? '—',
                    'wrong' => (int) $r->wrong,
                    'total' => (int) $r->total,
                ])->all();

            $out[] = [
                'publication_id' => $pubId,
                'title' => $info['title'],
                'attempts' => $attempts->count(),
                'avg_score' => (int) round($scores->avg()),
                'distribution' => $dist,
                'top_wrong' => $topWrong,
            ];
        }

        return $out;
    }

    // ===== Attività per studente =====

    /**
     * Per ogni studente attivo: ultima visita, viste, tentativi, messaggi
     * Minerva di classe, auto-generazioni.
     *
     * @return array<int, array>
     */
    public function studentActivity(SchoolClass $class): array
    {
        $students = ClassStudent::where('school_class_id', $class->id)
            ->where('status', 'active')
            ->with('student:id,name,email')
            ->get();
        $studentIds = $students->pluck('student_id');

        $pubIds = $class->publications()->pluck('id');
        $quizIds = collect($this->quizIdsByPublication($class))
            ->flatMap(fn ($i) => $i['quiz_ids'])->unique()->values();

        // Viste (somma view_count, ultima last_viewed_at) per studente.
        $views = StudentArtifactView::whereIn('artifact_publication_id', $pubIds)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, sum(view_count) as views, max(last_viewed_at) as last_visit')
            ->groupBy('student_id')->get()->keyBy('student_id');

        $attempts = $quizIds->isEmpty() ? collect() : QuizAttempt::whereIn('quiz_id', $quizIds)
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('completed_at')
            ->selectRaw('student_id, count(*) as c')
            ->groupBy('student_id')->get()->keyBy('student_id');

        $chat = ChatMessage::query()
            ->join('chat_conversations', 'chat_messages.conversation_id', '=', 'chat_conversations.id')
            ->where('chat_messages.role', 'user')
            ->where('chat_conversations.school_class_id', $class->id)
            ->whereIn('chat_conversations.student_id', $studentIds)
            ->selectRaw('chat_conversations.student_id as sid, count(*) as c')
            ->groupBy('chat_conversations.student_id')->get()->keyBy('sid');

        $gens = StudentGeneratedArtifact::whereIn('artifact_publication_id', $pubIds)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, count(*) as c')
            ->groupBy('student_id')->get()->keyBy('student_id');

        return $students->map(function ($cs) use ($views, $attempts, $chat, $gens) {
            $sid = $cs->student_id;
            $lastVisit = $views[$sid]->last_visit ?? null;

            return [
                'student_id' => $sid,
                'name' => $cs->student?->name ?? '—',
                'last_visit' => $lastVisit ? Carbon::parse($lastVisit)->toDateTimeString() : null,
                'views' => (int) ($views[$sid]->views ?? 0),
                'attempts' => (int) ($attempts[$sid]->c ?? 0),
                'chat_messages' => (int) ($chat[$sid]->c ?? 0),
                'generations' => (int) ($gens[$sid]->c ?? 0),
            ];
        })->all();
    }

    /**
     * Studenti attivi inattivi da più di $days giorni (o mai entrati).
     *
     * @return array<int, array>
     */
    public function inactiveStudents(SchoolClass $class, int $days = 7): array
    {
        $cutoff = Carbon::now()->subDays($days);

        return collect($this->studentActivity($class))
            ->filter(fn ($a) => $a['last_visit'] === null || Carbon::parse($a['last_visit'])->lt($cutoff))
            ->values()
            ->all();
    }

    // ===== Domande scoperte: clustering =====

    /**
     * Domande "open" raggruppate per similarità (clustering greedy a soglia sugli
     * embedding). Fallback su lista piatta (un cluster per domanda) se gli
     * embedding non sono disponibili (videoai giù / pgvector assente).
     *
     * @return array<int, array{label:string,count:int,clustered:bool,questions:array}>
     */
    public function openQuestionClusters(SchoolClass $class): array
    {
        $questions = UnansweredQuestion::where('school_class_id', $class->id)
            ->where('status', 'open')
            ->with('student:id,name')
            ->orderBy('created_at')
            ->get();

        if ($questions->isEmpty()) {
            return [];
        }

        $toRow = fn (UnansweredQuestion $q) => [
            'id' => $q->id,
            'text' => $q->question,
            'created_at' => $q->created_at?->toDateTimeString(),
            'best_similarity' => $q->best_similarity,
            'student_name' => $q->student?->name, // NON anonimo (§8.1)
        ];

        // Tentativo di clustering vettoriale.
        try {
            $vectors = $this->embeddings->embed($questions->pluck('question')->all());
        } catch (Throwable) {
            $vectors = null;
        }

        if ($vectors === null || count($vectors) !== $questions->count()) {
            // Fallback: lista piatta (ogni domanda un cluster).
            return $questions->map(fn ($q) => [
                'label' => $q->question,
                'count' => 1,
                'clustered' => false,
                'questions' => [$toRow($q)],
            ])->all();
        }

        $threshold = (float) atheneum_setting('schola.question_cluster_threshold', 0.6);
        $clusters = []; // ['rep' => vector, 'items' => [question...]]

        $questions->values()->each(function ($q, $i) use (&$clusters, $vectors, $threshold) {
            $v = $vectors[$i];
            foreach ($clusters as &$c) {
                if ($this->cosine($c['rep'], $v) >= $threshold) {
                    $c['items'][] = $q;
                    return;
                }
            }
            unset($c);
            $clusters[] = ['rep' => $v, 'items' => [$q]];
        });

        return collect($clusters)->map(fn ($c) => [
            'label' => $c['items'][0]->question,
            'count' => count($c['items']),
            'clustered' => true,
            'questions' => array_map($toRow, $c['items']),
        ])->all();
    }

    private function cosine(array $a, array $b): float
    {
        // Vettori già normalizzati (L2=1) dal servizio embeddings → dot product.
        $dot = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
        }

        return $dot;
    }

    // ===== Dashboard docente (cross-classe) =====

    /**
     * Riepilogo cross-classe per la dashboard del docente.
     *
     * @return array
     */
    public function teacherDashboard(Student $teacher): array
    {
        $classIds = SchoolClass::where('teacher_id', $teacher->id)->pluck('id');

        $pendingApprovals = ClassStudent::whereIn('school_class_id', $classIds)
            ->where('status', 'pending')
            ->with(['student:id,name,email', 'schoolClass:id,name'])
            ->orderBy('created_at')
            ->get();

        $docsInFlight = TeachingDocument::where('teacher_id', $teacher->id)
            ->whereIn('status', ['pending', 'processing', 'failed'])
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'status', 'failure_reason', 'updated_at']);

        $openQuestions = UnansweredQuestion::whereIn('school_class_id', $classIds)
            ->where('status', 'open')
            ->count();

        $recentViews = StudentArtifactView::whereHas('publication',
                fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->with(['student:id,name', 'publication.artifact:id,title', 'publication.schoolClass:id,name'])
            ->orderByDesc('last_viewed_at')
            ->limit(8)
            ->get();

        return [
            'classes_count' => $classIds->count(),
            'pending_approvals' => $pendingApprovals,
            'docs_in_flight' => $docsInFlight,
            'open_questions' => $openQuestions,
            'recent_views' => $recentViews,
        ];
    }

    public function openQuestionsCount(SchoolClass $class): int
    {
        return UnansweredQuestion::where('school_class_id', $class->id)
            ->where('status', 'open')
            ->count();
    }
}
