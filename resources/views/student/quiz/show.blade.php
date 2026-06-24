@extends('layouts.student')
@section('title', $quiz->title)
@section('breadcrumb', 'Quiz · ' . $quiz->title)

@section('content')
<div style="max-width:800px;" x-data="quizApp()" x-init="init()">

    {{-- INTRO --}}
    <div x-show="phase === 'intro'" style="background:white; border-radius:16px; padding:40px; text-align:center;">
        <div style="font-size:3rem; margin-bottom:16px;">📝</div>
        <h1 style="font-size:1.5rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">{{ $quiz->title }}</h1>
        @if($quiz->description)
        <p style="color:#8A9696; margin-bottom:24px;">{{ $quiz->description }}</p>
        @endif

        <div style="display:flex; justify-content:center; gap:24px; margin-bottom:32px;">
            <div style="text-align:center;">
                <div style="font-size:1.5rem; font-weight:700; color:#55B1AE;">{{ $displayCount }}</div>
                <div style="font-size:0.75rem; color:#8A9696;">
                    Domande
                    @if($quiz->questions_per_attempt && $poolCount > $displayCount)
                        <span style="color:#8A9696;">(estratte a caso da {{ $poolCount }})</span>
                    @endif
                </div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.5rem; font-weight:700; color:#55B1AE;">{{ $quiz->passing_score }}%</div>
                <div style="font-size:0.75rem; color:#8A9696;">Soglia</div>
            </div>
            @if($quiz->time_limit_minutes)
            <div style="text-align:center;">
                <div style="font-size:1.5rem; font-weight:700; color:#E28A53;">{{ $quiz->time_limit_minutes }}'</div>
                <div style="font-size:0.75rem; color:#8A9696;">Minuti</div>
            </div>
            @endif
        </div>

        @php
            $isExam = !empty($quiz->course_id) && empty($quiz->module_id);
            $effMax = $effectiveMax ?? null;
            $used = $usedAttempts ?? 0;
            $passed = $alreadyPassed ?? false;
            $exhausted = $isExam && $effMax !== null && $used >= $effMax && !$passed;
        @endphp
        @if($isExam && $effMax !== null)
        <div style="background:{{ $exhausted ? '#fff3ec' : '#E8F5F5' }};
                    border:1px solid {{ $exhausted ? '#E28A53' : '#55B1AE' }};
                    border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:0.8rem;
                    color:{{ $exhausted ? '#c97a45' : '#3A8C89' }};">
            Tentativi: <strong>{{ $used }}/{{ $effMax }}</strong>
            @if($exhausted) — esauriti, contatta il formatore @endif
        </div>
        @endif

        @if($pastAttempts->count() > 0)
        <div style="background:#F5F7F7; border-radius:10px; padding:16px; margin-bottom:24px; text-align:left;">
            <div style="font-size:0.8rem; font-weight:700; color:#4A5252; margin-bottom:8px;">I tuoi tentativi precedenti</div>
            @foreach($pastAttempts->take(3) as $attempt)
            <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #E8F5F5; font-size:0.8rem;">
                <span style="color:#4A5252;">{{ $attempt->completed_at?->format('d/m/Y H:i') }}</span>
                <span style="font-weight:700; color:{{ $attempt->passed ? '#3A8C89' : '#E28A53' }}">
                    @if($attempt->passed)
                        {{ $attempt->score }}% — ✓ Superato
                    @elseif($attempt->abandoned)
                        ✗ Interrotto — non superato
                    @else
                        {{ $attempt->score }}% — ✗ Non superato
                    @endif
                </span>
            </div>
            @endforeach
        </div>
        @endif

        @if($passed)
        <div style="padding:14px 24px; background:#E8F5F5; color:#3A8C89; border-radius:10px; font-size:0.9rem; font-weight:600; display:inline-block;">
            ✓ Esame già superato. Nessun nuovo tentativo necessario.
        </div>
        @elseif($exhausted)
        <button disabled
                style="padding:14px 40px; background:#C8D0D0; color:white; border:none; border-radius:10px; font-size:1rem; font-weight:700; cursor:not-allowed; opacity:0.7;">
            Tentativi esauriti
        </button>
        <p style="font-size:0.75rem; color:#8A9696; margin-top:10px;">
            Contatta il formatore per richiedere un tentativo aggiuntivo.
        </p>
        @else
        <button @click="startQuiz()"
                style="padding:14px 40px; background:#55B1AE; color:white; border:none; border-radius:10px; font-size:1rem; font-weight:700; cursor:pointer;">
            Inizia il quiz →
        </button>
        @endif
    </div>

    {{-- QUIZ IN CORSO --}}
    <div x-show="phase === 'quiz'" x-cloak>

        {{-- HEADER con progresso e timer --}}
        <div style="background:white; border-radius:12px; padding:16px 20px; margin-bottom:16px; display:flex; align-items:center; gap:16px;">
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span style="font-size:0.8rem; color:#8A9696;">Domanda <span x-text="current + 1"></span> di <span x-text="questions.length"></span></span>
                    <span style="font-size:0.8rem; font-weight:700; color:#55B1AE;" x-text="(questions.length ? Math.round((current / questions.length) * 100) : 0) + '%'"></span>
                </div>
                <div style="height:6px; background:#E8F5F5; border-radius:3px; overflow:hidden;">
                    <div style="height:100%; background:#55B1AE; border-radius:3px; transition:width 0.3s;"
                         :style="'width:' + (questions.length ? Math.round((current / questions.length) * 100) : 0) + '%'"></div>
                </div>
            </div>
            @if($quiz->time_limit_minutes)
            <div style="text-align:center; min-width:60px;">
                <div style="font-size:1.1rem; font-weight:700;" :style="timeLeft < 60 ? 'color:#E28A53' : 'color:#1A1F1F'" x-text="formatTime(timeLeft)"></div>
                <div style="font-size:0.65rem; color:#8A9696;">rimasto</div>
            </div>
            @endif
        </div>

        {{-- DOMANDA --}}
        <template x-for="(q, idx) in questions" :key="q.id">
            <div x-show="current === idx" style="background:white; border-radius:16px; padding:32px; margin-bottom:16px;">
                <div style="font-size:0.75rem; font-weight:700; color:#55B1AE; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:12px;">
                    Domanda <span x-text="idx + 1"></span>
                </div>
                <h2 style="font-size:1.1rem; font-weight:600; color:#1A1F1F; line-height:1.6; margin-bottom:24px;" x-text="q.question"></h2>

                {{-- OPZIONI --}}
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <template x-for="(opt, oidx) in q.options" :key="oidx">
                        <button type="button"
                                @click="proposeAnswer(q.id, opt)"
                                :disabled="answered[q.id] !== undefined"
                                :style="getOptionStyle(q.id, opt)"
                                style="padding:14px 18px; border-radius:10px; text-align:left; cursor:pointer; font-size:0.9rem; transition:all 0.2s; display:flex; align-items:center; gap:12px;">
                            <span style="width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; flex-shrink:0;"
                                  :style="getLetterStyle(q.id, opt)"
                                  x-text="String.fromCharCode(65 + oidx)"></span>
                            <span x-text="opt"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- NAVIGAZIONE --}}
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <button @click="prev()" x-show="current > 0"
                    style="padding:10px 20px; border:1px solid #C8D0D0; background:white; color:#4A5252; border-radius:8px; cursor:pointer; font-size:0.875rem;">
                ← Precedente
            </button>
            <div x-show="current === 0"></div>

            <button @click="next()" x-show="current < questions.length - 1"
                    :disabled="answered[questions[current]?.id] === undefined"
                    :style="answered[questions[current]?.id] !== undefined ? 'opacity:1;cursor:pointer' : 'opacity:0.4;cursor:not-allowed'"
                    style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600;">
                Prossima →
            </button>

            <button @click="submitQuiz()" x-show="current === questions.length - 1"
                    :disabled="answered[questions[current]?.id] === undefined || submitting"
                    :style="(answered[questions[current]?.id] !== undefined && !submitting) ? 'opacity:1;cursor:pointer' : 'opacity:0.4;cursor:not-allowed'"
                    style="padding:10px 24px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700;">
                <span x-show="!submitting">Consegna quiz ✓</span>
                <span x-show="submitting">Invio in corso…</span>
            </button>
        </div>
    </div>

    {{-- MODAL CONFERMA RISPOSTA --}}
    <div x-show="pendingAnswer" x-cloak
         style="position:fixed; inset:0; background:rgba(26,31,31,0.75); display:flex; align-items:center; justify-content:center; z-index:200;">
        <div style="background:white; border-radius:16px; padding:28px; max-width:400px; width:90%; text-align:center;">
            <div style="font-size:2rem; margin-bottom:8px;">🎯</div>
            <div style="font-weight:700; color:#1A1F1F; font-size:1.05rem; margin-bottom:6px;">La accendiamo?</div>
            <div style="color:#8A9696; font-size:0.85rem; margin-bottom:10px; line-height:1.5;">
                Vuoi confermare la tua risposta?
            </div>
            <div x-show="pendingAnswer" style="padding:10px 14px; background:#F5F7F7; border-radius:8px; margin-bottom:16px; font-size:0.85rem; color:#4A5252; text-align:left;">
                <span x-text="pendingAnswer?.opt"></span>
            </div>
            <div style="display:flex; gap:10px; justify-content:center;">
                <button @click="cancelAnswer()" type="button"
                        style="padding:10px 22px; border:1px solid #C8D0D0; background:white; color:#4A5252; border-radius:8px; font-size:0.85rem; cursor:pointer;">
                    Annulla
                </button>
                <button @click="confirmAnswer()" type="button"
                        style="padding:10px 28px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">
                    OK, confermo
                </button>
            </div>
        </div>
    </div>

    {{-- RISULTATO --}}
    <div x-show="phase === 'result'" x-cloak style="background:white; border-radius:16px; padding:40px; text-align:center;">
        <div style="font-size:4rem; margin-bottom:16px;" x-text="passed ? '🎉' : '💪'"></div>
        <h1 style="font-size:1.75rem; font-weight:700; margin-bottom:8px;"
            :style="passed ? 'color:#3A8C89' : 'color:#E28A53'"
            x-text="passed ? 'Quiz superato!' : 'Quasi!'"></h1>
        <p style="color:#8A9696; margin-bottom:32px;"
           x-text="passed ? 'Ottimo lavoro! Hai dimostrato una buona comprensione dei contenuti.' : 'Non preoccuparti, ripasssa i contenuti e riprova.'"></p>

        {{-- PUNTEGGIO --}}
        <div style="position:relative; width:140px; height:140px; margin:0 auto 32px;">
            <svg viewBox="0 0 140 140" style="transform:rotate(-90deg);">
                <circle cx="70" cy="70" r="60" fill="none" stroke="#E8F5F5" stroke-width="12"/>
                <circle cx="70" cy="70" r="60" fill="none" stroke-width="12"
                        :stroke="passed ? '#55B1AE' : '#E28A53'"
                        stroke-linecap="round"
                        :stroke-dasharray="'377'"
                        :stroke-dashoffset="377 - (377 * score / 100)"/>
            </svg>
            <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                <div style="font-size:2rem; font-weight:700; color:#1A1F1F;" x-text="score + '%'"></div>
                <div style="font-size:0.7rem; color:#8A9696;">punteggio</div>
            </div>
        </div>

        {{-- DETTAGLIO --}}
        <div style="display:flex; justify-content:center; gap:32px; margin-bottom:32px;">
            <div>
                <div style="font-size:1.25rem; font-weight:700; color:#3A8C89;" x-text="correctCount"></div>
                <div style="font-size:0.75rem; color:#8A9696;">Corrette</div>
            </div>
            <div>
                <div style="font-size:1.25rem; font-weight:700; color:#E28A53;" x-text="questions.length - correctCount"></div>
                <div style="font-size:0.75rem; color:#8A9696;">Errate</div>
            </div>
            <div>
                <div style="font-size:1.25rem; font-weight:700; color:#8A9696;">{{ $quiz->passing_score }}%</div>
                <div style="font-size:0.75rem; color:#8A9696;">Soglia</div>
            </div>
        </div>

        {{-- RIEPILOGO RISPOSTE --}}
        <div style="text-align:left; margin-bottom:32px; max-height:300px; overflow-y:auto;">
            <div style="font-size:0.8rem; font-weight:700; color:#4A5252; margin-bottom:10px;">Riepilogo risposte</div>
            <template x-for="q in questions" :key="q.id">
                <div style="padding:10px 0; border-bottom:1px solid #F5F7F7; display:flex; gap:10px; align-items:flex-start;">
                    <span style="font-size:0.9rem;" x-text="answered[q.id] === corrections[q.id]?.correct_answer ? '✅' : '❌'"></span>
                    <div style="flex:1;">
                        <div style="font-size:0.85rem; color:#1A1F1F; font-weight:500;" x-text="q.question"></div>
                        <div style="font-size:0.75rem; color:#8A9696; margin-top:2px;">
                            La tua risposta: <span style="font-weight:600;" :style="answered[q.id] === corrections[q.id]?.correct_answer ? 'color:#3A8C89' : 'color:#E28A53'" x-text="answered[q.id] || 'Non risposta'"></span>
                        </div>
                        <div x-show="answered[q.id] !== corrections[q.id]?.correct_answer" style="font-size:0.75rem; color:#3A8C89; margin-top:2px;">
                            Risposta corretta: <span style="font-weight:600;" x-text="corrections[q.id]?.correct_answer"></span>
                        </div>
                        <div x-show="corrections[q.id]?.explanation" style="font-size:0.75rem; color:#4A5252; margin-top:6px; padding:8px 10px; background:#E8F5F5; border-left:3px solid #55B1AE; border-radius:0 6px 6px 0; line-height:1.5;">
                            💡 <span x-text="corrections[q.id]?.explanation"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div style="display:flex; gap:12px; justify-content:center;">
            @if($course)
            <a href="/learn/course/{{ $course->slug }}"
               style="padding:10px 24px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                ← Torna al corso
            </a>
            @endif
            <button @click="restartQuiz()" x-show="!passed"
                    style="padding:10px 24px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">
                Riprova
            </button>
            @if($course && $nextModule)
            <a href="/learn/course/{{ $course->slug }}/module/{{ $nextModule->id }}"
               x-show="passed"
               style="padding:10px 24px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
                Modulo successivo →
            </a>
            @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
function quizApp() {
    return {
        phase: 'intro',
        // Le domande (id/question/options, MAI correct_answer) arrivano da start():
        // è il sottoinsieme estratto per QUESTO tentativo. Le correzioni solo post-submit.
        questions: [],
        current: 0,
        answered: {},        // { qid: opzione_scelta }
        corrections: {},     // { qid: { correct_answer, explanation } } popolato post-submit
        score: 0,
        correctCount: 0,
        passed: false,
        submitting: false,
        timeLeft: {{ $quiz->time_limit_minutes ? $quiz->time_limit_minutes * 60 : 0 }},
        timer: null,
        attemptId: null,

        init() {},

        async startQuiz() {
            try {
                const res = await fetch('/learn/quiz/{{ $quiz->id }}/start', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({})
                });
                const data = await res.json();

                if (!res.ok) {
                    if (data && data.already_passed) {
                        alert('Hai già superato questo esame.');
                    } else if (data && data.attempts_exhausted) {
                        alert(data.error || 'Tentativi esauriti.');
                    } else {
                        alert(data?.error || 'Impossibile avviare il quiz.');
                    }
                    window.location.reload();
                    return;
                }

                this.attemptId = data.attempt_id;
                this.questions = data.questions || [];   // sottoinsieme estratto per il tentativo
            } catch(e) {}

            if (!this.questions.length) {
                alert('Nessuna domanda disponibile per questo quiz.');
                return;
            }

            this.phase = 'quiz';

            // Nessun abbandono automatico su beforeunload/visibilitychange: quegli
            // eventi scattano anche su un semplice REFRESH e ucciderebbero il tentativo,
            // contraddicendo "refresh = riprendi" (start() ritrova il tentativo aperto
            // con le stesse K). L'abbandono "silenzioso" (chiude il tab / se ne va) è
            // chiuso dal reaper schedulato `exams:fail-stale` (ogni 5 min, oltre il
            // time_limit o il cap di default). L'endpoint abandon() resta per un'uscita
            // ESPLICITA (es. futuro bottone "Esci dal quiz"), non cablato su unload.

            @if($quiz->time_limit_minutes)
            this.timer = setInterval(() => {
                this.timeLeft--;
                if (this.timeLeft <= 0) {
                    clearInterval(this.timer);
                    this.submitQuiz();
                }
            }, 1000);
            @endif
        },

        pendingAnswer: null,

        proposeAnswer(qid, opt) {
            if (this.answered[qid] !== undefined) return;
            this.pendingAnswer = { qid, opt };
        },

        cancelAnswer() {
            this.pendingAnswer = null;
        },

        confirmAnswer() {
            if (!this.pendingAnswer) return;
            const { qid, opt } = this.pendingAnswer;
            this.answered[qid] = opt;
            this.answered = { ...this.answered };
            this.pendingAnswer = null;
        },

        // Pre-submit: tutte le opzioni neutre, solo quella selezionata evidenziata.
        // Post-submit: lookup su corrections per mostrare verde/rosso.
        getOptionStyle(qid, opt) {
            const picked = this.answered[qid];
            const correct = this.corrections[qid]?.correct_answer;

            if (this.phase === 'result' && correct !== undefined) {
                if (opt === correct) {
                    return 'background:#E8F5F5; border:2px solid #55B1AE; color:#3A8C89;';
                }
                if (picked === opt) {
                    return 'background:#fff3ec; border:2px solid #E28A53; color:#c97a45;';
                }
                return 'background:#F5F7F7; border:2px solid transparent; color:#8A9696;';
            }

            if (picked === opt) {
                return 'background:#E8F5F5; border:2px solid #55B1AE; color:#1A1F1F;';
            }
            return 'background:#F5F7F7; border:2px solid transparent; color:#1A1F1F;';
        },

        getLetterStyle(qid, opt) {
            const picked = this.answered[qid];
            const correct = this.corrections[qid]?.correct_answer;

            if (this.phase === 'result' && correct !== undefined) {
                if (opt === correct) {
                    return 'background:#55B1AE; color:white;';
                }
                if (picked === opt) {
                    return 'background:#E28A53; color:white;';
                }
                return 'background:#C8D0D0; color:white;';
            }

            if (picked === opt) {
                return 'background:#55B1AE; color:white;';
            }
            return 'background:#C8D0D0; color:white;';
        },

        next() {
            if (this.current < this.questions.length - 1) this.current++;
        },

        prev() {
            if (this.current > 0) this.current--;
        },

        async submitQuiz() {
            if (this.submitting) return;
            if (this.timer) clearInterval(this.timer);
            this.submitting = true;

            try {
                const res = await fetch('/learn/quiz/{{ $quiz->id }}/submit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        attempt_id: this.attemptId,
                        answers: this.answered,
                    })
                });

                if (res.status === 409) {
                    alert('Questo tentativo risulta già consegnato.');
                    window.location.reload();
                    return;
                }

                if (!res.ok) {
                    alert('Errore durante il salvataggio del quiz. Riprova.');
                    this.submitting = false;
                    return;
                }

                const data = await res.json();
                this.score = data.score ?? 0;
                this.passed = !!data.passed;
                this.corrections = data.corrections ?? {};
                this.correctCount = this.questions.filter(
                    q => this.answered[q.id] === this.corrections[q.id]?.correct_answer
                ).length;
                // Submit andato a buon fine: neutralizza il beacon (il tentativo
                // è già consegnato, non va richiuso come abbandonato).
                this.attemptId = null;
                this.phase = 'result';
            } catch(e) {
                alert('Errore di rete. Riprova.');
                this.submitting = false;
            }
        },

        restartQuiz() {
            this.current = 0;
            this.answered = {};
            this.corrections = {};
            this.score = 0;
            this.correctCount = 0;
            this.passed = false;
            this.submitting = false;
            this.timeLeft = {{ $quiz->time_limit_minutes ? $quiz->time_limit_minutes * 60 : 0 }};
            this.phase = 'intro';
        },

        formatTime(seconds) {
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            return m + ':' + s;
        }
    }
}
</script>
@endpush
@endsection
