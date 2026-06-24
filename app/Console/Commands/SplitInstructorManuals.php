<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use App\Services\InstructorManualSplitterService;
use Illuminate\Console\Command;

class SplitInstructorManuals extends Command
{
    protected $signature = 'atheneum:split-instructor-manuals
        {--course= : Limita a un corso specifico (slug)}';

    protected $description = 'Spezza i manuali formatore esistenti in sezioni con anchor';

    public function handle(InstructorManualSplitterService $splitter): int
    {
        $query = Material::where('is_instructor_only', true)
            ->whereNotNull('content_html');

        if ($slug = $this->option('course')) {
            $course = Course::where('slug', $slug)->first();
            if (!$course) {
                $this->error("Corso $slug non trovato");
                return self::FAILURE;
            }
            $query->where('course_id', $course->id);
        }

        $manuals = $query->with('course')->get();
        $this->info("Trovati {$manuals->count()} manuali formatore.");

        foreach ($manuals as $m) {
            $this->line("Splitting: {$m->title} ({$m->course->name})...");
            try {
                $count = $splitter->split($m);
                $this->info("  ✅ $count sezioni create");
            } catch (\Throwable $e) {
                $this->error("  ❌ " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
