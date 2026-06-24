<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;

class QuizQuestionController extends Controller
{
    public function index(Quiz $quiz)
    {
        $questions = $quiz->questions()->orderBy('sort_order')->get();
        return view('admin.quizzes.questions', compact('quiz', 'questions'));
    }

    public function create(Quiz $quiz)
    {
        $question = new QuizQuestion(['type' => 'multiple_choice', 'points' => 1]);
        return view('admin.quizzes.questions.edit', compact('quiz', 'question'));
    }

    public function store(Request $request, Quiz $quiz)
    {
        return $this->persist($request, $quiz, new QuizQuestion([
            'quiz_id' => $quiz->id,
            'sort_order' => ($quiz->questions()->max('sort_order') ?? 0) + 1,
        ]));
    }

    public function edit(Quiz $quiz, QuizQuestion $question)
    {
        return view('admin.quizzes.questions.edit', compact('quiz', 'question'));
    }

    public function update(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        return $this->persist($request, $quiz, $question);
    }

    private function persist(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        $data = $request->validate([
            'question' => 'required|string',
            'type' => 'required|in:multiple_choice,true_false,open',
            'options_text' => 'nullable|string',
            'explanation' => 'nullable|string',
            'points' => 'required|integer|min:1',
        ]);

        $options = [];
        $correct = null;
        if (!empty($data['options_text'])) {
            foreach (explode("\n", $data['options_text']) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $isCorrect = false;
                if (str_starts_with($line, '✓')) {
                    $line = trim(substr($line, strlen('✓')));
                    $isCorrect = true;
                } elseif (str_starts_with($line, '* ')) {
                    $line = trim(substr($line, 2));
                    $isCorrect = true;
                }
                if ($isCorrect) {
                    $correct = $line;
                }
                $options[] = $line;
            }
        }

        $question->fill([
            'quiz_id' => $quiz->id,
            'question' => $data['question'],
            'type' => $data['type'],
            'options' => $options ?: null,
            'correct_answer' => $correct,
            'explanation' => $data['explanation'] ?? null,
            'points' => $data['points'],
        ]);

        if (!$question->sort_order) {
            $question->sort_order = ($quiz->questions()->max('sort_order') ?? 0) + 1;
        }

        $question->save();

        return redirect("/admin/quizzes/{$quiz->id}/questions")
            ->with('success', 'Domanda salvata.');
    }

    public function destroy(Quiz $quiz, QuizQuestion $question)
    {
        $question->delete();
        return back()->with('success', 'Domanda eliminata.');
    }
}
