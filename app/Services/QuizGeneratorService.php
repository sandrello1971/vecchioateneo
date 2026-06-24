<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuizGeneratorService
{
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';
    private const PROMPT_VERSION = 'quiz-2026-06';

    /** Domande per chiamata Claude nel pool: piccolo → niente troncamento JSON. */
    private const POOL_BATCH_SIZE = 10;
    /** Tetto ai round di batch (anti-loop se il modello non raggiunge il target). */
    private const POOL_MAX_ROUNDS = 12;
    /** Contenuto usato per i pool grandi (più materiale = più varietà). */
    private const POOL_CONTENT_CHARS = 16000;

    /**
     * Percorso storico (mondo corsi): genera e persiste un quiz legato a un corso.
     * Comportamento invariato — delega la generazione delle domande al core
     * generateQuestions e la persistenza a persistQuiz.
     */
    public function generateFromContent(
        Course $course,
        string $content,
        int $numQuestions = 10,
        ?int $questionsPerAttempt = null
    ): ?Quiz {
        $brand = atheneum_setting('instance_name', 'aziende e PMI');
        $opts = [
            'audience' => "formazione aziendale per {$brand}",
            'subject_noun' => 'corso',
        ];

        // Pool grande → generazione a batch (evita il troncamento del single-call);
        // pool piccolo → singola chiamata storica.
        $result = $numQuestions > self::POOL_BATCH_SIZE
            ? $this->generatePool($content, $course->name, $numQuestions, $opts)
            : $this->generateQuestions($content, $course->name, $numQuestions, $opts);

        if ($result === null) {
            return null;
        }

        // questions_per_attempt valido solo se < pool effettivo; altrimenti NULL (tutte).
        $pool = count($result['questions']);
        $perAttempt = ($questionsPerAttempt !== null && $questionsPerAttempt > 0 && $questionsPerAttempt < $pool)
            ? $questionsPerAttempt
            : null;

        return $this->persistQuiz([
            'course_id' => $course->id,
            'title' => 'Quiz AI — ' . $course->name,
            'description' => 'Quiz generato automaticamente da Claude AI',
            'passing_score' => 70,
            'is_active' => true,
            'randomize_questions' => true,
            'questions_per_attempt' => $perAttempt,
            'show_results_immediately' => true,
        ], $result['questions']);
    }

    /**
     * Genera un POOL ampio di domande iterando in batch da POOL_BATCH_SIZE finché
     * raggiunge $target (o esaurisce i round). Ogni batch è una chiamata Claude
     * separata (no troncamento JSON). Dedup su testo normalizzato + anti-ripetizione
     * via prompt (avoid-list). NON persiste nulla.
     *
     * @return array{questions: array, meta: array}|null
     */
    public function generatePool(string $content, string $contextLabel, int $target, array $options = []): ?array
    {
        $collected = [];      // questions accumulate
        $seen = [];           // testo normalizzato → true (dedup)
        $tokensIn = 0;
        $tokensOut = 0;
        $rounds = 0;

        while (count($collected) < $target && $rounds < self::POOL_MAX_ROUNDS) {
            $rounds++;
            $need = $target - count($collected);
            $batch = min(self::POOL_BATCH_SIZE, $need + 2); // +2 margine per gli scarti del dedup

            $res = $this->generateQuestions($content, $contextLabel, $batch, array_merge($options, [
                'content_chars' => self::POOL_CONTENT_CHARS,
                'avoid' => array_map(fn ($q) => $q['question'] ?? '', $collected),
            ]));

            if ($res === null) {
                // Batch fallito: se abbiamo già qualcosa, ci fermiamo con ciò che c'è.
                Log::warning('QuizGeneratorService: batch pool fallito', ['round' => $rounds, 'collected' => count($collected)]);
                break;
            }

            $tokensIn += $res['meta']['tokens_in'] ?? 0;
            $tokensOut += $res['meta']['tokens_out'] ?? 0;

            $added = 0;
            foreach ($res['questions'] as $q) {
                $key = $this->normalizeQuestion($q['question'] ?? '');
                if ($key === '' || isset($seen[$key])) {
                    continue; // duplicato/quasi-identico → scarta
                }
                $seen[$key] = true;
                $collected[] = $q;
                $added++;
                if (count($collected) >= $target) {
                    break;
                }
            }

            // Round improduttivo (0 nuove dopo il dedup) → evita loop infinito.
            if ($added === 0) {
                Log::info('QuizGeneratorService: round pool senza nuove domande, stop', ['round' => $rounds]);
                break;
            }
        }

        if (empty($collected)) {
            return null;
        }

        return [
            'questions' => array_slice($collected, 0, $target),
            'meta' => [
                'model' => self::CLAUDE_MODEL,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'prompt_version' => self::PROMPT_VERSION,
                'questions_count' => min(count($collected), $target),
                'rounds' => $rounds,
            ],
        ];
    }

    /** Chiave di dedup: testo domanda normalizzato (minuscole, spazi/punteggiatura collassati). */
    private function normalizeQuestion(string $text): string
    {
        $t = mb_strtolower(strip_tags($text));
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', '', $t); // via punteggiatura
        $t = trim(preg_replace('/\s+/u', ' ', $t));

        return $t;
    }

    /**
     * Core parametrizzato: interroga Claude e restituisce le domande a risposta
     * multipla (NON persiste nulla). Riusabile dal mondo corsi e da Schola.
     *
     * @param  string  $content       testo sorgente su cui basare le domande
     * @param  string  $contextLabel  titolo/etichetta del contesto (corso, documento, ecc.)
     * @param  array   $options       ['audience' => string, 'subject_noun' => string]
     * @return array{questions: array, meta: array}|null  null in caso di errore API/parse
     */
    public function generateQuestions(string $content, string $contextLabel, int $numQuestions = 10, array $options = []): ?array
    {
        $audience = $options['audience']
            ?? 'studenti di scuola superiore (linguaggio chiaro, registro scolastico)';
        $subjectNoun = $options['subject_noun'] ?? 'materiale';

        $systemPrompt = <<<SYSTEM
Sei un esperto di {$audience}.
Devi generare domande a risposta multipla per verificare la comprensione del materiale.

Regole:
- Ogni domanda deve avere esattamente 4 opzioni (a, b, c, d)
- Una sola risposta corretta per domanda
- Le domande devono testare comprensione reale, non solo memoria
- Le opzioni sbagliate devono essere plausibili
- Includi una spiegazione breve per la risposta corretta
- Rispondi SOLO con JSON valido, nessun testo extra

Formato JSON richiesto:
{
  "questions": [
    {
      "question": "testo della domanda",
      "options": ["opzione a", "opzione b", "opzione c", "opzione d"],
      "correct_answer": "testo esatto dell'opzione corretta",
      "explanation": "spiegazione breve della risposta"
    }
  ]
}
SYSTEM;

        // Limite contenuto configurabile: i pool grandi hanno bisogno di più
        // materiale per varietà (default storico 6000 per il single-call).
        $contentChars = (int) ($options['content_chars'] ?? 6000);

        $userPrompt = "Genera {$numQuestions} domande a risposta multipla per il {$subjectNoun} '{$contextLabel}'.\n\n";
        $userPrompt .= "Ecco il contenuto su cui basare le domande:\n\n";
        $userPrompt .= substr(strip_tags($content), 0, $contentChars);

        // Anti-ripetizione tra batch del pool: l'elenco delle domande già generate
        // viene passato perché il modello ne produca di NUOVE (riduce i duplicati a monte).
        $avoid = $options['avoid'] ?? [];
        if (!empty($avoid)) {
            $userPrompt .= "\n\nNON ripetere né riformulare queste domande GIÀ generate (creane di diverse, su altri aspetti del contenuto):\n- "
                . implode("\n- ", array_slice($avoid, 0, 60));
        }

        $userPrompt .= "\n\nRispondi SOLO con JSON valido.";

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => self::CLAUDE_MODEL,
                'max_tokens' => 4096,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ]);

            if ($response->failed()) {
                Log::warning('QuizGeneratorService: API call failed', ['status' => $response->status()]);
                return null;
            }

            $text = $response->json('content.0.text', '');
            $text = preg_replace('/```json\s*/i', '', $text);
            $text = preg_replace('/```\s*/i', '', $text);
            $data = json_decode(trim($text), true);

            if (!$data || !isset($data['questions']) || !is_array($data['questions']) || empty($data['questions'])) {
                Log::warning('QuizGeneratorService: invalid JSON response');
                return null;
            }

            return [
                'questions' => $data['questions'],
                'meta' => [
                    'model' => self::CLAUDE_MODEL,
                    'tokens_in' => (int) $response->json('usage.input_tokens', 0),
                    'tokens_out' => (int) $response->json('usage.output_tokens', 0),
                    'prompt_version' => self::PROMPT_VERSION,
                    'questions_count' => count($data['questions']),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('QuizGeneratorService error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Persiste un quiz + le sue domande. Gli attributi di $attrs prevalgono sui
     * default; i quiz Schola passano module_id e course_id NULL (vivono fuori dal
     * mondo corsi, agganciati a un teaching_artifact via quiz_id).
     *
     * @param  array  $attrs      attributi del Quiz
     * @param  array  $questions  domande nel formato JSON del modello
     */
    public function persistQuiz(array $attrs, array $questions): Quiz
    {
        $quiz = Quiz::create(array_merge([
            'passing_score' => 70,
            'is_active' => true,
            'randomize_questions' => true,
            'show_results_immediately' => true,
        ], $attrs));

        $this->syncQuestions($quiz, $questions);

        return $quiz;
    }

    /**
     * Sostituisce le domande di un quiz esistente (usato in rigenerazione: il
     * quiz_id resta stabile, niente quiz orfani). Riusato anche da persistQuiz.
     */
    public function syncQuestions(Quiz $quiz, array $questions): void
    {
        $quiz->questions()->delete();

        foreach (array_values($questions) as $i => $q) {
            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $q['question'] ?? '',
                'type' => 'multiple_choice',
                'options' => $q['options'] ?? [],
                'correct_answer' => $q['correct_answer'] ?? '',
                'explanation' => $q['explanation'] ?? null,
                'points' => 1,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
