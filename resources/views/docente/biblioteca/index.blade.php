@extends('layouts.docente')
@section('title', 'Biblioteca docenti')
@section('breadcrumb', 'Biblioteca')
@section('content')
@php
    $typeLabels = ['transcript'=>'Trascrizione','summary'=>'Riassunto','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale','quiz'=>'Quiz','outline'=>'Scaletta'];
@endphp
<div style="max-width:980px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">Biblioteca docenti</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">Artefatti condivisi dai colleghi. Duplica una copia indipendente nella tua libreria.</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif

    {{-- Filtri --}}
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cerca per titolo…" style="flex:1; min-width:180px; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
        <select name="subject_id" style="padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
            <option value="">Tutte le materie</option>
            @foreach($subjects as $s)<option value="{{ $s->id }}" @selected(request('subject_id')===$s->id)>{{ $s->name }}</option>@endforeach
        </select>
        <select name="type" style="padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
            <option value="">Tutti i tipi</option>
            @foreach($typeLabels as $k=>$lab)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $lab }}</option>@endforeach
        </select>
        @if(request('tag'))<input type="hidden" name="tag" value="{{ request('tag') }}">@endif
        <button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Filtra</button>
    </form>

    @forelse($artifacts as $a)
        <a href="{{ route('docente.biblioteca.show', $a) }}" style="display:flex; align-items:center; gap:12px; padding:14px 16px; background:white; border:1px solid #C8D0D0; border-radius:10px; margin-bottom:8px; text-decoration:none;">
            <span style="flex:1;">
                <span style="display:block; font-weight:600; color:#1A1F1F; font-size:0.92rem;">{{ $a->title }}</span>
                <span style="font-size:0.78rem; color:#8A9696;">
                    {{ $typeLabels[$a->type] ?? $a->type }} · {{ $a->subject->name ?? 'senza materia' }} · di {{ $a->teacher->name ?? '—' }}
                    @if($a->origin_artifact_id) · <em>fork</em>@endif
                </span>
            </span>
            <span style="font-size:0.75rem; color:#55B1AE; font-weight:600;">apri &rarr;</span>
        </a>
    @empty
        <div style="background:white; border:2px dashed #C8D0D0; border-radius:12px; padding:36px; text-align:center; color:#8A9696;">
            <div style="font-size:2rem; margin-bottom:6px;">&#127963;</div>
            Nessun artefatto condiviso {{ request()->hasAny(['q','subject_id','type','tag']) ? 'con questi filtri' : 'al momento' }}.
        </div>
    @endforelse
</div>
@endsection
