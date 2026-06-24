@extends('layouts.docente')
@section('title', $artifact->title)
@section('breadcrumb', 'Artefatti / ' . $artifact->title)
@section('content')
@php
    $typeLabels = [
        'transcript' => 'Trascrizione', 'summary' => 'Riassunto', 'mindmap' => 'Mappa mentale',
        'conceptmap' => 'Mappa concettuale', 'quiz' => 'Quiz', 'outline' => 'Scaletta',
    ];
    $isMarkdown = in_array($artifact->type, ['transcript', 'summary', 'outline'], true);
@endphp
<div style="max-width:980px;" x-data="artifactStatus('{{ $artifact->id }}', '{{ $artifact->status }}')">
    <div style="margin-bottom:8px;">
        @if($artifact->teaching_document_id)
            <a href="{{ route('docente.materials.show', $artifact->teaching_document_id) }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Torna al materiale</a>
        @else
            <a href="{{ route('docente.materials.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Materiali</a>
        @endif
    </div>

    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $artifact->title }}</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
        {{ $typeLabels[$artifact->type] ?? $artifact->type }}
        @if($artifact->subject) · {{ $artifact->subject->name }} @endif
    </p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Stato generazione (polling) --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Stato</div>
        <div style="display:flex; align-items:center; gap:12px;">
            <template x-if="status==='generating'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.9rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite;"></span>
                    <span>Generazione in corso…</span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.9rem;">&#10003; Pronto</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.9rem;">&#10007; Generazione fallita</span></template>
        </div>

        @if($artifact->status === 'failed')
            @if($artifact->generation_meta['failure_reason'] ?? null)
                <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $artifact->generation_meta['failure_reason'] }}</p>
            @endif
            @if($artifact->type !== 'transcript' && $artifact->teaching_document_id)
                <form method="POST" action="{{ route('docente.artifacts.regenerate', $artifact) }}" data-async style="margin-top:10px;">
                    @csrf
                    <button data-busy-label="Riprovo…" style="padding:8px 14px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.82rem; font-weight:600; cursor:pointer;">Riprova</button>
                </form>
            @endif
        @endif

        @if($artifact->generation_meta && $artifact->status === 'ready')
            <div style="margin-top:8px; font-size:0.75rem; color:#8A9696;">
                @isset($artifact->generation_meta['model']) modello: {{ $artifact->generation_meta['model'] }} @endisset
                @isset($artifact->generation_meta['tokens_in']) · token in/out: {{ $artifact->generation_meta['tokens_in'] }}/{{ $artifact->generation_meta['tokens_out'] ?? 0 }} @endisset
                @isset($artifact->generation_meta['prompt_version']) · prompt: {{ $artifact->generation_meta['prompt_version'] }} @endisset
            </div>
        @endif
    </div>

    {{-- Rendering del contenuto (solo se ready) --}}
    @if($artifact->status === 'ready')
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
            @if($isMarkdown)
                <div class="md-body" style="font-size:0.9rem; line-height:1.65; color:#1A1F1F;">
                    {!! schola_markdown($artifact->content) !!}
                </div>

            @elseif($artifact->type === 'mindmap')
                <div style="position:relative; width:100%; height:640px; border:1px solid #F5F7F7; border-radius:8px; overflow:hidden; background:#FDFEFE;">
                    <svg id="artifact-mindmap-svg" style="width:100%; height:100%;"></svg>
                    <div style="position:absolute; bottom:8px; right:12px; font-size:0.65rem; color:#8A9696; pointer-events:none; background:rgba(255,255,255,0.85); padding:2px 8px; border-radius:8px;">
                        Trascina per spostare · Scroll per zoom
                    </div>
                </div>

            @elseif($artifact->type === 'conceptmap')
                <div id="artifact-concept-map" style="background:#FFFFFF; border:1px solid #D1D5DB; border-radius:8px; width:100%; height:72vh; min-height:520px; max-height:800px;"></div>

            @elseif($artifact->type === 'quiz')
                @if($quiz && $quiz->questions->count())
                    <ol style="margin:0; padding-left:20px;">
                        @foreach($quiz->questions as $q)
                            <li style="margin-bottom:16px;">
                                <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem;">{{ $q->question }}</div>
                                <ul style="list-style:none; padding:0; margin:8px 0 0;">
                                    @foreach(($q->options ?? []) as $opt)
                                        @php $correct = trim((string)$opt) === trim((string)$q->correct_answer); @endphp
                                        <li style="padding:5px 10px; margin-bottom:4px; border-radius:6px; font-size:0.85rem; {{ $correct ? 'background:#E8F5F5; color:#3A8C89; font-weight:600;' : 'background:#F5F7F7; color:#4A5252;' }}">
                                            {{ $correct ? '✓ ' : '' }}{{ $opt }}
                                        </li>
                                    @endforeach
                                </ul>
                                @if($q->explanation)
                                    <div style="margin-top:6px; font-size:0.78rem; color:#8A9696; font-style:italic;">{{ $q->explanation }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p style="color:#8A9696; font-size:0.85rem;">Nessuna domanda disponibile.</p>
                @endif
            @endif
        </div>
    @endif

    {{-- Editing manuale (no quiz: le domande vivono altrove) --}}
    @if($artifact->status === 'ready' && $artifact->type !== 'quiz')
        <details style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:0; margin-bottom:12px;">
            <summary style="padding:14px 18px; cursor:pointer; font-size:0.85rem; font-weight:600; color:#4A5252;">Modifica manuale</summary>
            <form method="POST" action="{{ route('docente.artifacts.update', $artifact) }}" style="padding:0 18px 18px;">
                @csrf @method('PATCH')
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block;">Titolo</label>
                <input type="text" name="title" value="{{ old('title', $artifact->title) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-bottom:10px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block;">
                    Contenuto
                    @if($artifact->type === 'conceptmap')<span style="color:#8A9696; font-weight:400;"> (JSON con nodes/edges)</span>@endif
                    @if($artifact->type === 'mindmap')<span style="color:#8A9696; font-weight:400;"> (markdown markmap)</span>@endif
                </label>
                <textarea name="content" rows="14" style="width:100%; padding:12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; font-family:monospace; line-height:1.5;">{{ old('content', $artifact->content) }}</textarea>
                <button type="submit" style="margin-top:10px; padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">Salva modifiche</button>
            </form>
        </details>
    @endif

    {{-- Pubblicazione su classi (con feedback UX: stato + polling) --}}
    @if($artifact->status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:12px;"
         x-data="publicationStatus('{{ $artifact->id }}')">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Pubblicazione</div>

        {{-- Pubblicazioni esistenti --}}
        <template x-if="publications.length">
            <div style="margin-bottom:14px;">
                <template x-for="p in publications" :key="p.id">
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border:1px solid #E5E7E7; border-radius:8px; margin-bottom:6px;">
                        <span style="font-size:0.85rem; color:#1A1F1F;" x-text="p.class_name || 'Classe'"></span>
                        <span style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:0.72rem; font-weight:700;"
                                  :style="{color: p.rag_status==='ready' ? '#3A8C89' : (p.rag_status==='failed' ? '#A8521F' : '#E28A53')}"
                                  x-text="p.rag_status==='ready' ? 'pubblicato' : (p.rag_status==='failed' ? 'errore' : 'pubblicazione in corso…')"></span>
                            <form :action="'/docente/pubblicazioni/' + p.id" method="POST" onsubmit="return confirm('Ritirare la pubblicazione da questa classe?');">
                                @csrf @method('DELETE')
                                <button style="padding:5px 10px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;" data-busy-label="Ritiro…">Ritira</button>
                            </form>
                        </span>
                    </div>
                </template>
            </div>
        </template>

        {{-- Form pubblicazione su nuove classi --}}
        @if($teacherClasses->count())
        <form method="POST" action="{{ route('docente.artifacts.publish', $artifact) }}" data-async>
            @csrf
            <div style="font-size:0.8rem; color:#4A5252; margin-bottom:6px;">Pubblica su:</div>
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
                @foreach($teacherClasses as $class)
                    <label style="display:flex; align-items:center; gap:6px; padding:6px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem; {{ in_array($class->id, $publishedClassIds) ? 'opacity:0.5;' : '' }}">
                        <input type="checkbox" name="class_ids[]" value="{{ $class->id }}" {{ in_array($class->id, $publishedClassIds) ? 'disabled' : '' }}>
                        {{ $class->name }}
                    </label>
                @endforeach
            </div>
            <div style="display:flex; gap:16px; align-items:center; margin-bottom:10px; font-size:0.8rem; color:#4A5252;">
                <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" name="students_can_generate" value="1" checked> gli studenti possono auto-generare</label>
                <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" name="downloadable" value="1"> scaricabile</label>
            </div>
            <button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;" data-busy-label="Pubblicazione…">Pubblica</button>
        </form>
        @else
            <p style="font-size:0.82rem; color:#8A9696;">Non hai ancora classi attive su cui pubblicare.</p>
        @endif
    </div>
    @endif

    {{-- Condivisione in Biblioteca docenti (pacchetto 9) --}}
    @if($artifact->status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:12px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Biblioteca docenti</div>

        @if($sharingBlocked)
            <p style="font-size:0.85rem; color:#A8521F; margin:0;">
                &#128274; Trascrizione integrale di materiale potenzialmente protetto: resta nel perimetro delle tue classi e non è condivisibile in biblioteca.
            </p>
        @elseif($artifact->shared_with_teachers)
            <p style="font-size:0.85rem; color:#3A8C89; margin:0 0 10px;">&#10003; Condiviso con gli altri docenti.</p>
            <form method="POST" action="{{ route('docente.artifacts.sharing', $artifact) }}" data-async>
                @csrf @method('PATCH')
                <input type="hidden" name="shared" value="0">
                <button data-busy-label="Rimuovo…" style="padding:8px 14px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.82rem; cursor:pointer;">Rimuovi dalla biblioteca</button>
            </form>
        @else
            <p style="font-size:0.82rem; color:#8A9696; margin:0 0 10px;">Condividi questo artefatto trasformativo con gli altri docenti: potranno duplicarne una copia indipendente.</p>
            <form method="POST" action="{{ route('docente.artifacts.sharing', $artifact) }}" data-async>
                @csrf @method('PATCH')
                <input type="hidden" name="shared" value="1">
                @unless($rightsAcked)
                    <label style="display:flex; gap:8px; align-items:flex-start; font-size:0.8rem; color:#4A5252; margin-bottom:10px;">
                        <input type="checkbox" name="rights_ack" value="1" style="margin-top:2px;">
                        Dichiaro di avere i diritti per condividere questo contenuto con altri docenti e mi assumo la responsabilità di quanto pubblicato.
                    </label>
                @endunless
                <button data-busy-label="Condivido…" style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">Condividi in biblioteca</button>
            </form>
        @endif
    </div>
    @endif

    {{-- Azioni: rigenera (con conferma) + elimina. La trascrizione non si rigenera. --}}
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
        @if($artifact->type !== 'transcript' && $artifact->teaching_document_id)
            <form method="POST" action="{{ route('docente.artifacts.regenerate', $artifact) }}"
                  onsubmit="return confirm('Rigenerare questo artefatto? Il contenuto attuale verrà sovrascritto.');" data-async>
                @csrf
                @if($artifact->type === 'summary')
                    <select name="level" style="padding:9px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem;">
                        <option value="breve">Breve</option>
                        <option value="medio" selected>Medio</option>
                        <option value="dispensa">Dispensa</option>
                    </select>
                @endif
                @if($artifact->type === 'quiz')
                    <input type="number" name="num_questions" min="3" max="20" value="10" style="width:70px; padding:9px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem;">
                @endif
                <button style="padding:9px 16px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;" data-busy-label="Rigenerazione…">Rigenera</button>
            </form>
        @endif

        <form method="POST" action="{{ route('docente.artifacts.destroy', $artifact) }}" onsubmit="return confirm('Eliminare questo artefatto?');">
            @csrf @method('DELETE')
            <button style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.82rem; cursor:pointer;">Elimina</button>
        </form>
    </div>
</div>

@push('styles')
<style>
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
    .md-body h1{font-size:1.3rem;font-weight:700;margin:.6em 0 .3em}
    .md-body h2{font-size:1.1rem;font-weight:700;margin:.6em 0 .3em;color:#3A8C89}
    .md-body h3{font-size:1rem;font-weight:600;margin:.5em 0 .25em}
    .md-body ul,.md-body ol{margin:.3em 0 .6em 1.2em}
    .md-body li{margin:.15em 0}
    .md-body p{margin:.4em 0}
    .md-body strong{color:#1A1F1F}
    .md-body code{background:#F5F7F7;padding:1px 5px;border-radius:4px;font-size:.85em}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function artifactStatus(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const url = `/docente/artefatti/${id}/stato`;
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') {
                        clearInterval(timer);
                        window.location.reload();
                    }
                } catch(e) {}
            }, 4000);
        },
    };
}

function publicationStatus(artifactId) {
    return {
        publications: [],
        init() { this.refresh(); },
        async refresh() {
            try {
                const r = await fetch(`/docente/artefatti/${artifactId}/pubblicazioni/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                const d = await r.json();
                this.publications = d.publications || [];
            } catch(e) {}
            // Continua a fare polling finché qualche pubblicazione è in corso.
            if (this.publications.some(p => p.rag_status === 'pending' || p.rag_status === 'indexing')) {
                setTimeout(() => this.refresh(), 3000);
            }
        },
    };
}
</script>

@if($artifact->status === 'ready' && $artifact->type === 'mindmap')
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-view@0.18"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-lib@0.18"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const svg = document.getElementById('artifact-mindmap-svg');
    if (!svg) return;
    const markdown = @json($artifact->content ?? '');
    if (!markdown.trim()) return;
    try {
        const { Transformer, Markmap } = window.markmap;
        const { root } = new Transformer().transform(markdown);
        const mm = Markmap.create(svg, { fitRatio: 0.92, autoFit: true }, root);
        setTimeout(() => mm.fit(), 100);
    } catch (e) {
        console.error('Markmap render error:', e);
        svg.parentElement.innerHTML = '<div style="padding:24px; color:#C52A2A; font-size:0.85rem;">Errore rendering mappa mentale: ' + e.message + '</div>';
    }
});
</script>
@endif

@if($artifact->status === 'ready' && $artifact->type === 'conceptmap')
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
<script src="/js/concept-map-editor.js?v={{ filemtime(public_path('js/concept-map-editor.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const initial = @json($graph ?: ['nodes' => [], 'edges' => []]);
    const el = document.getElementById('artifact-concept-map');
    if (!initial.nodes || initial.nodes.length === 0) {
        el.innerHTML = '<div style="padding:40px; text-align:center; color:#8A9696;">Nessun dato per la mappa concettuale.</div>';
        return;
    }
    window.NosciteConceptMap.createViewer('#artifact-concept-map', initial, {});
});
</script>
@endif
@endpush
@endsection
