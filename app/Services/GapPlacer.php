<?php

namespace App\Services;

use App\Models\CourseSource;
use App\Models\GapDraft;
use App\Services\Freshness\AnthropicError;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * P26 Fase C — Propone DOVE inserire una bozza: nel formatore (dopo quale block_id heading dei
 * course_sources) e nello studente (in quale modulo, dopo quale ancora testuale verbatim). È una
 * PROPOSTA: l'admin conferma o sposta — la posizione non è mai automatica. Modello: Sonnet.
 */
class GapPlacer
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MAX_TOKENS = 800;

    private const SYSTEM_PROMPT = <<<SYS
    Sei un editor didattico. Devi proporre DOVE inserire una nuova sezione su un ARGOMENTO in un
    corso, coerentemente con la struttura. Scegli:
    - FORMATORE: il block_id (TRA quelli elencati) DOPO cui inserire la nuova sezione (tipicamente
      dopo l'heading dell'argomento più affine).
    - STUDENTE: il module_id (tra quelli elencati) e una FRASE VERBATIM, copiata ESATTA dal testo di
      quel modulo, dopo la quale inserire la versione studente.

    Rispondi ESCLUSIVAMENTE con JSON valido, senza preamboli né markdown. Formato esatto:
    {"formatore_after_block_id":"<id>","student_module_id":"<id>","student_anchor":"<frase verbatim>","reason":"<breve>"}

    Regole: usa SOLO id presenti negli elenchi; la "student_anchor" DEVE essere copiata
    letteralmente dal modulo scelto (serve a trovare il punto). Non inventare.
    SYS;

    /**
     * @return array{formatore_after_block_id:?string, student_module_id:?string, student_anchor:?string, reason:string}
     */
    public function propose(GapDraft $draft): array
    {
        $gap = $draft->gap;
        $course = $gap->course;

        $source = CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        $headings = $this->headings($source?->blocks ?? []);
        $modules = $this->modules($course);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::CLAUDE_API_URL, [
            'model' => config('services.anthropic.freshness_extract_model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [['role' => 'user', 'content' => $this->userMessage($gap->title, $headings, $modules)]],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(AnthropicError::message($response, 'proposta posizione'));
        }

        return $this->parse($response->json('content.0.text'));
    }

    private function headings(array $blocks): string
    {
        $lines = [];
        foreach ($blocks as $b) {
            if (in_array(($b['type'] ?? ''), ['PART', 'H1', 'H2'], true) && trim((string) ($b['text'] ?? '')) !== '') {
                $lines[] = "[{$b['id']}] {$b['text']}";
            }
        }

        return implode("\n", $lines) ?: '(nessun heading)';
    }

    private function modules($course): string
    {
        $lines = [];
        foreach ($course->modules()->get() as $m) {
            $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $m->content)));
            $lines[] = "[{$m->id}] {$m->title} — " . mb_substr($plain, 0, 200);
        }

        return implode("\n", $lines) ?: '(nessun modulo)';
    }

    private function userMessage(string $title, string $headings, string $modules): string
    {
        return <<<MSG
        ARGOMENTO DA INSERIRE: {$title}

        === STRUTTURA FORMATORE (block_id → heading) ===
        {$headings}

        === MODULI STUDENTE (module_id → titolo + estratto) ===
        {$modules}

        Proponi il block_id formatore dopo cui inserire e il modulo+frase-ancora verbatim per lo studente.
        MSG;
    }

    private function parse(?string $text): array
    {
        $clean = trim((string) preg_replace('/```(?:json)?/i', '', (string) $text));
        $clean = str_replace('```', '', $clean);
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false) {
            $clean = substr($clean, $start, $end - $start + 1);
        }
        $d = json_decode($clean, true);
        if (!is_array($d)) {
            throw new RuntimeException('Output proposta posizione non valido (JSON atteso).');
        }

        return [
            'formatore_after_block_id' => isset($d['formatore_after_block_id']) ? (string) $d['formatore_after_block_id'] : null,
            'student_module_id' => isset($d['student_module_id']) ? (string) $d['student_module_id'] : null,
            'student_anchor' => isset($d['student_anchor']) ? (string) $d['student_anchor'] : null,
            'reason' => trim((string) ($d['reason'] ?? '')),
        ];
    }
}
