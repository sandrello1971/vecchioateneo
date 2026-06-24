<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Http\Controllers\Student\Concerns\EvaluatesExamState;
use App\Mail\CertificationPassedMail;
use App\Models\Certificate;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentGeneratedArtifact;
use App\Models\TeachingArtifact;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class QuizController extends Controller
{
    use DeterminesTeachingMode;
    use EvaluatesExamState;

    /**
     * Gate di accesso per i quiz SCHOLA (module_id e course_id entrambi NULL):
     * lo studente può accedere solo se il quiz è (a) un artefatto quiz pubblicato
     * in una sua classe attiva, oppure (b) un quiz di autoverifica auto-generato
     * da lui. Per i quiz del MONDO CORSI (course/module valorizzati) è un no-op
     * → nessuna regressione.
     */
    private function guardScholaQuiz(Quiz $quiz, ?string $studentId): void
    {
        if ($quiz->module_id !== null || $quiz->course_id !== null) {
            return; // quiz del mondo corsi: comportamento invariato
        }

        $activeClassIds = ClassStudent::where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('school_class_id');

        $viaPublication = TeachingArtifact::where('quiz_id', $quiz->id)
            ->whereHas('publications', fn ($q) => $q->whereIn('school_class_id', $activeClassIds))
            ->exists();

        $viaSelfGenerated = StudentGeneratedArtifact::where('quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->exists();

        abort_unless($viaPublication || $viaSelfGenerated, 403,
            'Non hai accesso a questo quiz.');
    }

    public function show(Quiz $quiz)
    {
        $student = Student::findOrFail(session('student_id'));
        $this->guardScholaQuiz($quiz, $student->id);

        if ($student->is_demo && !$quiz->is_demo) {
            abort(403, 'In modalità demo puoi vedere solo il quiz di prova.');
        }
        if (!$student->is_demo && $quiz->is_demo) {
            abort(404);
        }

        // Le domande NON sono più pre-embeddate: vengono consegnate da start() (il
        // sottoinsieme estratto per il tentativo). Qui servono solo i conteggi per l'intro.
        $poolCount = $quiz->questions()->count();
        $displayCount = $quiz->effectiveQuestionCount();

        $pastAttempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $course = $quiz->course ?? $quiz->module?->course;

        $nextModule = null;
        if ($quiz->module_id && $quiz->module) {
            $nextModule = Module::where('course_id', $quiz->module->course_id)
                ->where('sort_order', '>', $quiz->module->sort_order)
                ->orderBy('sort_order')
                ->first();
        }

        // Advisory cap info — solo per gli esami. Il controllo autoritativo
        // resta server-side in start(). Qui serve a disabilitare il bottone
        // e mostrare il contatore.
        $effectiveMax = null;
        $usedAttempts = 0;
        $alreadyPassed = false;
        if ($this->isExamQuiz($quiz) && !$student->is_demo) {
            $effectiveMax = $this->examState()->effectiveMaxAttempts($quiz, $student->id);
            $usedAttempts = QuizAttempt::where('quiz_id', $quiz->id)
                ->where('student_id', $student->id)
                ->whereNotNull('completed_at')
                ->count();
            $alreadyPassed = Certificate::where('student_id', $student->id)
                    ->where('course_id', $quiz->course_id)
                    ->exists()
                || QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('student_id', $student->id)
                    ->where('passed', true)
                    ->exists();
        }

        return view('student.quiz.show', compact(
            'quiz', 'poolCount', 'displayCount', 'pastAttempts', 'course', 'nextModule',
            'effectiveMax', 'usedAttempts', 'alreadyPassed'
        ));
    }

    public function start(Request $request, Quiz $quiz)
    {
        $student = Student::findOrFail(session('student_id'));
        $this->guardScholaQuiz($quiz, $student->id);

        if ($student->is_demo) {
            // Demo: nessuna persistenza; consegna tutte le domande del quiz demo.
            return response()->json([
                'attempt_id' => 'demo-' . uniqid(),
                'questions' => $this->questionsPayload($quiz, $quiz->questions()->pluck('id')->all()),
            ]);
        }

        $course = $this->courseForQuiz($quiz);
        if ($course && $this->isTeachingMode($student, $course)) {
            return response()->json([
                'error' => 'Modalità docenza: i quiz non vengono valutati né registrati.',
                'teaching' => true,
            ], 403);
        }

        // RIPRESA (tutti i quiz, formativi ed esami): se esiste GIÀ un tentativo aperto
        // per questo studente, lo si RIUSA restituendo le STESSE domande estratte
        // (selected_question_ids). Un refresh/ricarica della pagina ri-chiama start()
        // → ritrova il suo tentativo, niente nuovo attempt, niente fail, niente reroll
        // delle K. Il force-fail dell'incompleto NON avviene più qui: solo su abbandono
        // vero (abandon() beacon) o sul reaper dei tentativi stale.
        $open = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNull('completed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($open) {
            return response()->json([
                'attempt_id' => $open->id,
                'questions' => $this->questionsPayload($quiz, $open->selected_question_ids),
            ]);
        }

        // Tetto tentativi: solo per gli esami (quiz a livello corso).
        // Ordine: demo/teaching → ripresa tentativo aperto →
        // già-superato → tetto → creazione NUOVO attempt.
        if ($this->isExamQuiz($quiz)) {
            // Già superato: non bloccare con messaggio "esauriti",
            // segnale gentile dedicato.
            $alreadyPassed = Certificate::where('student_id', $student->id)
                    ->where('course_id', $quiz->course_id)
                    ->exists()
                || QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('student_id', $student->id)
                    ->where('passed', true)
                    ->exists();

            if ($alreadyPassed) {
                return response()->json([
                    'error' => 'Hai già superato questo esame.',
                    'already_passed' => true,
                ], 409);
            }

            $max = $this->examState()->effectiveMaxAttempts($quiz, $student->id);

            if ($max !== null) {
                $used = QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('student_id', $student->id)
                    ->whereNotNull('completed_at') // include gli abbandonati
                    ->count();

                if ($used >= $max) {
                    Log::info('Quiz start bloccato: tentativi esauriti', [
                        'student_id' => $student->id,
                        'quiz_id'    => $quiz->id,
                        'used'       => $used,
                        'max'        => $max,
                    ]);
                    return response()->json([
                        'error' => "Hai esaurito i tentativi disponibili per questo esame ({$used}/{$max}).",
                        'attempts_exhausted' => true,
                        'used' => $used,
                        'max'  => $max,
                    ], 403);
                }
            }
        }

        // Estrazione del sottoinsieme per QUESTO tentativo (K casuali dal pool, o
        // tutte se questions_per_attempt è null) e persistenza per stabilità/punteggio.
        $selectedIds = $quiz->buildAttemptQuestionIds();

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now(),
            'attempt_number' => QuizAttempt::where('quiz_id', $quiz->id)
                ->where('student_id', $student->id)
                ->count() + 1,
            'selected_question_ids' => $selectedIds,
        ]);

        return response()->json([
            'attempt_id' => $attempt->id,
            'questions' => $this->questionsPayload($quiz, $selectedIds),
        ]);
    }

    public function abandon(Request $request, Quiz $quiz)
    {
        $student = Student::findOrFail(session('student_id'));
        $this->guardScholaQuiz($quiz, $student->id);

        // Demo: no-op
        if ($student->is_demo) {
            return response()->noContent();
        }
        // Abbandono vero = force-fail per TUTTI i quiz (formativi ed esami): è il
        // criterio "abbandono reale vs refresh tecnico". Il refresh ri-chiama start()
        // e riprende il tentativo; abandon() (beacon su chiusura/navigazione) lo chiude.

        // CSRF: la rotta è web ma il beacon usa sendBeacon → no header CSRF.
        // Validiamo il token manualmente dal body per non indebolire la
        // protezione CSRF globale via $except.
        $tokenFromBody = $request->input('_token');
        if (!$tokenFromBody || !hash_equals((string) csrf_token(), (string) $tokenFromBody)) {
            abort(419, 'CSRF token mismatch');
        }

        $incomplete = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNull('completed_at')
            ->get();

        // Idempotente: nessun tentativo aperto → 204
        if ($incomplete->isEmpty()) {
            return response()->noContent();
        }

        foreach ($incomplete as $att) {
            $att->update([
                'completed_at' => now(),
                'score'        => 0,
                'passed'       => false,
                'abandoned'    => true,
                'time_spent_seconds' => (int) max(0,
                    $att->started_at->diffInSeconds(now())),
            ]);
            Log::info('Quiz attempt abandoned (beacon)', [
                'attempt_id' => $att->id,
                'student_id' => $student->id,
                'quiz_id'    => $quiz->id,
            ]);
        }

        return response()->noContent();
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);
        $this->guardScholaQuiz($quiz, $studentId);

        $student = Student::findOrFail($studentId);

        // Branch demo: nessuna persistenza, calcoliamo lo score lato server dalle
        // domande del DB per restituire le corrections al client. start() per gli
        // studenti demo restituisce 'demo-XXX' invece di un UUID valido, quindi la
        // validazione `attempt_id|exists` non si applica qui.
        if ($student->is_demo) {
            $data = Validator::make($request->all(), [
                'answers' => 'present|array',
                'answers.*' => 'nullable|string|max:1000',
            ])->validate();

            return response()->json(array_merge(
                ['success' => true, 'demo' => true],
                $this->scorePayload($quiz, $data['answers'])
            ));
        }

        $course = $this->courseForQuiz($quiz);
        if ($course && $this->isTeachingMode($student, $course)) {
            return response()->json([
                'error' => 'Modalità docenza: il quiz non viene valutato né registrato.',
                'teaching' => true,
            ], 403);
        }

        $data = Validator::make($request->all(), [
            'attempt_id' => 'required|uuid|exists:quiz_attempts,id',
            'answers' => 'present|array',
            'answers.*' => 'nullable|string|max:1000',
        ])->validate();

        $attempt = QuizAttempt::where('id', $data['attempt_id'])
            ->where('student_id', $studentId)
            ->where('quiz_id', $quiz->id)
            ->firstOrFail();

        // Anti-replay: 409 esplicito (non 404) per dare al frontend un segnale
        // utile a disabilitare il bottone in caso di doppio submit accidentale.
        abort_if($attempt->completed_at !== null, 409, 'Tentativo già consegnato.');

        // Punteggio SOLO sulle domande estratte per questo tentativo (selected_question_ids).
        // Fallback retrocompat: tentativi vecchi/null senza selezione → tutte le domande.
        $questions = $this->attemptQuestions($quiz, $attempt);
        $totalPoints = $questions->sum('points') ?: $questions->count();
        $earnedPoints = 0;

        DB::transaction(function () use ($attempt, $questions, $data, &$earnedPoints) {
            QuizAnswer::where('attempt_id', $attempt->id)->delete();

            // Iteriamo sulle domande del quiz (autorevole), non sulle chiavi del payload:
            // question_id ignote nel request vengono silenziosamente ignorate, e le
            // domande non risposte risultano answer=null, is_correct=false, points=0.
            foreach ($questions as $qid => $question) {
                $given = $data['answers'][$qid] ?? null;
                $isCorrect = $given !== null && $given === $question->correct_answer;
                $points = $isCorrect ? ($question->points ?? 1) : 0;
                $earnedPoints += $points;

                QuizAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $qid,
                    'answer' => $given,
                    'is_correct' => $isCorrect,
                    'points_earned' => $points,
                ]);
            }
        });

        $score = $totalPoints > 0 ? (int) round(($earnedPoints / $totalPoints) * 100) : 0;
        $passed = $score >= $quiz->passing_score;

        $timeSpent = (int) max(0, $attempt->started_at->diffInSeconds(now()));

        // Time-limit superato: accettiamo comunque il submit ma logghiamo per audit.
        if ($quiz->time_limit_minutes && $timeSpent > $quiz->time_limit_minutes * 60) {
            Log::warning('Quiz submit oltre time_limit', [
                'attempt_id' => $attempt->id,
                'student_id' => $studentId,
                'time_spent_seconds' => $timeSpent,
                'time_limit_seconds' => $quiz->time_limit_minutes * 60,
            ]);
        }

        $attempt->update([
            'completed_at' => now(),
            'score' => $score,
            'passed' => $passed,
            'time_spent_seconds' => $timeSpent,
        ]);

        if ($passed && $quiz->course_id && !$quiz->module_id) {
            $course = $quiz->course;
            if ($course) {
                $certificate = $this->issueCertificate($student, $course, $quiz, $attempt, $score);

                if ($certificate) {
                    try {
                        Mail::to($student->email)
                            ->queue(new CertificationPassedMail($student, $course, $certificate));
                    } catch (\Throwable $e) {
                        Log::error('Email certificato fallita: ' . $e->getMessage());
                    }
                }
            }
        }

        return response()->json(array_merge(
            ['success' => true],
            $this->scorePayload($quiz, $data['answers'], $questions, $score, $passed)
        ));
    }

    public function result(Quiz $quiz, QuizAttempt $attempt)
    {
        $studentId = session('student_id');
        abort_unless($studentId && $attempt->student_id === $studentId, 403);
        $this->guardScholaQuiz($quiz, $studentId);
        abort_unless($attempt->quiz_id === $quiz->id, 404);

        $attempt->load(['answers.question']);

        return view('student.quiz.result', compact('quiz', 'attempt'));
    }

    /**
     * Costruisce il payload (score, passed, corrections) restituito al client dopo
     * il submit. corrections è una mappa { qid: {correct_answer, explanation} }
     * per consentire lookup O(1) lato view.
     */
    private function scorePayload(
        Quiz $quiz,
        array $answers,
        $questions = null,
        ?int $score = null,
        ?bool $passed = null
    ): array {
        $questions = $questions ?? $quiz->questions()->get()->keyBy('id');

        if ($score === null) {
            $totalPoints = $questions->sum('points') ?: $questions->count();
            $earned = 0;
            foreach ($questions as $qid => $q) {
                $given = $answers[$qid] ?? null;
                if ($given !== null && $given === $q->correct_answer) {
                    $earned += ($q->points ?? 1);
                }
            }
            $score = $totalPoints > 0 ? (int) round(($earned / $totalPoints) * 100) : 0;
            $passed = $score >= $quiz->passing_score;
        }

        return [
            'score' => $score,
            'passed' => $passed,
            'corrections' => $questions->mapWithKeys(fn($q, $qid) => [
                $qid => [
                    'correct_answer' => $q->correct_answer,
                    'explanation' => $q->explanation,
                ],
            ]),
        ];
    }

    /**
     * Emette un certificato in modo idempotente per (studente, corso). Se il
     * certificato esiste già (ripassaggio del quiz), restituisce quello esistente
     * senza modificarne score né code: comportamento "primo passaggio vince".
     */
    private function issueCertificate(
        Student $student,
        $course,
        Quiz $quiz,
        QuizAttempt $attempt,
        int $score
    ): ?Certificate {
        try {
            $cert = Certificate::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                ],
                [
                    'quiz_attempt_id' => $attempt->id,
                    'code' => Certificate::generateCode(),
                    'score' => $score,
                    'issued_at' => now(),
                    'certification_name' => $course->certification_name
                        ?: ($course->name ?: 'Certificato Atheneum'),
                ]
            );
        } catch (UniqueConstraintViolationException $e) {
            // Race: due submit paralleli hanno entrambi visto "non esiste" e tentato
            // di inserire. La unique constraint DB ne ha bocciato uno; recuperiamo il vincente.
            $cert = Certificate::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->firstOrFail();
        } catch (\Throwable $e) {
            Log::error('Emissione certificato fallita: ' . $e->getMessage(), [
                'student_id' => $student->id,
                'course_id' => $course->id,
            ]);
            return null;
        }

        if ($cert->wasRecentlyCreated) {
            Log::info('Certificato emesso', [
                'certificate_id' => $cert->id,
                'code' => $cert->code,
                'student_id' => $student->id,
                'course_id' => $course->id,
                'quiz_attempt_id' => $attempt->id,
                'score' => $score,
            ]);
        }

        return $cert;
    }

    /**
     * Payload domande per il client, nell'ordine di $ids (selezione del tentativo),
     * SENZA correct_answer (mai esposta). $ids vuoto/null → tutte le domande.
     *
     * @param  list<string>|null  $ids
     */
    private function questionsPayload(Quiz $quiz, ?array $ids)
    {
        $all = $quiz->questions()->get()->keyBy('id');
        $ids = !empty($ids) ? $ids : $all->keys()->all();

        return collect($ids)
            ->map(fn ($id) => $all->get($id))
            ->filter()
            ->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'options' => $q->options ?? [],
            ])
            ->values();
    }

    /**
     * Domande su cui valutare un tentativo: SOLO le estratte (selected_question_ids),
     * keyed by id. Fallback retrocompat: nessuna selezione (tentativi vecchi o quiz
     * senza pool) → tutte le domande del quiz.
     */
    private function attemptQuestions(Quiz $quiz, QuizAttempt $attempt): \Illuminate\Support\Collection
    {
        $all = $quiz->questions()->get()->keyBy('id');
        $ids = $attempt->selected_question_ids;

        if (empty($ids)) {
            return $all;
        }

        return collect($ids)->map(fn ($id) => $all->get($id))->filter()->keyBy('id');
    }

    private function courseForQuiz(Quiz $quiz): ?Course
    {
        if ($quiz->course_id) {
            return $quiz->course;
        }

        return $quiz->module?->course;
    }
}
