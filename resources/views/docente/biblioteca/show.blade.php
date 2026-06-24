@extends('layouts.docente')
@section('title', $artifact->title)
@section('breadcrumb', 'Biblioteca / ' . $artifact->title)
@section('content')
@php
    $typeLabels = ['transcript'=>'Trascrizione','summary'=>'Riassunto','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale','quiz'=>'Quiz','outline'=>'Scaletta'];
    $isMarkdown = in_array($artifact->type, ['transcript','summary','outline'], true);
@endphp
<div style="max-width:980px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.biblioteca.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Biblioteca</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $artifact->title }}</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:8px;">
        {{ $typeLabels[$artifact->type] ?? $artifact->type }} · {{ $artifact->subject->name ?? 'senza materia' }}
    </p>

    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif

    {{-- Attribuzione + catena origin --}}
    <div style="background:#F0F6F6; border:1px solid #C8E0E0; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:0.83rem; color:#3A8C89;">
        Autore: <strong>{{ $artifact->teacher->name ?? '—' }}</strong> · {{ $artifact->created_at?->format('d/m/Y') }}
        @if(count($chain))
            <div style="margin-top:6px; color:#4A5252; font-size:0.8rem;">
                Deriva da:
                @foreach($chain as $i => $c)
                    {{ $c['title'] }} (di {{ $c['author'] ?? 'autore rimosso' }}{{ $c['deleted'] ? ', eliminato' : '' }}){{ $i < count($chain) - 1 ? ' ← ' : '' }}
                @endforeach
            </div>
        @endif
    </div>

    {{-- Anteprima --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        @if($isMarkdown)
            <div class="md-body" style="font-size:0.9rem; line-height:1.65; color:#1A1F1F;">{!! schola_markdown($artifact->content) !!}</div>
        @elseif($artifact->type === 'mindmap')
            <div style="position:relative; width:100%; height:560px; border:1px solid #F5F7F7; border-radius:8px; overflow:hidden; background:#FDFEFE;">
                <svg id="lib-mindmap-svg" style="width:100%; height:100%;"></svg>
            </div>
        @elseif($artifact->type === 'conceptmap')
            <div id="lib-concept-map" style="background:#FFF; border:1px solid #D1D5DB; border-radius:8px; width:100%; height:68vh; min-height:460px; max-height:760px;"></div>
        @elseif($artifact->type === 'quiz')
            @if($quiz && $quiz->questions->count())
                <p style="font-size:0.85rem; color:#8A9696; margin:0 0 10px;">{{ $quiz->questions->count() }} domande.</p>
                <ol style="margin:0; padding-left:20px; font-size:0.88rem;">
                    @foreach($quiz->questions->take(3) as $q)<li style="margin-bottom:6px;">{{ $q->question }}</li>@endforeach
                </ol>
                @if($quiz->questions->count() > 3)<p style="font-size:0.8rem; color:#8A9696; margin-top:6px;">… e altre {{ $quiz->questions->count() - 3 }}.</p>@endif
            @else
                <p style="color:#8A9696;">Quiz senza domande.</p>
            @endif
        @endif
    </div>

    {{-- Fork --}}
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <form method="POST" action="{{ route('docente.biblioteca.fork', $artifact) }}" data-async>
            @csrf
            <button data-busy-label="Duplico…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">&#128203; Duplica nella mia libreria</button>
        </form>
        @if($isOwner)<span style="font-size:0.8rem; color:#8A9696;">Questo artefatto è tuo.</span>@endif
    </div>
</div>

@push('styles')
<style>
.md-body h1{font-size:1.3rem;font-weight:700;margin:.6em 0 .3em}
.md-body h2{font-size:1.1rem;font-weight:700;margin:.6em 0 .3em;color:#3A8C89}
.md-body h3{font-size:1rem;font-weight:600;margin:.5em 0 .25em}
.md-body ul,.md-body ol{margin:.3em 0 .6em 1.2em}.md-body li{margin:.15em 0}
.md-body p{margin:.4em 0}.md-body strong{color:#1A1F1F}
</style>
@endpush

@push('scripts')
@if($artifact->type === 'mindmap')
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-view@0.18"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-lib@0.18"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const svg = document.getElementById('lib-mindmap-svg');
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
    const el = document.getElementById('lib-concept-map');
    if (!initial.nodes || !initial.nodes.length) { el.innerHTML = '<div style="padding:30px;text-align:center;color:#8A9696">Nessun dato.</div>'; return; }
    window.NosciteConceptMap.createViewer('#lib-concept-map', initial, {});
});
</script>
@endif
@endpush
@endsection
