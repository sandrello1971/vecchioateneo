<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSource;

/**
 * P26 Fase A — "Cosa copre il corso": estrae la MAPPA dalla versione corrente di course_sources.
 * La mappa = outline degli heading (PART/H1/H2) + un ESTRATTO breve del testo dei blocchi
 * (cappato), per ridurre i falsi gap "c'è ma non come heading". Solo lettura.
 *
 * Scelta token: includo l'outline completo (poche righe) e un estratto troncato (300 char/blocco,
 * tetto globale ~4000 char): abbastanza per disambiguare la copertura senza far esplodere il costo.
 */
class CourseMapExtractor
{
    private const HEADING_TYPES = ['PART', 'H1', 'H2'];
    private const MAX_SNIPPET_CHARS = 300;
    private const MAX_EXCERPT_CHARS = 4000;

    /** Mappa dalla versione corrente del sorgente del corso (ultima per created_at/id). */
    public function fromCourse(Course $course): array
    {
        $source = CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();

        return $this->fromBlocks($source?->blocks ?? []);
    }

    /**
     * @param  array<int,array<string,mixed>>  $blocks
     * @return array{headings: list<array{level:string,text:string}>, outline:string, excerpt:string, empty:bool}
     */
    public function fromBlocks(array $blocks): array
    {
        $headings = [];
        $outline = [];
        $excerpt = [];
        $excerptLen = 0;

        foreach ($blocks as $b) {
            $type = (string) ($b['type'] ?? '');
            $text = trim((string) ($b['text'] ?? ''));
            if ($text === '' && isset($b['items']) && is_array($b['items'])) {
                $text = trim(implode(' · ', array_map('strval', $b['items'])));
            }
            if ($text === '') {
                continue;
            }

            if (in_array($type, self::HEADING_TYPES, true)) {
                $headings[] = ['level' => $type, 'text' => $text];
                $indent = $type === 'PART' ? '' : ($type === 'H1' ? '  ' : '    ');
                $outline[] = $indent . $text;
            } elseif ($excerptLen < self::MAX_EXCERPT_CHARS) {
                $snippet = mb_substr($text, 0, self::MAX_SNIPPET_CHARS);
                $excerpt[] = $snippet;
                $excerptLen += mb_strlen($snippet);
            }
        }

        return [
            'headings' => $headings,
            'outline' => implode("\n", $outline),
            'excerpt' => mb_substr(implode("\n", $excerpt), 0, self::MAX_EXCERPT_CHARS),
            'empty' => $headings === [],
        ];
    }
}
