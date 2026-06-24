<?php

namespace App\Jobs;

use App\Services\CourseDocumentParser;
use App\Services\CourseIngestionService;
use App\Support\CourseIngestProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ParseCourseDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(
        public string $jobId,
        public string $manualStoragePath,
        public ?string $examStoragePath,
        public array $settings,
    ) {}

    public function handle(CourseDocumentParser $parser, CourseIngestionService $llm): void
    {
        $stage = 0;

        try {
            $manualAbsPath = Storage::disk('local')->path($this->manualStoragePath);
            $examAbsPath = $this->examStoragePath
                ? Storage::disk('local')->path($this->examStoragePath)
                : null;

            // Stage 1: pandoc conversion (docx o markdown, via dispatcher per estensione)
            $stage = 1;
            CourseIngestProgress::setStage($this->jobId, 1, 'Conversione manuale discente...');
            $manualHtml = $parser->convertManualToHtml($manualAbsPath);

            // Stage 2: normalize headings + split into modules + separate exam prep.
            // Markdown: heading espliciti → niente promozione bold/numerati (un
            // <p><strong> NON è un heading). Docx: promozione necessaria (Word).
            $stage = 2;
            CourseIngestProgress::setStage($this->jobId, 2, 'Identificazione struttura moduli...');
            $ext = strtolower(pathinfo($manualAbsPath, PATHINFO_EXTENSION));
            $normalizedHtml = in_array($ext, ['md', 'markdown'], true)
                ? $parser->normalizeMarkdownHtml($manualHtml)
                : $parser->normalizeHeadings($manualHtml);
            $modules = $parser->splitIntoModules($normalizedHtml);

            $separated = $parser->separateExamPrep($modules);
            $modules = $separated['modules'];
            $examPrepHtml = $separated['exam_prep_html'];

            // Stage 3: course metadata via LLM (short call, ~500 tokens output)
            $stage = 3;
            CourseIngestProgress::setStage($this->jobId, 3, 'Estrazione metadati corso...');
            $frontmatter = $parser->extractFrontmatter($normalizedHtml);
            $metadata = $parser->extractCourseMetadata($frontmatter);

            // Stage 4 + 5: exam (optional)
            $exam = null;
            if ($examAbsPath) {
                $stage = 4;
                CourseIngestProgress::setStage($this->jobId, 4, 'Conversione esame...');
                $examText = $parser->extractTextForExam($examAbsPath);

                if (!empty(trim($examText))) {
                    $stage = 5;
                    CourseIngestProgress::setStage($this->jobId, 5, 'Estrazione domande esame...');
                    $exam = $llm->parseExamToQuestions($examText);
                }
            } else {
                CourseIngestProgress::setStage($this->jobId, 5, 'Esame non fornito, salto.');
            }

            CourseIngestProgress::setResult($this->jobId, [
                'course' => $metadata,
                'modules' => $modules,
                'exam_prep_html' => $examPrepHtml,
                'exam' => $exam,
                'settings' => $this->settings,
            ]);
        } catch (\Throwable $e) {
            Log::error('ParseCourseDocumentsJob failed', [
                'jobId' => $this->jobId,
                'stage' => $stage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            CourseIngestProgress::setError($this->jobId, $e->getMessage(), $stage);
        }
    }
}
