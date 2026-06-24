@extends('layouts.student')
@section('title', $artifact->title)
@section('breadcrumb', 'Classi / ' . $class->name . ' / ' . $artifact->title)
@section('content')
@php
    $typeLabels = [
        'transcript' => 'Trascrizione', 'summary' => 'Riassunto', 'mindmap' => 'Mappa mentale',
        'conceptmap' => 'Mappa concettuale', 'quiz' => 'Quiz', 'outline' => 'Scaletta',
    ];
    $isMarkdown = in_array($artifact->type, ['transcript', 'summary', 'outline'], true);
@endphp
<div style="max-width:980px;" x-data="genPanel('{{ $class->id }}', '{{ $publication->id }}')">
    <div style="margin-bottom:8px;"><a href="{{ route('student.classes.show', $class) }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; {{ $class->name }}</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $artifact->title }}</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">{{ $typeLabels[$artifact->type] ?? $artifact->type }}</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif

    {{-- Player audio (con seek; deep-link ?t=N posiziona la riproduzione) --}}
    @if($hasAudioSource)
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Audio</div>
        <audio id="artifact-audio" controls preload="metadata" style="width:100%;"
               src="{{ route('student.classes.artifact.source', [$class, $publication]) }}"></audio>
    </div>
    @endif

    {{-- Contenuto per tipo --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        @if($isMarkdown)
            <div class="md-body" style="font-size:0.9rem; line-height:1.65; color:#1A1F1F;">{!! schola_markdown($artifact->content) !!}</div>
        @elseif($artifact->type === 'mindmap')
            <div style="position:relative; width:100%; height:600px; border:1px solid #F5F7F7; border-radius:8px; overflow:hidden; background:#FDFEFE;">
                <svg id="artifact-mindmap-svg" style="width:100%; height:100%;"></svg>
            </div>
        @elseif($artifact->type === 'conceptmap')
            <div id="artifact-concept-map" style="background:#FFF; border:1px solid #D1D5DB; border-radius:8px; width:100%; height:70vh; min-height:480px; max-height:780px;"></div>
        @elseif($artifact->type === 'quiz')
            @if($artifact->quiz_id)
                <p style="font-size:0.9rem; color:#1A1F1F; margin-bottom:12px;">Quiz pubblicato dal docente. I tentativi vengono registrati.</p>
                <a href="{{ route('student.quiz.show', $artifact->quiz_id) }}" style="display:inline-block; padding:10px 18px; background:#55B1AE; color:white; border-radius:8px; font-weight:600; text-decoration:none;">Svolgi il quiz &rarr;</a>
            @else
                <p style="color:#8A9696;">Quiz non disponibile.</p>
            @endif
        @endif
    </div>

    {{-- Trascrizione con minutaggio (audio/video) --}}
    @if($segments)
    <details style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:0; margin-bottom:16px;">
        <summary style="padding:14px 18px; cursor:pointer; font-size:0.85rem; font-weight:600; color:#4A5252;">Trascrizione con minutaggio</summary>
        <div style="padding:0 18px 16px; font-size:0.85rem; line-height:1.6;">
            @foreach($segments as $seg)
                @php $s = (int)($seg['start_seconds'] ?? 0); $mm = floor($s/60); $ss = str_pad($s%60,2,'0',STR_PAD_LEFT); @endphp
                <p style="margin:4px 0;">
                    @if($hasAudioSource)
                        <a href="#" @click.prevent="seek({{ $s }})" style="color:#3A8C89; font-weight:600; text-decoration:none;">[{{ $mm }}:{{ $ss }}]</a>
                    @else
                        <span style="color:#8A9696; font-weight:600;">[{{ $mm }}:{{ $ss }}]</span>
                    @endif
                    {{ $seg['text'] ?? '' }}
                </p>
            @endforeach
        </div>
    </details>
    @endif

    {{-- Auto-generazione (se abilitata dal docente) --}}
    @if($publication->students_can_generate)
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Crea per ripassare</div>
        <p style="font-size:0.8rem; color:#8A9696; margin:0 0 12px;">Genera per te una mappa mentale o un quiz di autoverifica da questo materiale. Restano {{ $usage['remaining'] }} generazioni oggi.</p>

        @if($usage['allowed'])
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <form method="POST" action="{{ route('student.classes.artifact.generate', [$class, $publication]) }}" data-async>
                @csrf
                <input type="hidden" name="type" value="mindmap">
                <button data-busy-label="Genero…" style="padding:9px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">🧠 Mappa mentale</button>
            </form>
            <form method="POST" action="{{ route('student.classes.artifact.generate', [$class, $publication]) }}" data-async style="display:flex; gap:6px; align-items:center;">
                @csrf
                <input type="hidden" name="type" value="quiz">
                <input type="number" name="num_questions" min="3" max="15" value="8" style="width:64px; padding:8px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem;">
                <button data-busy-label="Genero…" style="padding:9px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">❓ Quiz autoverifica</button>
            </form>
        </div>
        @else
        <p style="font-size:0.82rem; color:#A8521F;">Hai raggiunto il limite di generazioni per oggi. Riprova domani 🙂</p>
        @endif

        {{-- I miei artefatti generati per questo materiale --}}
        @if($generated->count())
        <div style="margin-top:16px;">
            <div style="font-size:0.78rem; font-weight:700; color:#8A9696; margin-bottom:6px;">I tuoi</div>
            @foreach($generated as $g)
                <div x-data="genRow('{{ $g->id }}', '{{ $g->status }}', '{{ $g->type }}', @js($g->quiz_id))"
                     style="display:flex; align-items:center; justify-content:space-between; padding:9px 12px; border:1px solid #E5E7E7; border-radius:8px; margin-bottom:6px;">
                    <span style="font-size:0.85rem; color:#1A1F1F;">{{ $g->type === 'mindmap' ? '🧠 Mappa mentale' : '❓ Quiz' }}</span>
                    <span style="font-size:0.78rem; display:flex; gap:10px; align-items:center;">
                        <span x-show="status==='generating'" style="color:#E28A53; font-weight:600;">generazione in corso…</span>
                        <template x-if="status==='ready'">
                            <span>
                                <template x-if="type==='quiz' && quizId">
                                    <a :href="'/learn/quiz/' + quizId" style="color:#3A8C89; font-weight:600; text-decoration:none;">Svolgi &rarr;</a>
                                </template>
                                <template x-if="type==='mindmap'">
                                    <a :href="'#mygen-' + id" @click.prevent="$dispatch('show-mindmap', {id})" style="color:#3A8C89; font-weight:600; text-decoration:none;">Apri</a>
                                </template>
                            </span>
                        </template>
                        <span x-show="status==='failed'" style="color:#A8521F; font-weight:600;">non riuscita</span>
                    </span>
                </div>
                @if($g->type === 'mindmap' && $g->status === 'ready' && $g->content)
                <div id="mygen-{{ $g->id }}" style="display:none; margin:6px 0 10px;">
                    <div style="position:relative; width:100%; height:480px; border:1px solid #F5F7F7; border-radius:8px; overflow:hidden; background:#FDFEFE;">
                        <svg class="mygen-svg" data-md="{{ base64_encode($g->content) }}" style="width:100%; height:100%;"></svg>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
        @endif
    </div>
    @endif
</div>

@push('styles')
<style>
.md-body h1{font-size:1.3rem;font-weight:700;margin:.6em 0 .3em}
.md-body h2{font-size:1.1rem;font-weight:700;margin:.6em 0 .3em;color:#3A8C89}
.md-body h3{font-size:1rem;font-weight:600;margin:.5em 0 .25em}
.md-body ul,.md-body ol{margin:.3em 0 .6em 1.2em}.md-body li{margin:.15em 0}
.md-body p{margin:.4em 0}.md-body strong{color:#1A1F1F}
.md-body code{background:#F5F7F7;padding:1px 5px;border-radius:4px;font-size:.85em}
@keyframes nosc-spin { to { transform: rotate(360deg); } }
.nosc-spin{display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:nosc-spin .7s linear infinite;vertical-align:-2px;margin-right:6px}
button[disabled]{opacity:.65;cursor:progress}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
// Feedback UX globale: form[data-async] → disabilita + spinner + anti doppio submit.
document.addEventListener('submit', function (e) {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || !f.hasAttribute('data-async')) return;
    if (f.dataset.submitting === '1') { e.preventDefault(); return; }
    f.dataset.submitting = '1';
    const b = f.querySelector('button[type="submit"], button:not([type])');
    if (b) { const l=b.getAttribute('data-busy-label')||'Attendere…'; b.innerHTML='<span class="nosc-spin"></span>'+l; setTimeout(()=>b.disabled=true,0); }
}, true);

function genPanel(classId, pubId) {
    return {
        seek(sec) {
            const a = document.getElementById('artifact-audio');
            if (a) { a.currentTime = sec; a.play().catch(()=>{}); window.scrollTo({top:0,behavior:'smooth'}); }
        },
        init() {
            // deep-link ?t=N → posiziona il player audio
            const t = new URLSearchParams(location.search).get('t');
            const a = document.getElementById('artifact-audio');
            if (t && a) { const s=parseInt(t,10)||0; a.addEventListener('loadedmetadata',()=>{a.currentTime=s;},{once:true}); }
            // toggle mindmap auto-generate
            window.addEventListener('show-mindmap', (e) => {
                const el = document.getElementById('mygen-' + e.detail.id);
                if (!el) return;
                const open = el.style.display !== 'none';
                el.style.display = open ? 'none' : 'block';
                if (!open) renderMyGen(el.querySelector('.mygen-svg'));
            });
        },
    };
}

function genRow(id, status, type, quizId) {
    return {
        id, status, type, quizId,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const url = `/learn/classi/${@js($class->id)}/artefatti/${@js($publication->id)}/generati/${this.id}/stato`;
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

function renderMyGen(svg) {
    if (!svg || svg.dataset.rendered) return;
    try {
        const md = atob(svg.dataset.md);
        const { Transformer, Markmap } = window.markmap;
        const { root } = new Transformer().transform(md);
        Markmap.create(svg, { fitRatio: 0.92, autoFit: true }, root);
        svg.dataset.rendered = '1';
    } catch(e) { console.error('markmap', e); }
}
</script>

@if($artifact->type === 'mindmap' || $generated->where('type','mindmap')->where('status','ready')->count())
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-view@0.18"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-lib@0.18"></script>
@endif

@if($artifact->type === 'mindmap')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const svg = document.getElementById('artifact-mindmap-svg');
    const md = @json($artifact->content ?? '');
    if (!svg || !md.trim()) return;
    try {
        const { Transformer, Markmap } = window.markmap;
        const { root } = new Transformer().transform(md);
        const mm = Markmap.create(svg, { fitRatio: 0.92, autoFit: true }, root);
        setTimeout(() => mm.fit(), 100);
    } catch (e) { svg.parentElement.innerHTML = '<div style="padding:20px;color:#C52A2A">Errore mappa: '+e.message+'</div>'; }
});
</script>
@endif

@if($artifact->type === 'conceptmap')
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
<script src="/js/concept-map-editor.js?v={{ filemtime(public_path('js/concept-map-editor.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const initial = @json($graph ?: ['nodes' => [], 'edges' => []]);
    const el = document.getElementById('artifact-concept-map');
    if (!initial.nodes || !initial.nodes.length) { el.innerHTML = '<div style="padding:30px;text-align:center;color:#8A9696">Nessun dato.</div>'; return; }
    window.NosciteConceptMap.createViewer('#artifact-concept-map', initial, {});
});
</script>
@endif
@endpush
@endsection
