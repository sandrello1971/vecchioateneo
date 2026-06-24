<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\Student;
use App\Support\ExamState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    public function index()
    {
        // I quiz Schola (output di un teaching_artifact, module_id/course_id NULL)
        // vivono fuori dal mondo corsi: esclusi da questa lista admin.
        $quizzes = Quiz::with(['course', 'module'])
            ->withCount(['questions', 'attempts'])
            ->whereDoesntHave('teachingArtifact')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.quizzes.index', compact('quizzes'));
    }

    public function create()
    {
        $courses = Course::where('is_active', true)->orderBy('sort_order')->get();
        $modules = Module::with('course')->where('is_active', true)->get();
        return view('admin.quizzes.create', compact('courses', 'modules'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|uuid',
            'module_id' => 'nullable|uuid',
            'passing_score' => 'required|integer|min:0|max:100',
            'time_limit_minutes' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:0',
            'randomize_questions' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $data['randomize_questions'] = isset($data['randomize_questions']);
        $data['is_active'] = isset($data['is_active']);
        // I campi 'nullable' assenti dalla request NON compaiono nei dati
        // validati: usare $data['k'] ?: null darebbe "Undefined array key".
        // (?? null) gestisce la chiave assente; ?: null preserva la semantica
        // "vuoto/0 = null" (es. 0 tentativi = illimitato).
        $data['time_limit_minutes'] = ($data['time_limit_minutes'] ?? null) ?: null;
        $data['max_attempts'] = ($data['max_attempts'] ?? null) ?: null;
        $data['course_id'] = ($data['course_id'] ?? null) ?: null;
        $data['module_id'] = $data['module_id'] ?? null ?: null;

        $quiz = Quiz::create($data);

        return redirect("/admin/quizzes/{$quiz->id}/questions")
            ->with('success', 'Quiz creato. Aggiungi le domande.');
    }

    public function show(string $id)
    {
        $quiz = Quiz::with(['course', 'module', 'questions'])->findOrFail($id);
        return view('admin.quizzes.show', compact('quiz'));
    }

    public function edit(string $id)
    {
        $quiz = Quiz::findOrFail($id);
        $courses = Course::where('is_active', true)->orderBy('sort_order')->get();
        return view('admin.quizzes.edit', compact('quiz', 'courses'));
    }

    public function update(Request $request, string $id)
    {
        $quiz = Quiz::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|uuid',
            'passing_score' => 'required|integer|min:0|max:100',
            'time_limit_minutes' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:0',
            'randomize_questions' => 'nullable',
            'questions_per_attempt' => 'nullable|integer|min:1',
            'is_active' => 'nullable',
        ]);

        // Pool: questions_per_attempt deve essere ≤ numero di domande del quiz
        // (non puoi estrarre 20 da 15). Vuoto/0 = NULL = somministra tutte.
        $perAttempt = ($data['questions_per_attempt'] ?? null) ?: null;
        if ($perAttempt !== null) {
            $pool = $quiz->questions()->count();
            if ($perAttempt > $pool) {
                return back()->withInput()->with('error',
                    "Non puoi estrarre {$perAttempt} domande da un pool di {$pool}. Riduci il valore o genera più domande.");
            }
        }
        $data['questions_per_attempt'] = $perAttempt;

        $data['randomize_questions'] = isset($data['randomize_questions']);
        $data['is_active'] = isset($data['is_active']);
        // I campi 'nullable' assenti dalla request NON compaiono nei dati
        // validati: usare $data['k'] ?: null darebbe "Undefined array key".
        // (?? null) gestisce la chiave assente; ?: null preserva la semantica
        // "vuoto/0 = null" (es. 0 tentativi = illimitato).
        $data['time_limit_minutes'] = ($data['time_limit_minutes'] ?? null) ?: null;
        $data['max_attempts'] = ($data['max_attempts'] ?? null) ?: null;
        $data['course_id'] = ($data['course_id'] ?? null) ?: null;

        $quiz->update($data);

        return redirect()->route('admin.quizzes.index')->with('success', 'Quiz aggiornato.');
    }

    public function destroy(string $id)
    {
        Quiz::findOrFail($id)->delete();
        return redirect()->route('admin.quizzes.index')->with('success', 'Quiz eliminato.');
    }

    public function results(string $id)
    {
        $quiz = Quiz::with(['attempts.student', 'questions', 'course'])->findOrFail($id);

        // Aggregato per studente per la UI override: per ogni studente che
        // ha tentato, mostra used/max e se è esaurito.
        $examState = app(ExamState::class);
        $perStudent = $quiz->attempts
            ->filter(fn ($a) => $a->student !== null)
            ->groupBy('student_id')
            ->map(function ($attempts, $studentId) use ($quiz, $examState) {
                $completed = $attempts->whereNotNull('completed_at');
                $max = $examState->effectiveMaxAttempts($quiz, $studentId);
                $used = $completed->count();
                $passed = $attempts->where('passed', true)->isNotEmpty();
                $grant = DB::table('exam_attempt_grants')
                    ->where('quiz_id', $quiz->id)
                    ->where('student_id', $studentId)
                    ->value('extra_attempts') ?? 0;
                return (object) [
                    'student' => $attempts->first()->student,
                    'used'    => $used,
                    'max'     => $max,
                    'passed'  => $passed,
                    'extra_granted' => (int) $grant,
                    'exhausted' => $max !== null && $used >= $max && !$passed,
                ];
            })
            ->sortByDesc('exhausted')
            ->values();

        return view('admin.quizzes.results', compact('quiz', 'perStudent'));
    }

    public function grantAttempt(Request $request, string $id)
    {
        $quiz = Quiz::findOrFail($id);

        // Override sensato solo per i quiz d'esame.
        abort_unless(app(ExamState::class)->isExamQuiz($quiz), 422,
            'L\'override tentativi si applica solo ai quiz d\'esame (a livello corso).');

        $data = $request->validate([
            'student_id'     => 'required|uuid|exists:students,id',
            'extra_attempts' => 'nullable|integer|min:1|max:10',
            'reason'         => 'nullable|string|max:500',
        ]);

        $n = $data['extra_attempts'] ?? 1;
        $adminEmail = session('admin_email') ?? 'unknown';
        $reason = $data['reason'] ?? null;

        DB::transaction(function () use ($quiz, $data, $n, $adminEmail, $reason) {
            $existing = DB::table('exam_attempt_grants')
                ->where('quiz_id', $quiz->id)
                ->where('student_id', $data['student_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                DB::table('exam_attempt_grants')
                    ->where('id', $existing->id)
                    ->update([
                        'extra_attempts' => $existing->extra_attempts + $n,
                        'granted_by'     => $adminEmail,
                        'reason'         => $reason ?? $existing->reason,
                        'updated_at'     => now(),
                    ]);
                $newTotal = $existing->extra_attempts + $n;
            } else {
                DB::table('exam_attempt_grants')->insert([
                    'id'             => (string) \Illuminate\Support\Str::uuid(),
                    'quiz_id'        => $quiz->id,
                    'student_id'     => $data['student_id'],
                    'extra_attempts' => $n,
                    'granted_by'     => $adminEmail,
                    'reason'         => $reason,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $newTotal = $n;
            }

            $studentEmail = Student::where('id', $data['student_id'])->value('email');
            Log::warning('[exam] tentativo extra concesso', [
                'admin'          => $adminEmail,
                'student_email'  => $studentEmail,
                'quiz_id'        => $quiz->id,
                'added'          => $n,
                'new_total_extra' => $newTotal,
                'reason'         => $reason,
            ]);
        });

        return back()->with('success', "Concessi +{$n} tentativi.");
    }
}
