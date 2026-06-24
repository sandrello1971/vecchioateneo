<?php

namespace App\Services;

use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstructorManualSplitterService
{
    public function split(Material $material): int
    {
        if (!$material->is_instructor_only) {
            throw new \InvalidArgumentException('Solo manuali formatore possono essere splittati');
        }
        if (empty($material->content_html)) {
            return 0;
        }

        $modules = Module::where('course_id', $material->course_id)
            ->orderBy('sort_order')->get();

        $manualOverrides = InstructorManualSection::where('material_id', $material->id)
            ->where('module_assigned_manually', true)
            ->get()
            ->keyBy('anchor');

        InstructorManualSection::where('material_id', $material->id)->delete();

        $normalizedHtml = $this->normalizeHtml($material->content_html);
        $level = $this->chooseSplitLevel($normalizedHtml);
        $sections = $this->extractSections($normalizedHtml, $level);

        Log::info('Split manuale formatore', [
            'material_id' => $material->id,
            'title' => $material->title,
            'level' => $level,
            'sections' => count($sections),
        ]);

        $sortOrder = 0;
        foreach ($sections as $sec) {
            $anchor = $this->generateAnchor($sec['title'], $sortOrder);

            $moduleId = $this->autoMapToModule($sec['title'], $modules);
            $manuallyAssigned = false;

            if (isset($manualOverrides[$anchor])) {
                $moduleId = $manualOverrides[$anchor]->module_id;
                $manuallyAssigned = true;
            }

            InstructorManualSection::create([
                'material_id'              => $material->id,
                'course_id'                => $material->course_id,
                'module_id'                => $moduleId,
                'title'                    => $sec['title'],
                'anchor'                   => $anchor,
                'heading_level'            => $sec['level'],
                'sort_order'               => $sortOrder,
                'content_html'             => $sec['content'],
                'module_assigned_manually' => $manuallyAssigned,
            ]);

            $sortOrder++;
        }

        $material->update(['sections_extracted_at' => now()]);

        return $sortOrder;
    }

    public function injectAnchorsIntoMainHtml(Material $material): string
    {
        if (empty($material->content_html)) return '';

        $sections = InstructorManualSection::where('material_id', $material->id)
            ->orderBy('sort_order')->get();

        $html = $this->normalizeHtml($material->content_html);
        $level = $this->chooseSplitLevel($html);
        $tag = $level === 1 ? 'h1' : 'h2';

        $usedTitles = [];
        foreach ($sections as $sec) {
            if ($sec->heading_level !== $level) continue;

            $titleEscaped = preg_quote(strip_tags($sec->title), '/');
            $cnt = $usedTitles[$sec->title] ?? 0;
            $usedTitles[$sec->title] = $cnt + 1;

            $pattern = "/<{$tag}([^>]*)>(\s*<[^>]+>)*\s*" . $titleEscaped . '/i';
            $matchCount = 0;
            $html = preg_replace_callback($pattern, function ($m) use ($sec, &$matchCount, $cnt, $tag) {
                $current = $matchCount++;
                if ($current !== $cnt) return $m[0];
                $attrs = $m[1];
                if (str_contains($attrs, 'id=')) return $m[0];
                $attrs .= ' id="' . $sec->anchor . '"';
                return "<{$tag}" . $attrs . '>' . ($m[2] ?? '') . ' ' . strip_tags($sec->title);
            }, $html, 1);
        }

        return $html;
    }

    /**
     * Promuove <p><strong>…</strong></p> strutturali a <h1> per manuali
     * che pandoc non ha convertito con heading semantici.
     * Idempotente: manuali già con veri H1 non vengono toccati.
     */
    private function normalizeHtml(string $html): string
    {
        return preg_replace_callback(
            '/<p[^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/p>/is',
            function ($m) {
                $text = trim(strip_tags($m[1]));
                if (preg_match('/^(PARTE|Capitolo|Modulo|Blocco|Sezione|Lezione)\s+[\w\sIVXivx]+/u', $text)
                    || preg_match('/^[A-ZÀ-Ý\s\—\-0-9]{15,}$/u', $text)
                ) {
                    return '<h1>' . $m[1] . '</h1>';
                }
                return $m[0];
            },
            $html
        );
    }

    /**
     * Decide se splittare per H1 o H2 in base alla struttura del documento.
     * - No H1 ma ci sono H2 → H2
     * - Pochi H1 (<4) e molti H2 (>=4) → H1 sono macro-titoli, usa H2
     * - ≥30% degli H1 è "PARTE PRIMA/…" → H1 sono macro-parti, usa H2
     * - Altrimenti H1
     */
    private function chooseSplitLevel(string $html): int
    {
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1Matches);
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $h2Matches);

        $h1Count = count($h1Matches[1]);
        $h2Count = count($h2Matches[1]);

        if ($h1Count === 0 && $h2Count > 0) return 2;

        if ($h1Count < 4 && $h2Count >= 4) return 2;

        if ($h1Count > 0) {
            $partTitleCount = 0;
            foreach ($h1Matches[1] as $title) {
                $clean = trim(strip_tags($title));
                if (preg_match('/^(?:PARTE|SEZIONE)\s+(?:PRIMA|SECONDA|TERZA|QUARTA|QUINTA|[IVX]+|\d+)/iu', $clean)) {
                    $partTitleCount++;
                }
            }
            if ($partTitleCount / $h1Count >= 0.3) return 2;
        }

        return 1;
    }

    private function extractSections(string $html, int $level = 1): array
    {
        $tag = $level === 1 ? 'h1' : 'h2';
        $parts = preg_split(
            "/(<{$tag}[^>]*>.*?<\\/{$tag}>)/is",
            $html, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        $sections = [];
        $current = null;

        foreach ($parts as $part) {
            if (preg_match("/^<{$tag}[^>]*>(.*?)<\\/{$tag}>$/is", $part, $m)) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $title = trim(strip_tags($m[1]));
                if (empty($title)) continue;
                $current = [
                    'title' => $title,
                    'level' => $level,
                    'content' => $part,
                ];
            } else {
                if ($current !== null) {
                    $current['content'] .= $part;
                }
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        return $sections;
    }

    private function generateAnchor(string $title, int $sortOrder): string
    {
        $slug = Str::slug($title);
        if (mb_strlen($slug) > 60) {
            $slug = mb_substr($slug, 0, 60);
        }
        return sprintf('sez-%03d-%s', $sortOrder, $slug);
    }

    private function autoMapToModule(string $sectionTitle, $modules): ?string
    {
        if ($modules->isEmpty()) return null;

        // PRIORITÀ 1: "Modulo N" o "M N"
        if (preg_match('/(?:^|[^a-z])(?:Modulo|M)\s*(?:nr\.?\s*)?(\d+)/i', $sectionTitle, $m)) {
            $module = $modules->firstWhere('sort_order', (int)$m[1]);
            if ($module) return $module->id;
        }

        // PRIORITÀ 2: "Lezione N"
        if (preg_match('/(?:^|[^a-z])Lezione\s*(\d+)/i', $sectionTitle, $m)) {
            $module = $modules->firstWhere('sort_order', (int)$m[1]);
            if ($module) return $module->id;
        }

        // PRIORITÀ 3: "Blocco X" (lettera) → substring match
        if (preg_match('/(?:^|[^a-z])Blocco\s+([A-Z])\b/i', $sectionTitle, $m)) {
            $letter = strtoupper($m[1]);
            $module = $modules->first(fn($mod) => stripos($mod->title, "Blocco $letter") !== false);
            if ($module) return $module->id;
        }

        return null;
    }
}
