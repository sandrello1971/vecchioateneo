<?php

namespace App\Console\Commands;

use App\Models\DocumentRag;
use App\Models\Material;
use App\Services\RagService;
use Illuminate\Console\Command;

class IngestInstructorManuals extends Command
{
    protected $signature = 'atheneum:ingest-instructor-manuals';

    protected $description = 'Indicizza i manuali formatore in documents_rag come chunks instructor-only';

    public function handle(RagService $rag): int
    {
        $materials = Material::where('is_instructor_only', true)
            ->whereNotNull('content_html')
            ->with('course')
            ->get();

        if ($materials->isEmpty()) {
            $this->warn('Nessun manuale formatore da indicizzare.');
            return self::SUCCESS;
        }

        $this->info("Trovati {$materials->count()} manuali formatore.");

        foreach ($materials as $m) {
            if (!$m->course) {
                $this->warn("  ⚠ Manuale «{$m->title}» senza corso associato, skippo.");
                continue;
            }
            $this->line("Elaboro: {$m->title} ({$m->course->name})...");

            DocumentRag::where('course_id', $m->course_id)
                ->where('is_instructor_only', true)
                ->where('title', $m->title)
                ->delete();

            $plainText = html_entity_decode(
                strip_tags($m->content_html ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $plainText = trim($plainText);

            if (mb_strlen($plainText) < 200) {
                $this->warn("  ⚠ Testo troppo corto, skippo.");
                continue;
            }

            $rag->indexDocument(
                $plainText,
                $m->title,
                $m->course_id,
                null,
                $m->file_path,
                true
            );

            $newCount = DocumentRag::where('course_id', $m->course_id)
                ->where('is_instructor_only', true)
                ->where('title', $m->title)
                ->count();

            $this->info("  ✅ {$newCount} chunks creati.");
        }

        $this->info("Completato.");
        return self::SUCCESS;
    }
}
