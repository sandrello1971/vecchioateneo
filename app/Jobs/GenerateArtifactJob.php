<?php

namespace App\Jobs;

use App\Models\Quiz;
use App\Models\TeachingArtifact;
use App\Services\ConceptMapGenerationService;
use App\Services\MindMapGenerationService;
use App\Jobs\IngestArtifactTeacherPrivateJob;
use App\Services\QuizGeneratorService;
use App\Services\SummaryGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

// Generazione asincrona di un teaching_artifact a partire dal testo estratto del
// suo teaching_document. Un servizio per tipo (summary/outline → Summary;
// mindmap → MindMap; conceptmap → ConceptMap; quiz → Quiz). Imposta status
// ready/failed e valorizza SEMPRE generation_meta (modello, token, prompt version).
// I quiz Schola nascono con module_id e course_id NULL: vivono fuori dal mondo corsi.
class GenerateArtifactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1; // il retry è esplicito (rigenerazione dall'UI)

    /**
     * @param  array  $options  opzioni di generazione (es. ['level'=>'medio'] per summary,
     *                          ['num_questions'=>10] per quiz)
     */
    public function __construct(public string $artifactId, public array $options = []) {}

    public function handle(
        MindMapGenerationService $mindMap,
        ConceptMapGenerationService $conceptMap,
        QuizGeneratorService $quizGenerator,
        SummaryGenerationService $summary,
    ): void {
        $artifact = TeachingArtifact::find($this->artifactId);
        if (!$artifact) {
            return; // artefatto eliminato nel frattempo
        }

        // Sorgente: il materiale grezzo (artefatto di materiale) oppure il corpo
        // della lezione composta (artefatto di lezione, lesson_id valorizzato).
        // Stessi servizi, stesso flusso: niente logica duplicata.
        $doc = $artifact->teachingDocument;
        if ($doc) {
            $source = trim((string) $doc->extracted_text);
            $label = $doc->title ?: $artifact->title;
        } else {
            $lesson = $artifact->lesson;
            $source = trim((string) ($lesson?->content ?? ''));
            $label = $lesson?->title ?: $artifact->title;
        }
        $log = ['artifact_id' => $artifact->id, 'type' => $artifact->type];

        if ($source === '') {
            $this->markFailed($artifact, $doc || $artifact->lesson_id
                ? 'La fonte non ha testo su cui lavorare.'
                : 'Artefatto senza materiale o lezione di origine.');
            return;
        }

        try {
            $quizId = $artifact->quiz_id;

            switch ($artifact->type) {
                case 'summary':
                    $r = $summary->generateFromText($source, $label, [
                        'level' => $this->options['level'] ?? 'medio',
                        'log_context' => $log,
                    ]);
                    $content = $r['content'];
                    $meta = $r['meta'];
                    break;

                case 'outline':
                    $r = $summary->generateOutline($source, $label, ['log_context' => $log]);
                    $content = $r['content'];
                    $meta = $r['meta'];
                    break;

                case 'mindmap':
                    $r = $mindMap->generateFromText($source, $label, ['log_context' => $log]);
                    $content = $r['content'];
                    $meta = $r['meta'];
                    break;

                case 'conceptmap':
                    $r = $conceptMap->generateFromText($source, $label, [
                        'focused' => true, // un singolo documento: focus stretto
                        'log_context' => $log,
                    ]);
                    $content = json_encode($r['content'], JSON_UNESCAPED_UNICODE);
                    $meta = $r['meta'];
                    break;

                case 'quiz':
                    $num = (int) ($this->options['num_questions'] ?? 10);
                    $res = $quizGenerator->generateQuestions($source, $label, $num);
                    if ($res === null) {
                        throw new RuntimeException('Generazione quiz fallita: risposta API non valida.');
                    }

                    // Rigenerazione: riusa lo stesso Quiz (quiz_id stabile, niente orfani).
                    $existing = $artifact->quiz_id ? Quiz::find($artifact->quiz_id) : null;
                    if ($existing) {
                        $existing->update(['title' => $artifact->title]);
                        $quizGenerator->syncQuestions($existing, $res['questions']);
                        $quizId = $existing->id;
                    } else {
                        $quiz = $quizGenerator->persistQuiz([
                            'module_id' => null,   // Schola: fuori dal mondo corsi
                            'course_id' => null,
                            'title' => $artifact->title,
                            'description' => 'Quiz Schola generato da Claude',
                        ], $res['questions']);
                        $quizId = $quiz->id;
                    }

                    $content = null; // per i quiz il contenuto vive su quizzes/quiz_questions
                    $meta = $res['meta'];
                    break;

                default:
                    throw new RuntimeException("Tipo artefatto non generabile: {$artifact->type}");
            }

            $artifact->update([
                'content' => $content,
                'quiz_id' => $quizId,
                'status' => 'ready',
                'generation_meta' => $meta,
            ]);

            // Minerva del docente: indicizza l'artefatto come teacher_private
            // (anche se non pubblicato). Best-effort, non blocca la generazione.
            IngestArtifactTeacherPrivateJob::dispatch($artifact->id);
        } catch (Throwable $e) {
            Log::warning('[schola] generazione artefatto fallita', array_merge($log, [
                'error' => $e->getMessage(),
            ]));
            $this->markFailed($artifact, $e->getMessage());
        }
    }

    private function markFailed(TeachingArtifact $artifact, string $reason): void
    {
        $artifact->update([
            'status' => 'failed',
            'generation_meta' => array_merge((array) $artifact->generation_meta, [
                'failure_reason' => $reason,
            ]),
        ]);
    }
}
