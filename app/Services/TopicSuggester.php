<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\TrustedSource;
use App\Services\Freshness\AnthropicError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * P26.1 — Suggeritore di TOPIC per un corso: legge il contenuto e propone un dominio tematico,
 * che l'admin conferma o corregge (HITL). ANTI-DRIFT: prima di proporre un topic nuovo, passa
 * all'LLM i topic GIÀ ESISTENTI (fonti + altri corsi) e gli impone di RIUSARNE uno se affine,
 * così non si moltiplicano quasi-duplicati ("agenti-ai" vs "intelligenza-artificiale").
 * Solo SUGGERISCE: non scrive nulla. Modello: Sonnet.
 */
class TopicSuggester
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MAX_TOKENS = 500;

    private const SYSTEM_PROMPT = <<<SYS
    Sei un classificatore di domini didattici. Dato il contenuto di un corso e una lista di TOPIC
    ESISTENTI, proponi UN topic di dominio per il corso: breve (1–3 parole), come slug minuscolo
    con trattini (es. "agenti-ai", "compliance", "elettronica").

    ANTI-DRIFT (regola chiave): se uno dei TOPIC ESISTENTI è semanticamente adatto al corso,
    proponi ESATTAMENTE QUELLO (riuso) e metti is_existing=true. Proponi un topic NUOVO solo se
    NESSUNO degli esistenti è adatto (is_existing=false). Non creare varianti quasi-identiche di un topic
    esistente (es. se esiste "agenti-ai" non proporre "intelligenza-artificiale" per un corso di agenti).

    Rispondi ESCLUSIVAMENTE con JSON valido, senza preamboli né markdown. Formato esatto:
    {"suggested_topic":"<slug>","is_existing":<true|false>,"alternatives":["<slug>", "..."]}
    SYS;

    private const SYSTEM_PROMPT_MULTI = <<<SYS
    Sei un classificatore di domini didattici. Un corso reale copre PIÙ domini. Dato il contenuto
    del corso e una lista di TOPIC ESISTENTI, proponi la LISTA dei topic che il corso copre:
    - UNO con weight="primary" (il dominio DOMINANTE del corso);
    - gli altri con weight="secondary" (domini toccati, anche solo 1–3).

    ANTI-DRIFT (regola chiave): per OGNI topic, se uno dei TOPIC ESISTENTI è semanticamente adatto,
    riusa ESATTAMENTE QUELLO; proponine uno nuovo solo se nessun esistente è adatto. Non creare
    varianti quasi-identiche di un topic esistente. Ogni topic è uno slug minuscolo con trattini.

    Rispondi ESCLUSIVAMENTE con JSON valido, senza preamboli né markdown. Formato esatto:
    {"topics":[{"topic":"<slug>","weight":"primary|secondary"}, ...]}
    SYS;

    /**
     * @return array{suggested_topic:string, is_existing:bool, alternatives:list<string>, existing:list<string>}
     */
    public function suggest(Course $course): array
    {
        $existing = $this->existingTopics();
        $map = app(CourseMapExtractor::class)->fromCourse($course);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::CLAUDE_API_URL, [
            'model' => config('services.anthropic.freshness_extract_model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [['role' => 'user', 'content' => $this->userMessage($course, $map, $existing)]],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(AnthropicError::message($response, 'suggerimento topic'));
        }

        $parsed = $this->parse($response->json('content.0.text'));
        $topic = $this->slug($parsed['suggested_topic']);
        if ($topic === '') {
            throw new RuntimeException('Nessun topic proposto.');
        }

        // is_existing verificato lato server (non mi fido del flag dell'LLM): è esistente
        // SOLO se lo slug proposto è realmente fra i topic esistenti.
        $isExisting = in_array($topic, $existing, true);

        $alternatives = collect($parsed['alternatives'] ?? [])
            ->map(fn ($a) => $this->slug((string) $a))->filter()
            ->reject(fn ($a) => $a === $topic)->unique()->values()->all();

        return ['suggested_topic' => $topic, 'is_existing' => $isExisting, 'alternatives' => $alternatives, 'existing' => $existing];
    }

    /**
     * P26.2 — Propone la LISTA pesata dei topic che il corso copre (uno primary, gli altri
     * secondary). Anti-drift per ciascun topic; is_existing verificato lato server. Solo suggerisce.
     *
     * @return array{topics:list<array{topic:string, weight:string, is_existing:bool}>, existing:list<string>}
     */
    public function suggestTopics(Course $course): array
    {
        $existing = $this->existingTopics();
        $map = app(CourseMapExtractor::class)->fromCourse($course);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::CLAUDE_API_URL, [
            'model' => config('services.anthropic.freshness_extract_model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => self::SYSTEM_PROMPT_MULTI,
            'messages' => [['role' => 'user', 'content' => $this->userMessage($course, $map, $existing)]],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(AnthropicError::message($response, 'suggerimento topic'));
        }

        $raw = $this->parseList($response->json('content.0.text'));

        $out = [];
        $seen = [];
        foreach ($raw as $t) {
            $slug = $this->slug((string) ($t['topic'] ?? ''));
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $out[] = [
                'topic' => $slug,
                'weight' => (($t['weight'] ?? '') === 'primary') ? 'primary' : 'secondary',
                'is_existing' => in_array($slug, $existing, true), // verificato lato server
            ];
        }
        if ($out === []) {
            throw new RuntimeException('Nessun topic proposto.');
        }

        return ['topics' => $this->ensureSinglePrimary($out), 'existing' => $existing];
    }

    /** Esattamente UN primary: il primo che lo è (o il primo della lista); gli altri → secondary. */
    private function ensureSinglePrimary(array $topics): array
    {
        $primaryIdx = null;
        foreach ($topics as $i => $t) {
            if ($t['weight'] === 'primary') {
                if ($primaryIdx === null) {
                    $primaryIdx = $i;
                } else {
                    $topics[$i]['weight'] = 'secondary';
                }
            }
        }
        if ($primaryIdx === null) {
            $topics[0]['weight'] = 'primary';
        }

        return $topics;
    }

    /** @return list<array<string,mixed>> */
    private function parseList(?string $text): array
    {
        $clean = trim((string) preg_replace('/```(?:json)?/i', '', (string) $text));
        $clean = str_replace('```', '', $clean);
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false) {
            $clean = substr($clean, $start, $end - $start + 1);
        }
        $d = json_decode($clean, true);
        if (!is_array($d['topics'] ?? null)) {
            throw new RuntimeException('Output suggerimento topic non valido (JSON atteso con "topics").');
        }

        return $d['topics'];
    }

    /** Topic distinti già in uso: fonti + topic assegnati ad altri corsi. @return list<string> */
    public function existingTopics(): array
    {
        return TrustedSource::query()->distinct()->pluck('topic')
            ->merge(\App\Models\CourseTopic::query()->distinct()->pluck('topic'))            // P26.2: pivot (verità)
            ->merge(CourseFreshnessConfig::whereNotNull('topic')->distinct()->pluck('topic')) // legacy
            ->map(fn ($t) => $this->slug((string) $t))
            ->filter()->unique()->sort()->values()->all();
    }

    private function userMessage(Course $course, array $map, array $existing): string
    {
        $existingBlock = $existing === [] ? '(nessuno ancora)' : "- " . implode("\n- ", $existing);
        $desc = trim((string) ($course->description ?? $course->short_description ?? ''));
        $outline = $map['outline'] !== '' ? $map['outline'] : '(nessun heading)';

        return <<<MSG
        CORSO: {$course->name}
        DESCRIZIONE: {$desc}

        CONTENUTO (mappa del corso):
        {$outline}

        {$map['excerpt']}

        TOPIC ESISTENTI (riusane uno se semanticamente adatto):
        {$existingBlock}

        Proponi il topic di dominio per questo corso.
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
        if (!is_array($d) || !isset($d['suggested_topic'])) {
            throw new RuntimeException('Output suggerimento topic non valido (JSON atteso).');
        }

        return $d;
    }

    private function slug(string $t): string
    {
        return Str::slug(trim($t));
    }
}
