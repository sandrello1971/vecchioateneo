@extends('layouts.docente')
@section('title', $lesson->title)
@section('breadcrumb', 'Argomenti / ' . ($lesson->topic->name ?? '') . ' / ' . $lesson->title)
@section('content')
@php
    $readyMaterials = $materials->where('status', 'ready')->filter(fn($m) => trim((string)$m->extracted_text) !== '');
    $canCompose = $readyMaterials->isNotEmpty();
    $meta = (array) $lesson->generation_meta;
    $artifactLabels = [
        'summary' => 'Riassunto', 'mindmap' => 'Mappa mentale', 'conceptmap' => 'Mappa concettuale',
        'quiz' => 'Quiz', 'outline' => 'Scaletta',
    ];
@endphp
<div style="max-width:980px;" x-data="lessonStatus('{{ $lesson->id }}', '{{ $lesson->generation_status }}')">
    <div style="margin-bottom:8px;">
        <a href="{{ route('docente.topics.show', $lesson->topic_id) }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; {{ $lesson->topic->name ?? 'Argomento' }}</a>
    </div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $lesson->title }}</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">{{ $lesson->topic->subject->name ?? '' }}</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Materiali sorgente --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Materiali della lezione ({{ $materials->count() }})</div>
        @forelse($materials as $m)
            @php $mb = ['pending'=>['#8A9696','in coda'],'processing'=>['#E28A53','in elaborazione'],'ready'=>['#3A8C89','pronto'],'failed'=>['#A8521F','fallito']]; [$c,$l]=$mb[$m->status]??['#8A9696',$m->status]; @endphp
            <div style="display:flex; align-items:center; gap:8px; padding:6px 0; border-top:1px solid #F0F2F2; font-size:0.82rem;">
                <span style="color:#3A8C89;">&#128196;</span>
                <a href="{{ route('docente.materials.show', $m) }}" style="flex:1; color:#1A1F1F; text-decoration:none;">{{ $m->title }} <span style="color:#8A9696;">· {{ $m->source_type }}</span></a>
                <span style="font-size:0.7rem; font-weight:700; color:{{ $c }}; border:1px solid {{ $c }}; border-radius:4px; padding:1px 8px;">{{ $l }}</span>
            </div>
        @empty
            <p style="color:#8A9696; font-size:0.85rem;">Nessun materiale assegnato. Vai all'<a href="{{ route('docente.topics.show', $lesson->topic_id) }}" style="color:#55B1AE;">argomento</a> per classificarne.</p>
        @endforelse
    </div>

    {{-- Stato composizione (polling) --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; flex:1;">Corpo della lezione</div>
            <template x-if="status==='generating'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.85rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite;"></span>
                    <span>Composizione in corso…</span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.85rem;">&#10003; Pronta</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.85rem;">&#10007; Composizione fallita</span></template>
            <template x-if="status==='draft'"><span style="color:#8A9696; font-weight:600; font-size:0.85rem;">Bozza</span></template>
        </div>

        @if($lesson->generation_status === 'failed' && ($meta['failure_reason'] ?? null))
            <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $meta['failure_reason'] }}</p>
        @endif

        @if($lesson->generation_status === 'ready' && !empty($meta))
            <div style="margin-top:8px; font-size:0.75rem; color:#8A9696;">
                @isset($meta['model']) modello: {{ $meta['model'] }} @endisset
                @isset($meta['tokens_in']) · token in/out: {{ $meta['tokens_in'] }}/{{ $meta['tokens_out'] ?? 0 }} @endisset
                @isset($meta['sources_count']) · fonti: {{ $meta['sources_count'] }} @endisset
                @if($meta['segments_preserved'] ?? false) · <span title="riferimenti temporali audio/video conservati">timestamp conservati</span> @endif
            </div>
        @endif

        {{-- Azioni di composizione (Feedback UX: data-async, anti-doppio-submit) --}}
        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;" x-show="status!=='generating'">
            @if($lesson->generation_status === 'draft' || $lesson->generation_status === 'failed')
                <form method="POST" action="{{ route('docente.lessons.generate', $lesson) }}" data-async>
                    @csrf
                    <button @disabled(!$canCompose) data-busy-label="Composizione in corso…"
                        style="padding:9px 16px; background:{{ $canCompose ? '#55B1AE' : '#C8D0D0' }}; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:{{ $canCompose ? 'pointer' : 'not-allowed' }};">
                        {{ $lesson->generation_status === 'failed' ? 'Riprova composizione' : 'Componi lezione' }}
                    </button>
                </form>
                @unless($canCompose)<span style="font-size:0.78rem; color:#8A9696; align-self:center;">Serve almeno un materiale pronto con testo.</span>@endunless
            @elseif($lesson->generation_status === 'ready')
                <form method="POST" action="{{ route('docente.lessons.regenerate', $lesson) }}" data-async
                      onsubmit="return confirm('Ricomporre la lezione? Il contenuto attuale (comprese le modifiche manuali) verrà sovrascritto.');">
                    @csrf
                    <button data-busy-label="Ricomposizione…" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Ricomponi (sovrascrive)</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Editor + anteprima del corpo (sempre modificabile dopo la generazione) --}}
    @if($lesson->generation_status === 'ready' || !empty($lesson->content))
        <div x-data="{tab:'edit'}" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <button type="button" @click="tab='edit'" :style="tab==='edit' ? 'background:#1A1F1F;color:white' : 'background:#F0F2F2;color:#4A5252'" style="padding:6px 14px; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Modifica</button>
                <button type="button" @click="tab='preview'" :style="tab==='preview' ? 'background:#1A1F1F;color:white' : 'background:#F0F2F2;color:#4A5252'" style="padding:6px 14px; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Anteprima</button>
            </div>

            <div x-show="tab==='edit'">
                <form method="POST" action="{{ route('docente.lessons.content', $lesson) }}">
                    @csrf @method('PATCH')
                    <textarea name="content" rows="22" style="width:100%; padding:12px; border:1px solid #C8D0D0; border-radius:8px; font-family:ui-monospace,monospace; font-size:0.82rem; line-height:1.5; color:#1A1F1F;">{{ $lesson->content }}</textarea>
                    <div style="margin-top:10px;"><button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Salva modifiche</button></div>
                </form>
            </div>

            <div x-show="tab==='preview'" style="display:none;" x-data="docenteLessonNotes()">
                <p style="font-size:0.78rem; color:#8A9696; margin:0 0 10px;">Passa il mouse su un paragrafo e clicca &#9998; per aggiungere una <strong>nota del docente</strong>: la vedranno tutti gli studenti della classe.</p>
                {{-- Stesso layout/rendering della vista studente (partials condivisi). --}}
                <div class="lesson-card"><div class="lesson-body">{!! $bodyHtml !!}</div></div>
            </div>
        </div>

        {{-- Artefatti a livello di lezione --}}
        @if($lesson->generation_status === 'ready')
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Genera dalla lezione</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @foreach(['summary'=>'Riassunto','outline'=>'Scaletta','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale'] as $t=>$lab)
                    <form method="POST" action="{{ route('docente.lessons.artifacts.generate', $lesson) }}" data-async>
                        @csrf<input type="hidden" name="type" value="{{ $t }}">
                        <button data-busy-label="Genero…" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">{{ $lab }}</button>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('docente.lessons.artifacts.generate', $lesson) }}" data-async style="display:flex; gap:6px; align-items:center;">
                    @csrf<input type="hidden" name="type" value="quiz">
                    <input type="number" name="num_questions" value="10" min="3" max="20" style="width:60px; padding:7px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                    <button data-busy-label="Genero…" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">Quiz</button>
                </form>
            </div>

            @if($artifacts->isNotEmpty())
            <div style="margin-top:12px;">
                @foreach($artifacts as $a)
                    <div x-data="artifactRow('{{ $a->id }}','{{ $a->status }}')" style="display:flex; align-items:center; gap:8px; padding:7px 0; border-top:1px solid #F0F2F2; font-size:0.82rem;">
                        <span style="flex:1;"><a href="{{ route('docente.artifacts.show', $a) }}" style="color:#1A1F1F; text-decoration:none;">{{ $artifactLabels[$a->type] ?? $a->type }} — {{ $a->title }}</a></span>
                        <template x-if="status==='generating'"><span style="color:#E28A53; font-size:0.75rem; font-weight:600;">in corso…</span></template>
                        <template x-if="status==='ready'"><span style="color:#3A8C89; font-size:0.75rem; font-weight:700;">&#10003;</span></template>
                        <template x-if="status==='failed'"><span style="color:#A8521F; font-size:0.75rem; font-weight:700;">&#10007;</span></template>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif
    @endif

    {{-- Presentazione .pptx (P21) — Feedback UX: stato + polling, anti-doppio-submit --}}
    @if($lesson->generation_status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;"
         x-data="presentationStatus('{{ $presentation?->status ?? 'none' }}')">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; flex:1;">Presentazione (.pptx)</div>
            <template x-if="status==='generating'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.85rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite;"></span>
                    <span>Generazione in corso…</span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.85rem;">&#10003; Pronta</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.85rem;">&#10007; Generazione fallita</span></template>
        </div>

        @if(($presentation?->status ?? null) === 'failed' && ($presentation->generation_meta['failure_reason'] ?? null))
            <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $presentation->generation_meta['failure_reason'] }}</p>
        @endif
        @if(($presentation?->status ?? null) === 'ready' && ($presentation->generation_meta['slides'] ?? null))
            <div style="margin-top:6px; font-size:0.75rem; color:#8A9696;">{{ $presentation->generation_meta['slides'] }} slide @isset($presentation->generation_meta['model']) · {{ $presentation->generation_meta['model'] }} @endisset</div>
        @endif

        {{-- x-show sul wrapper esterno: NON deve stare sul contenitore flex, perché
             Alpine (x-show) rimuove la proprietà `display` inline quando mostra,
             azzerando `display:flex` → i bottoni perdono il gap e si sovrappongono. --}}
        <div style="margin-top:12px;" x-show="status!=='generating'">
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            @if(!$presentation || $presentation->status === 'pending' || $presentation->status === 'failed')
                <form method="POST" action="{{ route('docente.lessons.presentation.generate', $lesson) }}" data-async>
                    @csrf
                    <button data-busy-label="Generazione…" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">{{ ($presentation?->status ?? null) === 'failed' ? 'Riprova' : 'Genera presentazione' }}</button>
                </form>
            @elseif($presentation->status === 'ready')
                <a href="{{ route('docente.lessons.presentation.download', $lesson) }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 16px; background:#3A8C89; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11015; Scarica .pptx</a>
                <form method="POST" action="{{ route('docente.lessons.presentation.regenerate', $lesson) }}" data-async
                      onsubmit="return confirm('Rigenerare la presentazione? Il file attuale verrà sovrascritto.');">
                    @csrf
                    <button data-busy-label="Rigenerazione…" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Rigenera</button>
                </form>
            @endif
        </div>
        </div>
    </div>
    @endif

    {{-- Pubblicazione su classi (P20a) — Feedback UX: rag_status + polling --}}
    @if($lesson->generation_status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;" x-data="lessonPublications('{{ $lesson->id }}')">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Pubblica agli studenti</div>

        @if($teacherClasses->isEmpty())
            <p style="color:#8A9696; font-size:0.85rem;">Nessuna classe disponibile: ti serve una cattedra (o una classe libera) per pubblicare.</p>
        @else
        <form method="POST" action="{{ route('docente.lessons.publish', $lesson) }}" data-async>
            @csrf
            <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:10px;">
                @foreach($teacherClasses as $class)
                    @php $pub = $lesson->publications->firstWhere('school_class_id', $class->id); @endphp
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; color:#1A1F1F;">
                        <input type="checkbox" name="class_ids[]" value="{{ $class->id }}" @checked($pub)>
                        <span style="flex:1;">{{ $class->name }} @if($class->school_id)<span style="color:#8A9696; font-size:0.75rem;">· scuola</span>@else<span style="color:#8A9696; font-size:0.75rem;">· libera</span>@endif</span>
                        @if($pub)
                            @php $rs = ['pending'=>['#8A9696','in coda'],'indexing'=>['#E28A53','indicizzazione…'],'ready'=>['#3A8C89','pubblicata'],'failed'=>['#A8521F','fallita']]; [$c,$l]=$rs[$pub->rag_status]??['#8A9696',$pub->rag_status]; @endphp
                            <span data-pub-class="{{ $class->id }}" style="font-size:0.72rem; font-weight:700; color:{{ $c }}; border:1px solid {{ $c }}; border-radius:4px; padding:1px 8px;">{{ $l }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <label style="display:flex; align-items:center; gap:8px; font-size:0.82rem; color:#4A5252; margin-bottom:10px;">
                <input type="checkbox" name="students_can_generate" value="1" checked>
                Gli studenti possono generare quiz/autoverifica dalla lezione
            </label>
            <button data-busy-label="Pubblicazione…" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Pubblica / aggiorna</button>
        </form>

        @if($lesson->publications->isNotEmpty())
        <div style="margin-top:12px; border-top:1px solid #F0F2F2; padding-top:10px;">
            <div style="font-size:0.72rem; color:#8A9696; margin-bottom:6px;">Pubblicazioni attive — il ritiro rimuove i contenuti dalla classe (RAG).</div>
            @foreach($lesson->publications as $pub)
                <div style="display:flex; align-items:center; gap:8px; padding:5px 0; font-size:0.82rem;">
                    <span style="flex:1; color:#1A1F1F;">{{ $pub->schoolClass->name ?? '—' }}</span>
                    <form method="POST" action="{{ route('docente.lesson-publications.destroy', $pub) }}" data-async onsubmit="return confirm('Ritirare la pubblicazione da questa classe? Gli studenti non vedranno più la lezione.');">
                        @csrf @method('DELETE')
                        <button data-busy-label="Ritiro…" style="border:none; background:none; color:#A8521F; cursor:pointer; font-size:0.78rem;">ritira</button>
                    </form>
                </div>
            @endforeach
        </div>
        @endif
        @endif
    </div>
    @endif
</div>

@push('styles')<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>@endpush
{{-- Stessa tipografia + KaTeX della vista studente: l'anteprima è IDENTICA. --}}
@include('schola.partials.lesson-typography')
@include('schola.partials.lesson-katex')
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function lessonStatus(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/lezioni/${id}/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') {
                        clearInterval(timer);
                        window.location.reload();
                    }
                } catch(e) {}
            }, 5000);
        },
    };
}
function artifactRow(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/artefatti/${id}/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') clearInterval(timer);
                } catch(e) {}
            }, 5000);
        },
    };
}
// Note del docente per paragrafo (didattiche, visibili agli studenti).
function docenteLessonNotes() {
    const csrf = '{{ csrf_token() }}';
    const saveUrl = '{{ route('docente.lessons.teacher-notes.save', $lesson) }}';
    const initial = @json($teacherNotes->map->content);
    return {
        notes: initial,
        init() { this.decorate(); },
        decorate() {
            this.$el.querySelectorAll('.lesson-body [data-note-anchor]').forEach(el => {
                const anchor = el.getAttribute('data-note-anchor');
                if (!el.querySelector('.note-tab')) {
                    const btn = document.createElement('button');
                    btn.className = 'note-tab';
                    btn.innerHTML = '&#9998;';
                    btn.title = 'Nota del docente';
                    btn.addEventListener('click', () => this.edit(anchor));
                    el.prepend(btn);
                }
                this.render(el, anchor);
            });
        },
        render(el, anchor) {
            const tab = el.querySelector('.note-tab');
            el.querySelectorAll('.note-teacher').forEach(n => n.remove());
            if (this.notes[anchor]) {
                if (tab) tab.classList.add('has-note');
                const t = document.createElement('div');
                t.className = 'note-teacher';
                t.innerHTML = '<span class="note-teacher-label">&#128221; Nota del docente</span>';
                const b = document.createElement('div'); b.textContent = this.notes[anchor]; t.appendChild(b);
                el.appendChild(t);
            } else if (tab) { tab.classList.remove('has-note'); }
        },
        async edit(anchor) {
            const current = this.notes[anchor] || '';
            const val = window.prompt('Nota del docente per questo paragrafo (vuoto = elimina):', current);
            if (val === null) return;
            try {
                await fetch(saveUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({anchor, content: val}),
                });
                if (val.trim() === '') delete this.notes[anchor]; else this.notes[anchor] = val;
            } catch(e) {}
            const el = this.$el.querySelector(`.lesson-body [data-note-anchor="${anchor}"]`);
            if (el) this.render(el, anchor);
        },
    };
}
// Polling stato presentazione (.pptx) generating→ready/failed.
function presentationStatus(initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch('{{ route('docente.lessons.presentation.status', $lesson) }}', {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') { clearInterval(timer); window.location.reload(); }
                } catch(e) {}
            }, 5000);
        },
    };
}
// Polling stato pubblicazioni (rag_status pending→indexing→ready/failed).
function lessonPublications(lessonId) {
    const LABELS = {pending:['#8A9696','in coda'],indexing:['#E28A53','indicizzazione…'],ready:['#3A8C89','pubblicata'],failed:['#A8521F','fallita']};
    return {
        init() {
            const pending = () => Array.from(this.$el.querySelectorAll('[data-pub-class]'))
                .some(el => !el.textContent.includes('pubblicata') && !el.textContent.includes('fallita'));
            if (!pending()) return;
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/lezioni/${lessonId}/pubblicazioni/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    let allDone = true;
                    d.publications.forEach(p => {
                        const el = this.$el.querySelector(`[data-pub-class="${p.school_class_id}"]`);
                        const [c,l] = LABELS[p.rag_status] || ['#8A9696', p.rag_status];
                        if (el) { el.textContent = l; el.style.color = c; el.style.borderColor = c; }
                        if (p.rag_status !== 'ready' && p.rag_status !== 'failed') allDone = false;
                    });
                    if (allDone) clearInterval(timer);
                } catch(e) {}
            }, 4000);
        },
    };
}
</script>
@endpush
@endsection
