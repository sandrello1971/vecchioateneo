@extends('layouts.docente')
@section('title', 'Argomenti')
@section('breadcrumb', 'Argomenti')
@section('content')
<div style="max-width:980px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Argomenti</h1>
    </div>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:18px;">
        Un argomento raccoglie le lezioni di una materia. Dentro ogni lezione classifichi i materiali caricati.
        @if($unclassifiedCount > 0)
            <strong style="color:#E28A53;">{{ $unclassifiedCount }}</strong> materiali ancora da organizzare.
        @endif
    </p>

    @if(session('success'))
        <div style="background:#E6F4F1; border:1px solid #3A8C89; color:#1A1F1F; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;">{{ session('success') }}</div>
    @endif

    {{-- Nuovo argomento --}}
    @if($subjects->isEmpty())
        <div style="background:#FBF0E8; border:1px solid #E28A53; color:#7A4A28; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:0.85rem;">
            Non hai materie assegnate: chiedi alla segreteria di assegnarti una cattedra/competenza prima di creare argomenti.
        </div>
    @else
    <form method="POST" action="{{ route('docente.topics.store') }}" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px; margin-bottom:18px; display:grid; grid-template-columns:2fr 1fr auto; gap:10px;">
        @csrf
        <input type="text" name="name" required maxlength="255" placeholder="Nome argomento (es. La Rivoluzione francese)" style="padding:9px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
        <select name="subject_id" required style="padding:9px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
            <option value="">Materia…</option>
            @foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
        <button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">+ Crea argomento</button>
        @error('name')<div style="grid-column:1/-1; color:#A8521F; font-size:0.78rem;">{{ $message }}</div>@enderror
        @error('subject_id')<div style="grid-column:1/-1; color:#A8521F; font-size:0.78rem;">{{ $message }}</div>@enderror
    </form>
    @endif

    {{-- Elenco argomenti --}}
    <div id="topics-list">
    @forelse($topics as $topic)
        <div class="topic-row" data-id="{{ $topic->id }}" style="display:flex; align-items:center; gap:12px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:8px;">
            <div style="display:flex; flex-direction:column; gap:2px;">
                <button type="button" onclick="moveTopic('{{ $topic->id }}',-1)" title="Su" style="border:none; background:none; cursor:pointer; color:#8A9696; font-size:0.7rem; line-height:1;">&#9650;</button>
                <button type="button" onclick="moveTopic('{{ $topic->id }}',1)" title="Giù" style="border:none; background:none; cursor:pointer; color:#8A9696; font-size:0.7rem; line-height:1;">&#9660;</button>
            </div>
            <a href="{{ route('docente.topics.show', $topic) }}" style="flex:1; text-decoration:none;">
                <div style="font-weight:700; color:#1A1F1F;">{{ $topic->name }}</div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $topic->subject->name ?? '—' }} · {{ $topic->lessons_count }} {{ $topic->lessons_count === 1 ? 'lezione' : 'lezioni' }}</div>
            </a>
            <details style="position:relative;">
                <summary style="list-style:none; cursor:pointer; color:#8A9696; font-size:1.1rem; padding:0 6px;">&#8943;</summary>
                <div style="position:absolute; right:0; top:24px; background:white; border:1px solid #C8D0D0; border-radius:8px; padding:8px; z-index:10; min-width:180px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                    <form method="POST" action="{{ route('docente.topics.update', $topic) }}" style="display:flex; gap:6px; margin-bottom:6px;">
                        @csrf @method('PATCH')
                        <input type="text" name="name" value="{{ $topic->name }}" maxlength="255" required style="flex:1; padding:6px; border:1px solid #C8D0D0; border-radius:5px; font-size:0.78rem;">
                        <button style="padding:6px 8px; background:#1A1F1F; color:white; border:none; border-radius:5px; font-size:0.72rem; cursor:pointer;">Salva</button>
                    </form>
                    <form method="POST" action="{{ route('docente.topics.destroy', $topic) }}" onsubmit="return confirm('Eliminare l\'argomento? Le lezioni vengono rimosse e i materiali tornano nel pool.')">
                        @csrf @method('DELETE')
                        <button style="width:100%; padding:6px; background:none; color:#A8521F; border:1px solid #E2B6A0; border-radius:5px; font-size:0.78rem; cursor:pointer;">Elimina argomento</button>
                    </form>
                </div>
            </details>
        </div>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessun argomento. Creane uno qui sopra.</p>
    @endforelse
    </div>
</div>

<script>
function currentTopicOrder() {
    return Array.from(document.querySelectorAll('#topics-list .topic-row')).map(el => el.dataset.id);
}
function moveTopic(id, delta) {
    const order = currentTopicOrder();
    const i = order.indexOf(id);
    const j = i + delta;
    if (j < 0 || j >= order.length) return;
    [order[i], order[j]] = [order[j], order[i]];
    fetch('{{ route('docente.topics.reorder') }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
        body: JSON.stringify({order})
    }).then(r => { if (r.ok) location.reload(); });
}
</script>
@endsection
