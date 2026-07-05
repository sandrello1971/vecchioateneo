@extends('layouts.docente')
@section('title', 'Materiali')
@section('breadcrumb', 'Materiali')
@section('content')
<div style="max-width:980px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Materiali</h1>
        <a href="{{ route('docente.videos.index') }}" style="padding:9px 16px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">🎥 Video con analisi visiva</a>
        <a href="{{ route('docente.materials.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">+ Nuovo materiale</a>
    </div>

    {{-- Filtri --}}
    <form method="GET" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px; margin-bottom:16px; display:grid; grid-template-columns:repeat(4,1fr); gap:10px;">
        <select name="source_type" style="padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <option value="">Tutti i tipi</option>
            @foreach(['audio','youtube','photos','pdf','docx','text'] as $t)
                <option value="{{ $t }}" @selected(request('source_type')===$t)>{{ $t }}</option>
            @endforeach
        </select>
        <select name="subject_id" style="padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <option value="">Tutte le materie</option>
            @foreach($subjects as $s)<option value="{{ $s->id }}" @selected(request('subject_id')===$s->id)>{{ $s->name }}</option>@endforeach
        </select>
        <select name="status" style="padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <option value="">Tutti gli stati</option>
            @foreach(['pending','processing','ready','failed'] as $st)<option value="{{ $st }}" @selected(request('status')===$st)>{{ $st }}</option>@endforeach
        </select>
        <div style="display:flex; gap:8px;">
            <input type="text" name="tag" value="{{ request('tag') }}" placeholder="tag" style="flex:1; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <button style="padding:8px 14px; background:#1A1F1F; color:white; border:none; border-radius:6px; font-size:0.82rem; cursor:pointer;">Filtra</button>
        </div>
    </form>

    @php $badge = ['pending'=>['#8A9696','In coda'],'processing'=>['#E28A53','In elaborazione'],'ready'=>['#3A8C89','Pronto'],'failed'=>['#A8521F','Fallito']]; @endphp
    @forelse($documents as $doc)
        <a href="{{ route('docente.materials.show', $doc) }}" style="display:flex; align-items:center; gap:12px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:8px; text-decoration:none;">
            <div style="flex:1;">
                <div style="font-weight:700; color:#1A1F1F;">{{ $doc->title }}</div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $doc->source_type }} · {{ $doc->subject->name ?? '—' }} @if($doc->tags) · {{ implode(', ', $doc->tags) }} @endif</div>
            </div>
            @php [$col,$lab] = $badge[$doc->status] ?? ['#8A9696',$doc->status]; @endphp
            <span style="font-size:0.72rem; font-weight:700; color:{{ $col }}; border:1px solid {{ $col }}; border-radius:4px; padding:2px 10px;">{{ $lab }}</span>
        </a>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessun materiale. Caricane uno con "Nuovo materiale".</p>
    @endforelse
</div>
@endsection
