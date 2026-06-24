<?php

namespace App\Jobs;

use App\Models\StudentGeneratedArtifact;
use App\Services\MindMapGenerationService;
use App\Services\QuizGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

// Auto-generazione lato studente: mindmap o quiz di autoverifica DAL CONTENUTO
// dell'artefatto PUBBLICATO (mai dal documento grezzo). Stato generating/ready/
// failed per il feedback UX. I quiz auto-generati nascono con module/course NULL
// (fuori dal mondo corsi) e i tentativi vanno su quiz_attempts (§8.1).
class StudentGenerateArtifactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public string $generatedId, public array $options = []) {}

    public function handle(MindMapGenerationService $mindMap, QuizGeneratorService $quiz): void
    {
        $gen = StudentGeneratedArtifact::with(['publication.artifact', 'lessonPublication.lesson'])->find($this->generatedId);
        if (!$gen) {
            return;
        }

        // Sorgente: il corpo della LEZIONE pubblicata (P20c) oppure l'artefatto
        // pubblicato (fetta 1). Esattamente uno dei due è valorizzato.
        if ($gen->lesson_publication_id) {
            $lesson = $gen->lessonPublication?->lesson;
            $source = trim((string) $lesson?->content);
            $label = $lesson?->title ?? 'Lezione della classe';
        } else {
            $artifact = $gen->publication?->artifact;
            $source = trim($this->artifactToText($artifact));
            $label = $artifact?->title ?? 'Materiale della classe';
        }

        if ($source === '') {
            $this->fail($gen, 'La fonte pubblicata non ha contenuto su cui generare.');
            return;
        }

        try {
            if ($gen->type === 'mindmap') {
                $r = $mindMap->generateFromText($source, $label, ['log_context' => ['student_generated' => $gen->id]]);
                $gen->update(['content' => $r['content'], 'status' => 'ready']);
            } elseif ($gen->type === 'quiz') {
                $num = (int) ($this->options['num_questions'] ?? 8);
                $res = $quiz->generateQuestions($source, $label, $num, [
                    'audience' => 'studenti di scuola superiore (quiz di autoverifica, registro scolastico)',
                ]);
                if ($res === null) {
                    throw new RuntimeException('Generazione quiz fallita: risposta API non valida.');
                }
                $newQuiz = $quiz->persistQuiz([
                    'module_id' => null,   // fuori dal mondo corsi
                    'course_id' => null,
                    'title' => 'Autoverifica — ' . $label,
                    'description' => 'Quiz di autoverifica generato dallo studente',
                ], $res['questions']);
                $gen->update(['quiz_id' => $newQuiz->id, 'status' => 'ready']);
            } else {
                throw new RuntimeException("Tipo non generabile: {$gen->type}");
            }
        } catch (Throwable $e) {
            Log::warning('[schola] auto-generazione studente fallita', [
                'generated_id' => $gen->id, 'type' => $gen->type, 'error' => $e->getMessage(),
            ]);
            $this->fail($gen, $e->getMessage());
        }
    }

    /** Rappresentazione testuale dell'artefatto pubblicato (sorgente della generazione). */
    private function artifactToText($artifact): string
    {
        if (!$artifact) {
            return '';
        }

        if ($artifact->type === 'conceptmap') {
            $graph = json_decode((string) $artifact->content, true);
            if (!is_array($graph)) {
                return '';
            }
            $labels = [];
            $lines = [];
            foreach ($graph['nodes'] ?? [] as $n) {
                $id = $n['id'] ?? null;
                $lab = trim((string) ($n['label'] ?? ''));
                if ($id !== null) $labels[$id] = $lab;
                $desc = trim((string) ($n['description'] ?? ''));
                if ($lab !== '') $lines[] = $desc !== '' ? "{$lab}: {$desc}" : $lab;
            }
            foreach ($graph['edges'] ?? [] as $e) {
                $from = $labels[$e['from'] ?? ''] ?? null;
                $to = $labels[$e['to'] ?? ''] ?? null;
                $rel = trim((string) ($e['label'] ?? ''));
                if ($from && $to) $lines[] = "{$from} {$rel} {$to}";
            }
            return implode('. ', $lines);
        }

        if ($artifact->type === 'quiz') {
            $q = $artifact->quiz()->with('questions')->first();
            if (!$q) return '';
            return $q->questions->map(fn ($x) => 'Domanda: ' . $x->question
                . ($x->correct_answer ? ' Risposta: ' . $x->correct_answer : ''))->implode("\n");
        }

        // transcript | summary | outline | mindmap → testo/markdown
        return (string) $artifact->content;
    }

    private function fail(StudentGeneratedArtifact $gen, string $reason): void
    {
        $gen->update(['status' => 'failed', 'failure_reason' => $reason]);
    }
}
