<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSource;
use App\Models\Module;

/**
 * P26 Fase B — Carica il "taglio" del corso (registro, profondità, frasi-bandiera) come ESEMPI
 * da imitare, NON come prosa generica. Per un dato gap seleziona gli esempi più rilevanti per
 * tema (keyword-overlap col titolo del gap) con un tetto in caratteri per non esplodere i token.
 *
 * Strategia (semplice e robusta, niente embeddings):
 *  - FORMATORE: dai blocchi course_sources, punteggia ogni blocco di testo per overlap col titolo
 *    del gap; bonus a BOX/EX (portano il registro: note tattiche, esempi); prendi i top fino a un
 *    tetto (~3500 char). Se nessun match, ripiega sui primi blocchi sostanziali (registro comunque).
 *  - STUDENTE: scegli il modulo più affine al gap (overlap su titolo+contenuto) e prendine un
 *    estratto (~2000 char) per il registro espositivo. Fallback: il primo modulo.
 */
class CourseStyleSampler
{
    private const FORMATORE_CHAR_CAP = 3500;
    private const STUDENT_CHAR_CAP = 2000;
    private const STOPWORDS = ['il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'di', 'a', 'da', 'in',
        'con', 'su', 'per', 'tra', 'fra', 'e', 'o', 'che', 'del', 'della', 'dei', 'delle', 'al', 'ai', 'nel',
        'come', 'agli', 'the', 'of', 'and', 'to', 'for'];

    /**
     * @return array{formatore_examples:string, student_excerpt:string, student_module_id:?string}
     */
    public function sample(Course $course, string $gapTitle): array
    {
        $tokens = $this->tokens($gapTitle);

        $source = CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();

        $student = $this->studentExcerpt($course, $tokens);

        return [
            'formatore_examples' => $this->formatoreExamples($source?->blocks ?? [], $tokens),
            'student_excerpt' => $student['student_excerpt'],
            'student_module_id' => $student['student_module_id'],
        ];
    }

    private function formatoreExamples(array $blocks, array $tokens): string
    {
        $scored = [];
        foreach ($blocks as $i => $b) {
            $type = (string) ($b['type'] ?? '');
            $text = trim((string) ($b['text'] ?? ''));
            if ($text === '' && isset($b['items']) && is_array($b['items'])) {
                $text = trim(implode(' · ', array_map('strval', $b['items'])));
            }
            if ($text === '' || in_array($type, ['PART', 'H1', 'H2'], true)) {
                continue; // gli heading non sono esempi di registro
            }
            $score = $this->overlap($text, $tokens) + (in_array($type, ['BOX', 'EX', 'ESE'], true) ? 1 : 0);
            $scored[] = ['i' => $i, 'type' => $type, 'text' => $text, 'score' => $score];
        }

        // Top per score; a parità, ordine del documento. Se tutto 0 → primi blocchi (registro).
        $anyMatch = collect($scored)->contains(fn ($b) => $b['score'] > 0);
        usort($scored, fn ($a, $b) => $anyMatch ? ($b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i']) : ($a['i'] <=> $b['i']));

        $out = [];
        $len = 0;
        foreach ($scored as $b) {
            $line = "[{$b['type']}] " . mb_substr($b['text'], 0, 600);
            if ($len + mb_strlen($line) > self::FORMATORE_CHAR_CAP) {
                break;
            }
            $out[] = $line;
            $len += mb_strlen($line);
        }

        return implode("\n\n", $out);
    }

    private function studentExcerpt(Course $course, array $tokens): array
    {
        $modules = $course->modules()->get();
        if ($modules->isEmpty()) {
            return ['student_excerpt' => '', 'student_module_id' => null];
        }

        $best = null;
        $bestScore = -1;
        foreach ($modules as $m) {
            $plain = $this->plain((string) $m->content);
            $score = $this->overlap($m->title . ' ' . $plain, $tokens);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $m;
            }
        }
        $best = $best ?: $modules->first();

        return [
            'student_excerpt' => mb_substr($this->plain((string) $best->content), 0, self::STUDENT_CHAR_CAP),
            'student_module_id' => $best->id,
        ];
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true))));
    }

    /** @param list<string> $tokens */
    private function overlap(string $text, array $tokens): int
    {
        if ($tokens === []) {
            return 0;
        }
        $lower = mb_strtolower($text);
        $hits = 0;
        foreach ($tokens as $t) {
            if (mb_strpos($lower, $t) !== false) {
                $hits++;
            }
        }
        return $hits;
    }

    private function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
