@extends('layouts.docente')
@section('title', $topic->name)
@section('breadcrumb', 'Argomenti / ' . $topic->name)
@section('content')
<div style="max-width:980px;">
    <div style="margin-bottom:6px;"><a href="{{ route('docente.topics.index') }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; Argomenti</a></div>
    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:4px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $topic->name }}</h1>
        <span style="font-size:0.82rem; color:#8A9696;">{{ $topic->subject->name ?? '—' }}</span>
    </div>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:18px;">Crea le lezioni e assegna a ciascuna i materiali caricati. Il corpo della lezione verrà generato in una fase successiva.</p>

    @if(session('success'))
        <div style="background:#E6F4F1; border:1px solid #3A8C89; color:#1A1F1F; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;">{{ session('success') }}</div>
    @endif

    {{-- Nuova lezione --}}
    <form method="POST" action="{{ route('docente.lessons.store', $topic) }}" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px; margin-bottom:18px; display:grid; grid-template-columns:1fr auto; gap:10px;">
        @csrf
        <input type="text" name="title" required maxlength="255" placeholder="Titolo lezione (es. Le cause economiche)" style="padding:9px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
        <button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">+ Crea lezione</button>
        @error('title')<div style="grid-column:1/-1; color:#A8521F; font-size:0.78rem;">{{ $message }}</div>@enderror
    </form>

    {{-- Lezioni --}}
    <div id="lessons-list">
    @forelse($topic->lessons as $lesson)
        @php $docs = $classified[$lesson->id] ?? collect(); @endphp
        <div class="lesson-row" data-id="{{ $lesson->id }}" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="display:flex; flex-direction:column; gap:2px;">
                    <button type="button" onclick="moveLesson('{{ $lesson->id }}',-1)" title="Su" style="border:none; background:none; cursor:pointer; color:#8A9696; font-size:0.7rem; line-height:1;">&#9650;</button>
                    <button type="button" onclick="moveLesson('{{ $lesson->id }}',1)" title="Giù" style="border:none; background:none; cursor:pointer; color:#8A9696; font-size:0.7rem; line-height:1;">&#9660;</button>
                </div>
                <a href="{{ route('docente.lessons.show', $lesson) }}" style="flex:1; text-decoration:none;">
                    <div style="font-weight:700; color:#1A1F1F;">{{ $lesson->title }}</div>
                    @php $glabels = ['draft'=>'bozza','generating'=>'in composizione','ready'=>'pronta','failed'=>'fallita']; @endphp
                    <div style="font-size:0.78rem; color:#8A9696;">{{ $docs->count() }} {{ $docs->count() === 1 ? 'materiale' : 'materiali' }} · {{ $glabels[$lesson->generation_status] ?? $lesson->generation_status }}</div>
                </a>
                <details style="position:relative;">
                    <summary style="list-style:none; cursor:pointer; color:#8A9696; font-size:1.1rem; padding:0 6px;">&#8943;</summary>
                    <div style="position:absolute; right:0; top:24px; background:white; border:1px solid #C8D0D0; border-radius:8px; padding:8px; z-index:10; min-width:200px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                        <form method="POST" action="{{ route('docente.lessons.update', $lesson) }}" style="display:flex; gap:6px; margin-bottom:6px;">
                            @csrf @method('PATCH')
                            <input type="text" name="title" value="{{ $lesson->title }}" maxlength="255" required style="flex:1; padding:6px; border:1px solid #C8D0D0; border-radius:5px; font-size:0.78rem;">
                            <button style="padding:6px 8px; background:#1A1F1F; color:white; border:none; border-radius:5px; font-size:0.72rem; cursor:pointer;">Salva</button>
                        </form>
                        <form method="POST" action="{{ route('docente.lessons.destroy', $lesson) }}" onsubmit="return confirm('Eliminare la lezione? I materiali tornano nel pool.')">
                            @csrf @method('DELETE')
                            <button style="width:100%; padding:6px; background:none; color:#A8521F; border:1px solid #E2B6A0; border-radius:5px; font-size:0.78rem; cursor:pointer;">Elimina lezione</button>
                        </form>
                    </div>
                </details>
            </div>

            {{-- Materiali classificati nella lezione --}}
            <div style="margin-top:10px; padding-left:34px;">
                @foreach($docs as $doc)
                    <div style="display:flex; align-items:center; gap:8px; padding:6px 0; border-top:1px solid #F0F2F2; font-size:0.82rem;">
                        <span style="color:#3A8C89;">&#128196;</span>
                        <span style="flex:1; color:#1A1F1F;">{{ $doc->title }} <span style="color:#8A9696;">· {{ $doc->source_type }}</span></span>
                        <form method="POST" action="{{ route('docente.lessons.materials.unassign', [$lesson, $doc]) }}">
                            @csrf @method('DELETE')
                            <button title="Rimanda al pool" style="border:none; background:none; color:#A8521F; cursor:pointer; font-size:0.76rem;">rimuovi</button>
                        </form>
                    </div>
                @endforeach

                {{-- Assegna un materiale dal pool --}}
                @if($pool->isNotEmpty())
                <form method="POST" action="{{ route('docente.lessons.materials.assign', $lesson) }}" style="display:flex; gap:8px; margin-top:8px;">
                    @csrf
                    <select name="document_id" required style="flex:1; padding:7px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                        <option value="">Aggiungi un materiale dal pool…</option>
                        @foreach($pool as $p)<option value="{{ $p->id }}">{{ $p->title }} ({{ $p->source_type }}{{ $p->subject ? ' · '.$p->subject->name : '' }})</option>@endforeach
                    </select>
                    <button style="padding:7px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Assegna</button>
                </form>
                @endif
            </div>
        </div>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessuna lezione. Creane una qui sopra.</p>
    @endforelse
    </div>

    {{-- Pool materiali da organizzare --}}
    <div style="margin-top:24px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">Da organizzare ({{ $pool->count() }})</div>
        @forelse($pool as $p)
            <div style="display:flex; align-items:center; gap:8px; background:#FBFCFC; border:1px dashed #C8D0D0; border-radius:8px; padding:9px 14px; margin-bottom:6px; font-size:0.82rem;">
                <span style="color:#8A9696;">&#128196;</span>
                <span style="flex:1; color:#1A1F1F;">{{ $p->title }} <span style="color:#8A9696;">· {{ $p->source_type }}{{ $p->subject ? ' · '.$p->subject->name : '' }}</span></span>
                <a href="{{ route('docente.materials.show', $p) }}" style="color:#55B1AE; text-decoration:none; font-size:0.78rem;">apri</a>
            </div>
        @empty
            <p style="color:#8A9696; font-size:0.85rem;">Nessun materiale da organizzare. Caricane dalla sezione <a href="{{ route('docente.materials.index') }}" style="color:#55B1AE;">Materiali</a>.</p>
        @endforelse
    </div>
</div>

<script>
function currentLessonOrder() {
    return Array.from(document.querySelectorAll('#lessons-list .lesson-row')).map(el => el.dataset.id);
}
function moveLesson(id, delta) {
    const order = currentLessonOrder();
    const i = order.indexOf(id);
    const j = i + delta;
    if (j < 0 || j >= order.length) return;
    [order[i], order[j]] = [order[j], order[i]];
    fetch('{{ route('docente.lessons.reorder', $topic) }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
        body: JSON.stringify({order})
    }).then(r => { if (r.ok) location.reload(); });
}
</script>
@endsection
