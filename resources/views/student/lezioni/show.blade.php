@extends('layouts.student')
@section('title', $lesson->title)
@section('breadcrumb', 'Classi / ' . $class->name . ' / ' . $lesson->title)
@section('content')
<div style="max-width:900px;" x-data="lessonPage()">
    <div style="margin-bottom:8px;"><a href="{{ route('student.classes.show', $class) }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; {{ $class->name }}</a></div>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div>
            <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">{{ $lesson->title }}</h1>
            <p style="color:#8A9696; font-size:0.875rem; margin:2px 0 0;">{{ $lesson->topic->name ?? '' }}</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            @if($hasPresentation)
                <a href="{{ route('student.classes.lesson.presentation', [$class, $lesson]) }}" style="padding:9px 16px; background:#3A8C89; color:white; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none;">&#11015; Presentazione (.pptx)</a>
            @endif
            <button @click="minervaOpen = !minervaOpen" style="padding:9px 16px; background:#1A1F1F; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">&#9788; Chiedi a Minerva</button>
        </div>
    </div>

    {{-- P21 — slide della presentazione PUBBLICATA: visualizzatore inline (lightbox),
         come nel flusso corsi. Il download .pptx resta nel pulsante in alto. --}}
    @if($hasPresentation && ($presentationSlides ?? 0) > 0)
        @php $presUrls = array_map(fn ($i) => route('student.classes.lesson.presentation.slide', [$class, $lesson, $i]), range(1, $presentationSlides)); @endphp
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Slide della lezione</div>
            <x-slide-lightbox :images="$presUrls" />
        </div>
    @endif

    {{-- V4 — video narrato della lezione (player); coesiste con le slide (download sopra).
         R4 — ricerca per-video: clic su un risultato → seek al punto. --}}
    @if(!empty($hasVideo))
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;"
             x-data="videoSearch('{{ route('student.classes.lesson.video.search', [$class, $lesson]) }}')">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">🎬 Video della lezione</div>
            <video x-ref="player" controls preload="metadata" style="width:100%; max-width:880px; border-radius:8px; background:#0A0A0A; aspect-ratio:16/9;"
                   src="{{ route('student.classes.lesson.video', [$class, $lesson]) }}"></video>

            <form @submit.prevent="run()" style="margin-top:12px; display:flex; gap:6px; max-width:880px;">
                <input x-model="q" type="text" placeholder="Cerca in questo video (parlato e schermo)…"
                       style="flex:1; padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
                <button style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Cerca</button>
            </form>
            <p x-show="loading" style="margin-top:8px; font-size:0.82rem; color:#8A9696;">Ricerca…</p>
            <p x-show="done && !results.length" style="margin-top:8px; font-size:0.82rem; color:#8A9696;">Nessun riscontro in questo video.</p>
            <ul x-show="results.length" style="margin-top:8px; list-style:none; padding:0; max-width:880px; display:flex; flex-direction:column; gap:4px;">
                <template x-for="m in results" :key="m.start + '_' + m.text.slice(0,12)">
                    <li @click="seek(m.start)" style="cursor:pointer; padding:7px 10px; background:#F4F6F6; border-radius:7px; font-size:0.82rem; color:#4A5252;">
                        <span style="font-weight:700; color:#3A8C89;" x-text="fmt(m.start)"></span>
                        <span style="font-size:0.68rem; color:#8A9696;" x-text="m.type === 'frame' ? ' schermo' : ' parlato'"></span>
                        — <span x-text="m.text"></span>
                    </li>
                </template>
            </ul>
        </div>
        <script>
            function videoSearch(url) {
                return {
                    url, q: '', results: [], loading: false, done: false,
                    async run() {
                        if (!this.q.trim()) return;
                        this.loading = true; this.done = false; this.results = [];
                        try {
                            const r = await fetch(this.url, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                body: JSON.stringify({ q: this.q }),
                            });
                            const j = await r.json();
                            this.results = j.matches || [];
                        } catch (e) { this.results = []; }
                        this.loading = false; this.done = true;
                    },
                    seek(s) { this.$refs.player.currentTime = s; this.$refs.player.play(); this.$refs.player.scrollIntoView({ behavior: 'smooth', block: 'center' }); },
                    fmt(s) { const m = Math.floor(s / 60), sec = Math.floor(s % 60); return m + ':' + String(sec).padStart(2, '0'); },
                };
            }
        </script>
    @endif

    {{-- Materiali audio/video della lezione (ricerca video / player con seek) --}}
    @if($mediaMaterials->isNotEmpty())
    <div style="margin-top:16px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">Video e audio della lezione</div>
        @foreach($mediaMaterials as $m)
            <div style="margin-bottom:10px;">
                <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">{{ $m->title }}</div>
                @if($m->source_type === 'audio')
                    <audio controls preload="none" data-media-id="{{ $m->id }}" style="width:100%;"
                           src="{{ route('student.classes.lesson.material.source', [$class, $lesson, $m]) }}"></audio>
                @elseif($m->source_type === 'youtube')
                    <a href="{{ $m->source_url }}" target="_blank" rel="noopener" style="color:#3A8C89; font-size:0.85rem;">&#9654; Apri il video su YouTube</a>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    {{-- Corpo della lezione con appunti per paragrafo --}}
    <div class="lesson-card">
        <div class="lesson-body">{!! $bodyHtml !!}</div>
    </div>

    {{-- Auto-generazione studente: quiz di autoverifica DALLA lezione (P20c) --}}
    @if($publication->students_can_generate)
    <div style="margin-top:16px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Mettiti alla prova</div>
        <p style="font-size:0.8rem; color:#8A9696; margin:0 0 4px;">Genera per te un quiz di autoverifica da questa lezione. Restano {{ $usage['remaining'] }} generazioni oggi.</p>
        <p style="font-size:0.74rem; color:#8A9696; margin:0 0 12px;">&#128274; È <strong>generato da te</strong>, non dal docente: resta privato, visibile solo a te.</p>

        @if($usage['allowed'])
        <form method="POST" action="{{ route('student.classes.lesson.generate', [$class, $lesson]) }}" data-async style="display:flex; gap:6px; align-items:center;">
            @csrf
            <input type="hidden" name="type" value="quiz">
            <input type="number" name="num_questions" min="3" max="15" value="8" style="width:64px; padding:8px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem;">
            <button data-busy-label="Genero…" style="padding:9px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">&#10067; Quiz di autoverifica</button>
        </form>
        @else
        <p style="font-size:0.82rem; color:#A8521F;">Hai raggiunto il limite di generazioni per oggi. Riprova domani &#128578;</p>
        @endif

        @if($generated->count())
        <div style="margin-top:16px;">
            <div style="font-size:0.78rem; font-weight:700; color:#8A9696; margin-bottom:6px;">I tuoi quiz (privati)</div>
            @foreach($generated as $g)
                <div x-data="lessonGenRow('{{ $g->id }}', '{{ $g->status }}', @js($g->quiz_id))"
                     style="display:flex; align-items:center; justify-content:space-between; padding:9px 12px; border:1px solid #E5E7E7; border-radius:8px; margin-bottom:6px;">
                    <span style="font-size:0.85rem; color:#1A1F1F;">&#10067; Quiz di autoverifica</span>
                    <span style="font-size:0.78rem;">
                        <span x-show="status==='generating'" style="color:#E28A53; font-weight:600;">generazione in corso…</span>
                        <template x-if="status==='ready' && quizId">
                            <a :href="'/learn/quiz/' + quizId" style="color:#3A8C89; font-weight:600; text-decoration:none;">Svolgi &rarr;</a>
                        </template>
                        <span x-show="status==='failed'" style="color:#A8521F; font-weight:600;">non riuscita</span>
                    </span>
                </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- Editor appunto per paragrafo (popover) --}}
    <div x-show="noteEditor.open" x-cloak @click.self="closeNote()"
         style="position:fixed; inset:0; background:rgba(0,0,0,0.25); display:flex; align-items:center; justify-content:center; z-index:50;">
        <div style="background:white; border-radius:12px; padding:18px; width:min(520px,92vw); box-shadow:0 12px 40px rgba(0,0,0,0.2);">
            <div style="font-size:0.8rem; font-weight:700; color:#4A5252; margin-bottom:8px;">Il tuo appunto</div>
            <textarea x-model="noteEditor.content" rows="5" placeholder="Scrivi qui i tuoi appunti su questo paragrafo…"
                      style="width:100%; padding:10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem; font-family:inherit;"></textarea>
            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:10px;">
                <button @click="closeNote()" style="padding:8px 14px; background:#F0F2F2; color:#4A5252; border:none; border-radius:6px; font-size:0.82rem; cursor:pointer;">Annulla</button>
                <button @click="saveNote()" style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.82rem; font-weight:600; cursor:pointer;">Salva appunto</button>
            </div>
        </div>
    </div>

    {{-- Minerva di lezione (gate §5) --}}
    <div x-show="minervaOpen" x-cloak
         style="position:fixed; right:0; top:0; bottom:0; width:min(420px,100vw); background:#F5F7F7; border-left:1px solid #C8D0D0; box-shadow:-8px 0 24px rgba(0,0,0,0.08); z-index:40; display:flex; flex-direction:column;">
        <div style="background:#1A1F1F; color:#E8EDED; padding:12px 16px; display:flex; align-items:center; gap:10px;">
            <strong>Minerva</strong>
            <span style="color:#8A9696; font-size:0.8rem; flex:1;">{{ $lesson->title }}</span>
            <button @click="minervaOpen=false" style="background:none; border:none; color:#8A9696; font-size:1.2rem; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:10px 16px; font-size:0.75rem; color:#8A9696;">Minerva risponde <strong>solo</strong> sui materiali di questa classe.</div>
        <div style="flex:1; overflow-y:auto; padding:0 16px;">
            <template x-for="(m,i) in messages" :key="i">
                <div style="margin-bottom:12px;" :style="m.role==='user' ? 'text-align:right' : ''">
                    <div :style="m.role==='user' ? 'background:#55B1AE;color:white' : 'background:white;border:1px solid #C8D0D0'"
                         style="display:inline-block; max-width:90%; padding:10px 12px; border-radius:12px; font-size:0.88rem; line-height:1.5; white-space:pre-wrap; text-align:left;" x-text="m.content"></div>
                    <template x-if="m.role==='assistant' && m.sources && m.sources.length">
                        <div style="margin-top:5px; font-size:0.74rem; color:#4A5252;">Fonti:
                            <template x-for="(s,j) in m.sources" :key="j">
                                <span style="display:inline-block; margin:2px 5px 2px 0; padding:2px 8px; background:#E8F5F5; border:1px solid #C8E0E0; border-radius:10px; color:#3A8C89; cursor:pointer;"
                                      @click="onSource(s)" x-text="s.title + (s.timestamp ? ' ['+s.timestamp+']' : '')"></span>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="loading"><div style="color:#E28A53; font-size:0.85rem;">Minerva sta cercando nei materiali…</div></template>
        </div>
        <div style="padding:12px 16px; border-top:1px solid #C8D0D0; background:white; display:flex; gap:8px;">
            <textarea x-model="question" rows="1" placeholder="Fai una domanda sulla lezione…" @keydown.enter.prevent="ask()"
                      style="flex:1; resize:none; padding:9px 11px; border:1px solid #C8D0D0; border-radius:9px; font-size:0.88rem; font-family:inherit;"></textarea>
            <button @click="ask()" :disabled="loading || !question.trim()" style="padding:0 16px; background:#55B1AE; color:white; border:none; border-radius:9px; font-weight:600; cursor:pointer;">Invia</button>
        </div>
    </div>
</div>

@push('styles')<style>[x-cloak]{display:none!important}</style>@endpush
@include('schola.partials.lesson-typography')
@include('schola.partials.lesson-katex')
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
// Feedback UX: form[data-async] → disabilita + spinner + anti doppio submit.
document.addEventListener('submit', function (e) {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || !f.hasAttribute('data-async')) return;
    if (f.dataset.submitting === '1') { e.preventDefault(); return; }
    f.dataset.submitting = '1';
    const b = f.querySelector('button[type="submit"], button:not([type])');
    if (b) { const l=b.getAttribute('data-busy-label')||'Attendere…'; b.dataset.html=b.innerHTML; b.textContent=l; setTimeout(()=>b.disabled=true,0); }
}, true);

// Polling di una generazione studente DA LEZIONE (privata).
function lessonGenRow(id, status, quizId) {
    return {
        status, quizId,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const url = `/learn/classi/{{ $class->id }}/lezioni/{{ $lesson->id }}/generati/${id}/stato`;
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status; this.quizId = d.quiz_id;
                    if (d.status === 'ready' || d.status === 'failed') { clearInterval(timer); if (d.status==='ready') window.location.reload(); }
                } catch(e) {}
            }, 4000);
        },
    };
}

function lessonPage() {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}';
    const notesUrl = '{{ route('student.classes.lesson.notes.list', [$class, $lesson]) }}';
    const saveUrl = '{{ route('student.classes.lesson.notes.save', [$class, $lesson]) }}';
    const askUrl = '{{ route('student.minerva.ask') }}';
    return {
        minervaOpen: false,
        messages: [],
        question: '',
        loading: false,
        notes: {},
        teacherNotes: @json($teacherNotes->map->content),
        noteEditor: {open: false, anchor: null, content: ''},
        init() {
            this.loadNotes();
        },
        async loadNotes() {
            try {
                const r = await fetch(notesUrl, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                const d = await r.json();
                d.notes.forEach(n => { if (n.anchor) this.notes[n.anchor] = n.content; });
            } catch(e) {}
            this.decorate();
        },
        decorate() {
            document.querySelectorAll('.lesson-body [data-note-anchor]').forEach(el => {
                const anchor = el.getAttribute('data-note-anchor');
                if (!el.querySelector('.note-tab')) {
                    const btn = document.createElement('button');
                    btn.className = 'note-tab';
                    btn.innerHTML = '&#9998;';
                    btn.title = 'Aggiungi una nota';
                    btn.setAttribute('aria-label', 'Aggiungi una nota a questo paragrafo');
                    btn.addEventListener('click', () => this.openNote(anchor));
                    el.prepend(btn);
                }
                this.renderNote(el, anchor);
            });
        },
        renderNote(el, anchor) {
            const tab = el.querySelector('.note-tab');
            el.querySelectorAll('.note-inline, .note-teacher').forEach(n => n.remove());
            // Nota del docente (didattica, visibile a tutti): sempre mostrata.
            if (this.teacherNotes[anchor]) {
                const t = document.createElement('div');
                t.className = 'note-teacher';
                t.innerHTML = '<span class="note-teacher-label">&#128221; Nota del docente</span>';
                const body = document.createElement('div');
                body.textContent = this.teacherNotes[anchor];
                t.appendChild(body);
                el.appendChild(t);
            }
            if (this.notes[anchor]) {
                if (tab) tab.classList.add('has-note');
                const div = document.createElement('div');
                div.className = 'note-inline';
                div.textContent = this.notes[anchor];
                el.appendChild(div);
            } else if (tab) {
                tab.classList.remove('has-note');
            }
        },
        openNote(anchor) {
            this.noteEditor = {open: true, anchor, content: this.notes[anchor] || ''};
        },
        closeNote() { this.noteEditor.open = false; },
        async saveNote() {
            const anchor = this.noteEditor.anchor;
            const content = this.noteEditor.content;
            try {
                await fetch(saveUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({anchor, content}),
                });
                if (content.trim() === '') delete this.notes[anchor]; else this.notes[anchor] = content;
            } catch(e) {}
            this.closeNote();
            const el = document.querySelector(`.lesson-body [data-note-anchor="${anchor}"]`);
            if (el) this.renderNote(el, anchor);
        },
        async ask() {
            const q = this.question.trim();
            if (!q || this.loading) return;
            this.messages.push({role:'user', content:q, sources:[]});
            this.question = '';
            this.loading = true;
            try {
                const r = await fetch(askUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({question:q, school_class_id:'{{ $class->id }}', lesson_id:'{{ $lesson->id }}'}),
                });
                const d = await r.json();
                this.messages.push({role:'assistant', content:d.answer || 'Risposta non disponibile.', sources:d.sources || []});
            } catch(e) {
                this.messages.push({role:'assistant', content:'Errore di rete. Riprova.', sources:[]});
            } finally {
                this.loading = false;
            }
        },
        // Citazione cliccata: youtube → apri url; audio → salta al minutaggio nel player.
        onSource(s) {
            if (s.url) { window.open(s.url, '_blank', 'noopener'); return; }
            if (s.seconds != null) {
                const player = document.querySelector('audio[data-media-id]');
                if (player) { player.currentTime = s.seconds; player.play().catch(()=>{}); }
            }
        },
    };
}
</script>
@endpush
@endsection
