<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use App\Services\InstructorManualService;
use Illuminate\Console\Command;

class ImportInstructorManual extends Command
{
    protected $signature = 'atheneum:import-instructor-manual
        {docx : Absolute path to the .docx file}
        {--course= : Course slug (primus, initium, structura, ai-agents-mcp, etc.)}
        {--title= : Title shown in the UI}';

    protected $description = 'Import a formatore .docx as instructor-only material for a course';

    public function handle(InstructorManualService $service): int
    {
        $docxPath = $this->argument('docx');
        $courseSlug = $this->option('course');
        $title = $this->option('title') ?? 'Manuale Formatore';

        if (!file_exists($docxPath)) {
            $this->error("File non trovato: $docxPath");
            return self::FAILURE;
        }

        $course = Course::where('slug', $courseSlug)->first();
        if (!$course) {
            $this->error("Corso non trovato con slug: $courseSlug");
            return self::FAILURE;
        }

        $existing = Material::where('course_id', $course->id)
            ->where('is_instructor_only', true)
            ->where('title', $title)
            ->first();

        try {
            $material = $service->import($docxPath, $course, $title, null, $existing);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $action = $existing ? 'aggiornato' : 'importato';
        $this->info("✅ Materiale formatore {$action}:");
        $this->line("   Course: {$course->name}");
        $this->line("   Title:  {$title}");
        $this->line("   Path:   storage/app/{$material->file_path}");
        $this->line("   HTML:   " . strlen($material->content_html) . " caratteri");
        $this->line("   ID:     {$material->id}");

        return self::SUCCESS;
    }
}
