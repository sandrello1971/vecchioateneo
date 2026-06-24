<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EvaluatesExamState;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UnansweredQuestion;
use App\Services\RagService;
use App\Support\StudentCourseAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    use EvaluatesExamState;

    public function __construct(
        private RagService $rag,
        private StudentCourseAccess $courseAccess,
    ) {}

    public function show(Course $course)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $student = Student::findOrFail($studentId);
        $enrolled = $student->auto_enroll_all_courses
            ? $course->is_active
            : $student->courses()->where('courses.id', $course->id)->exists();
        abort_unless($enrolled, 403);

        $conversation = ChatConversation::firstOrCreate(
            ['student_id' => $studentId, 'course_id' => $course->id, 'is_active' => true],
            ['title' => 'Chat ' . $course->name]
        );

        $messages = $conversation->messages()->orderBy('created_at')->get();

        return view('student.chat.show', compact('course', 'conversation', 'messages'));
    }

    public function minervaAsk(Request $request)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        if ($this->hasActiveExam($studentId)) {
            return response()->json([
                'error' => 'Minerva non è disponibile durante un esame in corso.',
                'exam_lock' => true,
            ], 423);
        }

        // === Schola — Minerva di classe (pacchetto 6b) ===
        // Contesto di classe opzionale: quando presente si applica il vincolo §5
        // (retrieval di classe + gate). Il mondo corsi qui sotto resta INVARIATO.
        if ($request->filled('school_class_id')) {
            return $this->classMinervaAsk($request, Student::findOrFail($studentId));
        }

        $data = $request->validate([
            'question' => 'required|string|max:4000',
            'history' => 'nullable|array',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string',
            'mode' => 'nullable|in:summary,expand',
        ]);

        $student = Student::findOrFail($studentId);
        $isInstructor = ($student->isInstructor());

        // Scoping unico via StudentCourseAccess: i corsi navigabili sono
        // quelli a cui l'utente è iscritto + (per i formatori) quelli che
        // insegna. Il flag $isInstructor influenza solo il tono del prompt,
        // mai lo scope dei dati.
        $navigable = $this->courseAccess->navigableCourses($student);
        $courseIds = $navigable->pluck('id')->all();
        $courseNames = $navigable->pluck('name')->all();

        // Documenti instructor-only ammessi SOLO per i corsi insegnati,
        // mai globalmente.
        $instructorScopedCourseIds = $navigable
            ->where('access_kind', 'teaching')
            ->pluck('id')->all();

        $mode = $data['mode'] ?? 'summary';

        $docs = $this->rag->searchScoped(
            $data['question'],
            $courseIds,
            $instructorScopedCourseIds,
            $isInstructor ? 6 : 4
        );

        $videoDocs = [];
        if (!empty($courseIds)) {
            foreach ($courseIds as $cid) {
                $found = $this->rag->searchVideos($data['question'], $cid, null, 1);
                foreach ($found as $f) $videoDocs[] = $f;
            }
            $videoDocs = array_slice($videoDocs, 0, 2);
        }

        $context = '';
        if (!empty($docs) && count($docs) > 0) {
            $context .= "📚 DOCUMENTI DEI CORSI:\n\n";
            foreach ($docs as $doc) {
                $content = is_array($doc) ? $doc['content'] : $doc->content;
                $title = is_array($doc) ? $doc['title'] : $doc->title;
                $context .= "--- {$title} ---\n{$content}\n\n";
            }
        }
        if (!empty($videoDocs)) {
            $context .= "\n🎬 CONTENUTO VIDEO:\n\n";
            foreach ($videoDocs as $doc) {
                $ts = $doc['timestamp'] ?? '';
                $tsStr = $ts ? " [{$ts}]" : '';
                $title = $doc['title'] ?? 'Video';
                $content = $doc['content'] ?? '';
                $context .= "--- {$title}{$tsStr} ---\n{$content}\n\n";
            }
        }

        $reply = $this->callClaudeForMinerva($data['question'], $data['history'] ?? [], $context, $courseNames, $mode, $isInstructor);

        return response()->json([
            'answer' => $reply['content'],
            'tokens' => $reply['tokens'] ?? null,
            'mode' => $mode,
        ]);
    }

    /**
     * Compone il system prompt globale di Minerva (bubble). Pubblico per
     * testabilità (no chiamate API, solo string composition).
     * Identità configurabile via settings (Fase 1), comportamento cablato.
     */
    public function buildMinervaSystemPrompt(
        array $courseNames,
        string $mode,
        bool $isInstructor,
        string $context = ''
    ): string {
        $coursesList = empty($courseNames) ? 'nessun corso attivo' : implode(', ', $courseNames);
        $isSingleCourse = count($courseNames) === 1;

        // Identità: l'UNICA parte del prompt che cambia con i settings.
        // Comportamento cablato → non sovrascrivibile dal cliente:
        // garanzia di affidabilità (scope RAG, citazione fonti, rifiuto
        // off-topic). Vedi Fase 1 §5.
        $assistantName = atheneum_setting('assistant_name', 'Minerva');
        $assistantRole = atheneum_setting('assistant_role_label', "l'assistente AI di formazione");
        $platformName  = atheneum_setting('instance_name', 'Atheneum');
        $domainContext = atheneum_setting('assistant_domain_context', '');
        $identity = "Sei {$assistantName}, {$assistantRole} di {$platformName}.";
        if ($domainContext !== '') {
            $identity .= " La piattaforma si rivolge a: {$domainContext}.";
        }

        $lengthRule = $mode === 'expand'
            ? "Rispondi in modo approfondito e dettagliato, con esempi e sezioni. Usa markdown: ## per titoli di sezione, **grassetto**, liste, citazioni >."
            : "Rispondi in 2-3 frasi brevi, massimo 60 parole complessive. Una risposta diretta, senza liste, senza titoli, senza esempi multipli. Lo studente potrà chiedere l'approfondimento con un tasto dedicato. Usa al massimo un **grassetto** sul concetto centrale. NON usare bullet, numerazioni, markdown di struttura. Vai dritto al punto.";

        $scopeRule = $isInstructor
            ? "Stai rispondendo a un formatore. Il formatore ha accesso ai corsi che insegna e a quelli a cui è iscritto: {$coursesList}. Non ha accesso ad altri corsi."
            : ($isSingleCourse
                ? "Lo studente ha accesso SOLO al corso: {$coursesList}. Rispondi basandoti sui contenuti di quel corso. Se la domanda tocca argomenti che vengono approfonditi in ALTRI corsi della piattaforma (non iscritti), accenna brevemente al fatto che 'altri corsi della piattaforma approfondiscono questo tema' — senza nominarli esplicitamente — e offri la risposta più utile possibile sul suo corso."
                : "Lo studente ha accesso ai corsi: {$coursesList}. Rispondi sui contenuti di tutti questi corsi, e anche sul contesto della piattaforma.");

        $rolePrompt = $isInstructor
            ? "IMPORTANTE: stai rispondendo a un FORMATORE, non a uno studente. Il formatore usa queste risposte per preparare lezioni e gestire l'aula. Quando rispondi:\n"
              . "- Fornisci la risposta come la daresti a uno studente, MA poi aggiungi una sezione finale intitolata '## 🎓 Note per il formatore' con:\n"
              . "  • suggerimenti didattici su come spiegare il concetto in aula\n"
              . "  • errori comuni degli studenti da anticipare\n"
              . "  • analogie o esempi aggiuntivi utili per chiarire\n"
              . "  • eventuali collegamenti con altri moduli/corsi\n"
              . "- Se nei documenti forniti ci sono chunks marcati come 'Manuale Formatore' o simile, USALI soprattutto per la sezione Note formatore.\n"
              . "- Lo scope dei contenuti è già limitato dal sistema ai corsi che insegna o a cui è iscritto: limita la risposta a quei contenuti, non spaziare su corsi non disponibili."
            : "";

        $behavior = <<<TXT
{$scopeRule}

{$rolePrompt}

{$lengthRule}

Regole:
- Rispondi in italiano
- Se citi un video con timestamp, formatta come [MM:SS] — lo studente può cliccarci
- Se citi un documento, cita il titolo
- Non inventare. Se l'informazione non è nei materiali forniti, dillo onestamente e usa il tuo buon senso generale
- Sii diretto, chiaro, incoraggiante

{$context}
TXT;

        return $identity . "\n\n" . $behavior;
    }

    private function callClaudeForMinerva(
        string $question,
        array $history,
        string $context,
        array $courseNames,
        string $mode,
        bool $isInstructor = false
    ): array {
        $systemPrompt = $this->buildMinervaSystemPrompt(
            $courseNames, $mode, $isInstructor, $context
        );

        $messages = array_values($history);
        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => $mode === 'expand' ? 4096 : 200,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                \Log::error('Minerva Claude API failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return ['content' => 'Errore nella risposta. Riprova.', 'tokens' => null];
            }

            $body = $response->json();
            return [
                'content' => $body['content'][0]['text'] ?? 'Risposta vuota.',
                'tokens' => ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['content' => 'Assistente momentaneamente non disponibile.', 'tokens' => null];
        }
    }

    public function sendMessage(Request $request)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        if ($this->hasActiveExam($studentId)) {
            return response()->json([
                'error' => 'Minerva non è disponibile durante un esame in corso.',
                'exam_lock' => true,
            ], 423);
        }

        $data = $request->validate([
            'conversation_id' => 'required|uuid',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = ChatConversation::with('course')
            ->where('id', $data['conversation_id'])
            ->where('student_id', $studentId)
            ->firstOrFail();

        // Riverifica iscrizione a ogni messaggio: una conversazione
        // resta utilizzabile solo finché il corso è effettivamente
        // accessibile (iscrizione attiva o corso insegnato).
        $student = Student::findOrFail($studentId);
        $navigableIds = $this->courseAccess->navigableCourses($student)
            ->pluck('id')->all();
        abort_unless(
            in_array($conversation->course_id, $navigableIds, true),
            403,
            'Non hai più accesso a questo corso.'
        );

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $docs = $this->rag->search($data['message'], $conversation->course_id, 4);
        $videoDocs = $this->rag->searchVideos($data['message'], $conversation->course_id, null, 2);

        $context = '';
        if (!empty($docs) && count($docs) > 0) {
            $context .= "📚 DOCUMENTI DEL CORSO:\n\n";
            foreach ($docs as $doc) {
                $content = is_array($doc) ? $doc['content'] : $doc->content;
                $title = is_array($doc) ? $doc['title'] : $doc->title;
                $context .= "--- {$title} ---\n{$content}\n\n";
            }
        }

        if (!empty($videoDocs)) {
            $context .= "\n🎬 CONTENUTO VIDEO DEL CORSO:\n\n";
            foreach ($videoDocs as $doc) {
                $ts = $doc['timestamp'] ? " [{$doc['timestamp']}]" : '';
                $context .= "--- {$doc['title']}{$ts} ---\n{$doc['content']}\n\n";
            }
        }

        $reply = $this->callClaude($conversation, $data['message'], $context);

        $contextDocs = array_merge(
            array_map(fn($d) => is_array($d) ? $d['title'] : $d->title, $docs->all()),
            array_map(fn($d) => $d['title'] . ($d['timestamp'] ? ' [' . $d['timestamp'] . ']' : ''), $videoDocs)
        );

        $aiMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'tokens_used' => $reply['tokens'] ?? null,
            'context_documents' => $contextDocs,
        ]);

        $timestamps = [];
        preg_match_all('/\[(\d{1,2}:\d{2}(?::\d{2})?)\]/', $reply['content'], $matches);
        if (!empty($matches[1])) {
            $timestamps = array_values(array_unique($matches[1]));
        }

        return response()->json([
            'message' => $aiMessage->content,
            'id' => $aiMessage->id,
            'timestamps' => $timestamps,
        ]);
    }

    /**
     * Compone il system prompt della chat di corso (chat persistente).
     * Pubblico per testabilità. Identità dai settings, comportamento
     * cablato (Fase 1 §5).
     */
    public function buildCourseChatSystemPrompt(string $courseName, string $context = ''): string
    {
        $assistantName = atheneum_setting('assistant_name', 'Minerva');
        $assistantRole = atheneum_setting('assistant_role_label', "l'assistente AI di formazione");
        $platformName  = atheneum_setting('instance_name', 'Atheneum');
        $domainContext = atheneum_setting('assistant_domain_context', '');
        $identity = "Sei {$assistantName}, {$assistantRole} per il corso {$courseName} di {$platformName}.";

        $domainBullet = $domainContext !== ''
            ? "- Chiarire concetti difficili con esempi pratici legati a: {$domainContext}"
            : "- Chiarire concetti difficili con esempi pratici concreti";

        $behavior = <<<TXT
Il tuo ruolo:
- Aiutare gli studenti a comprendere i contenuti del corso
- Rispondere basandoti sui DOCUMENTI e sui VIDEO del corso
- Quando citi informazioni da un video, indica SEMPRE il timestamp [MM:SS]
- Quando citi informazioni da un documento, cita il titolo del documento
{$domainBullet}

Regole:
- Rispondi SEMPRE in italiano
- Basa le risposte PRINCIPALMENTE sui contenuti del corso forniti
- Se citi un timestamp video, formattalo come [MM:SS] — lo studente può cliccarci per saltare al punto
- Se non sai qualcosa, dillo onestamente
- Sii chiaro, diretto, incoraggiante

{$context}
TXT;

        return $identity . "\n\n" . $behavior;
    }

    private function callClaude(ChatConversation $conversation, string $userMessage, string $context = ''): array
    {
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $history[] = ['role' => 'user', 'content' => $userMessage];

        $platformName = atheneum_setting('instance_name', 'Atheneum');
        $courseName = $conversation->course?->name ?? $platformName;

        $systemPrompt = $this->buildCourseChatSystemPrompt($courseName, $context);

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'messages' => $history,
            ]);

            if ($response->failed()) {
                return ['content' => 'Errore nella risposta dell\'assistente. Riprova.', 'tokens' => null];
            }

            $body = $response->json();
            return [
                'content' => $body['content'][0]['text'] ?? 'Risposta vuota.',
                'tokens' => ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['content' => 'Assistente momentaneamente non disponibile.', 'tokens' => null];
        }
    }

    // ===================== SCHOLA — Minerva di classe (pacchetto 6b) =====================

    /**
     * Pagina chat di classe (studente o docente). Stessa view, ruolo dedotto
     * dalla proprietà della classe (docente) o dall'iscrizione attiva (studente).
     */
    public function showClass(SchoolClass $class)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);
        $student = Student::findOrFail($studentId);

        $asDocente = $this->resolveClassRole($class, $student); // true=docente, false=studente, null=403
        abort_if($asDocente === null, 403, 'Non hai accesso a questa classe.');

        $conversation = $this->classConversation($student, $class);
        $messages = $conversation->messages()->orderBy('created_at')->get();

        return view('student.chat.class', compact('class', 'student', 'asDocente', 'conversation', 'messages'));
    }

    /**
     * Q&A di classe con il vincolo §5. Chiamata da minervaAsk quando è presente
     * school_class_id. NON tocca il percorso corsi.
     */
    private function classMinervaAsk(Request $request, Student $student)
    {
        $data = $request->validate([
            'question' => 'required|string|max:4000',
            'school_class_id' => 'required|uuid',
            'artifact_id' => 'nullable|uuid',
            'lesson_id' => 'nullable|uuid',
            'history' => 'nullable|array',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string',
        ]);

        $class = SchoolClass::find($data['school_class_id']);
        abort_unless($class, 403);

        // Ruolo + scope. Studente: SOLO la sua classe (enrollment active).
        // Docente: teacher_private (suoi) + class (questa sua classe).
        $asDocente = $this->resolveClassRole($class, $student);
        abort_if($asDocente === null, 403, 'Non hai accesso a questa classe.');

        // Minerva di lezione (P20b): il contesto è una lezione PUBBLICATA su questa
        // classe. Verifica la pubblicazione (niente leak: senza pubblicazione 403).
        $lessonId = $data['lesson_id'] ?? null;
        if ($lessonId !== null) {
            $published = \App\Models\LessonPublication::where('lesson_id', $lessonId)
                ->where('school_class_id', $class->id)->exists();
            abort_unless($published, 403, 'Lezione non disponibile in questa classe.');
        }

        // Rate limit giornaliero (solo studenti, §8.2): il modello NON viene
        // chiamato a soglia raggiunta. Messaggio gentile, niente conteggio extra.
        if (!$asDocente) {
            $usage = app(\App\Services\Schola\ScholaUsage::class);
            if (!$usage->chatStatus($student->id)['allowed']) {
                return response()->json([
                    'answer' => $usage->limitMessage('chat'),
                    'sources' => [],
                    'gate' => 'rate_limited',
                ]);
            }
        }

        $teacherId = $asDocente ? $student->id : null;
        $classIds = [$class->id];
        $artifactId = $data['artifact_id'] ?? null;

        // Retrieval di classe (gate §5). Pre-filtro sull'artefatto o sulla LEZIONE
        // se richiesto: si interroga "prima di tutto" quel contesto, poi — se vuoto —
        // si allarga ai materiali della classe (resta dentro lo scope §5).
        $result = $this->rag->searchClassScopedScored($data['question'], $classIds, $teacherId, 6, $artifactId, $lessonId);
        if (($artifactId || $lessonId) && $result['docs']->isEmpty()) {
            $result = $this->rag->searchClassScopedScored($data['question'], $classIds, $teacherId, 6, null, null);
        }

        $conversation = $this->classConversation($student, $class);
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $data['question'],
        ]);

        // GATE §5: nessun chunk sopra soglia → il modello NON viene chiamato.
        if ($result['docs']->isEmpty()) {
            UnansweredQuestion::create([
                'school_class_id' => $class->id,
                'student_id' => $asDocente ? null : $student->id,
                'question' => $data['question'],
                'best_similarity' => $result['best_similarity'],
                'status' => 'open',
            ]);

            $answer = $asDocente
                ? 'Non trovo questo nei tuoi materiali. Prova con altre parole, oppure genera/pubblica un materiale che copra questo argomento.'
                : 'Questo argomento non è nei materiali della tua classe — chiedi al tuo docente, o riprova con altre parole.';

            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $answer,
                'context_documents' => [],
            ]);

            return response()->json([
                'answer' => $answer,
                'sources' => [],
                'gate' => 'empty',
            ]);
        }

        // Contesto + citazioni dai chunk recuperati.
        [$context, $sources] = $this->buildClassContextAndSources($result['docs'], $asDocente);

        $reply = $this->callClaudeForClass(
            $data['question'],
            $data['history'] ?? [],
            $context,
            $asDocente
        );

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'tokens_used' => $reply['tokens'] ?? null,
            'context_documents' => $sources,
        ]);

        return response()->json([
            'answer' => $reply['content'],
            'sources' => $sources,
            'tokens' => $reply['tokens'] ?? null,
            'gate' => 'answered',
        ]);
    }

    /**
     * Ruolo nel contesto classe: true=docente proprietario, false=studente con
     * iscrizione ATTIVA, null=nessun accesso (pending/removed/estraneo).
     */
    private function resolveClassRole(SchoolClass $class, Student $student): ?bool
    {
        // Docente: proprietà (classe libera) O cattedra (classe di scuola) — P15.
        if (app(\App\Services\Schola\TeacherClassAccess::class)->canTeach($student->id, $class)) {
            return true;
        }
        $active = ClassStudent::where('school_class_id', $class->id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->exists();

        return $active ? false : null;
    }

    private function classConversation(Student $student, SchoolClass $class): ChatConversation
    {
        return ChatConversation::firstOrCreate(
            ['student_id' => $student->id, 'school_class_id' => $class->id, 'is_active' => true],
            ['title' => 'Classe ' . $class->name, 'course_id' => null]
        );
    }

    /**
     * System prompt Schola (SEPARATO da quello corsi): vincolo §5 cablato.
     * Pubblico per testabilità.
     */
    public function buildScholaSystemPrompt(bool $asDocente, string $context = ''): string
    {
        $assistantName = atheneum_setting('assistant_name', 'Minerva');
        $platformName = atheneum_setting('instance_name', 'Atheneum');

        $audience = $asDocente
            ? "Stai assistendo un DOCENTE sui materiali della sua classe."
            : "Stai assistendo uno STUDENTE di scuola superiore sui materiali della sua classe. Usa un tono chiaro, semplice e incoraggiante.";

        return <<<TXT
Sei {$assistantName}, l'assistente di studio di {$platformName} per la classe.

{$audience}

VINCOLO ASSOLUTO (non derogabile):
- Rispondi ESCLUSIVAMENTE in base al CONTESTO fornito qui sotto (i materiali della classe).
- È VIETATO integrare con la tua conoscenza generale o con informazioni esterne al contesto.
- Se il contesto copre solo parzialmente la domanda, RISPONDI su ciò che è coperto e DICHIARA esplicitamente cosa manca.
- Non rivelare MAI l'esistenza o il contenuto di materiali fuori da questo contesto.
- Cita sempre i materiali da cui trai la risposta (titolo; per i video, il minutaggio [mm:ss]).
- Rispondi in italiano.

Se il contesto non contiene la risposta, dillo chiaramente e invita a rivolgersi al docente: NON inventare.

CONTESTO (materiali della classe):
{$context}
TXT;
    }

    private function callClaudeForClass(string $question, array $history, string $context, bool $asDocente): array
    {
        $systemPrompt = $this->buildScholaSystemPrompt($asDocente, $context);

        $messages = array_values($history);
        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                \Log::error('Minerva classe Claude API failed', ['status' => $response->status()]);
                return ['content' => 'Errore nella risposta. Riprova.', 'tokens' => null];
            }

            $body = $response->json();
            return [
                'content' => $body['content'][0]['text'] ?? 'Risposta vuota.',
                'tokens' => ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['content' => 'Assistente momentaneamente non disponibile.', 'tokens' => null];
        }
    }

    /**
     * Costruisce il contesto testuale e l'elenco delle fonti (citazioni) dai
     * chunk recuperati. Ogni fonte: titolo + eventuale minutaggio + link.
     *
     * @return array{0: string, 1: array<int, array>}
     */
    private function buildClassContextAndSources($docs, bool $asDocente): array
    {
        $context = '';
        $sources = [];
        $seen = [];

        foreach ($docs as $doc) {
            $meta = is_array($doc->metadata) ? $doc->metadata : [];
            $title = $doc->title ?: 'Materiale';
            $start = $meta['start_seconds'] ?? null;
            $tsStr = $start !== null ? $this->formatTimestampSeconds((int) $start) : null;

            $ctxLabel = $tsStr ? "{$title} [{$tsStr}]" : $title;
            $context .= "--- {$ctxLabel} ---\n{$doc->content}\n\n";

            // Link: youtube → url con ?t=secondi; altrimenti (docente) pagina artefatto.
            $url = null;
            if (!empty($meta['source_url'])) {
                $url = $meta['source_url'];
                if ($start !== null) {
                    $url .= (str_contains($url, '?') ? '&' : '?') . 't=' . (int) $start;
                }
            } elseif ($asDocente && !empty($meta['artifact_id'])) {
                $url = route('docente.artifacts.show', $meta['artifact_id']);
            }

            // Dedup per (artifact_id|titolo|minutaggio).
            $key = ($meta['artifact_id'] ?? $title) . '|' . ($tsStr ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $sources[] = [
                'title' => $title,
                'artifact_id' => $meta['artifact_id'] ?? null,
                'seconds' => $start !== null ? (int) $start : null,
                'timestamp' => $tsStr,
                'url' => $url,
            ];
        }

        return [$context, $sources];
    }

    private function formatTimestampSeconds(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
