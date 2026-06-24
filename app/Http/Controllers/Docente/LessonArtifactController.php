<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateArtifactJob;
use App\Models\Lesson;
use App\Models\TeachingArtifact;
use Illuminate\Http\Request;

// Artefatti a LIVELLO LEZIONE (P19): mindmap/quiz/riassunto/scaletta/conceptmap
// generati dal corpo della lezione composta, non dal singolo materiale. Riusa
// GenerateArtifactJob (teaching_artifacts.lesson_id) — nessuna logica duplicata.
class LessonArtifactController extends Controller
{
    private const GENERABLE = ['summary', 'mindmap', 'conceptmap', 'quiz', 'outline'];

    private function teacherId(): string
    {
        return session('student_id');
    }

    public function store(Request $request, Lesson $lesson)
    {
        abort_unless($lesson->teacher_id === $this->teacherId(), 403);
        abort_unless($lesson->generation_status === 'ready' && !empty($lesson->content), 422,
            'Componi prima il corpo della lezione: gli artefatti si generano da una lezione pronta.');

        $data = $request->validate([
            'type' => 'required|in:' . implode(',', self::GENERABLE),
            'level' => 'nullable|in:breve,medio,dispensa',
            'num_questions' => 'nullable|integer|min:3|max:20',
        ]);

        $options = $this->options($data);

        // Guard anti-doppio-submit (server): stesso tipo già in corso per la lezione.
        $inProgress = TeachingArtifact::where('lesson_id', $lesson->id)
            ->where('type', $data['type'])
            ->where('status', 'generating')
            ->first();
        if ($inProgress) {
            return redirect()->route('docente.artifacts.show', $inProgress)
                ->with('success', 'Generazione già in corso per questo tipo: eccola qui.');
        }

        $artifact = TeachingArtifact::create([
            'teaching_document_id' => null,        // artefatto di lezione, non di materiale
            'lesson_id' => $lesson->id,
            'teacher_id' => $lesson->teacher_id,
            'type' => $data['type'],
            'title' => $this->defaultTitle($data['type'], $lesson->title),
            'subject_id' => $lesson->topic?->subject_id,
            'status' => 'generating',
            'generation_meta' => ['requested_options' => $options],
        ]);

        GenerateArtifactJob::dispatch($artifact->id, $options)->afterResponse();

        return redirect()->route('docente.artifacts.show', $artifact)
            ->with('success', 'Generazione avviata. L\'artefatto sarà pronto a breve.');
    }

    private function options(array $data): array
    {
        return match ($data['type']) {
            'summary' => ['level' => $data['level'] ?? 'medio'],
            'quiz' => ['num_questions' => (int) ($data['num_questions'] ?? 10)],
            default => [],
        };
    }

    private function defaultTitle(string $type, string $lessonTitle): string
    {
        $prefix = match ($type) {
            'summary' => 'Riassunto',
            'mindmap' => 'Mappa mentale',
            'conceptmap' => 'Mappa concettuale',
            'quiz' => 'Quiz',
            'outline' => 'Scaletta',
            default => 'Artefatto',
        };

        return "{$prefix} — {$lessonTitle}";
    }
}
