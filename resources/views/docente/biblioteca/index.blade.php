@extends('layouts.docente')
@section('title', 'Biblioteca docenti')
@section('breadcrumb', 'Biblioteca')
@section('content')
@php
    $typeLabels = ['transcript'=>'Trascrizione','summary'=>'Riassunto','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale','quiz'=>'Quiz','outline'=>'Scaletta'];
@endphp
<div style="max-width:980px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">Biblioteca</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">Materiali della scuola e dei colleghi + artefatti condivisi. Importa un materiale per usarlo nelle tue lezioni; duplica un artefatto nella tua libreria.</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif

    {{-- ===== Materiali (grezzi): di scuola + condivisi ===== --}}
    <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin:4px 0 10px;">Materiali</div>
    @forelse($materials as $m)
        <div style="display:flex; align-items:center; gap:12px; padding:12px 16px; background:white; border:1px solid #C8D0D0; border-radius:10px; margin-bottom:8px;">
            <span style="flex:1;">
                <a href="{{ route('docente.materials.shared.show', $m) }}" style="display:block; font-weight:600; color:#1A1F1F; font-size:0.92rem; text-decoration:none;">{{ $m->title }}</a>
                <span style="font-size:0.78rem; color:#8A9696;">
                    {{ $m->source_type }} · {{ $m->subject->name ?? 'senza materia' }} ·
                    @if($m->is_school_material) <span style="color:#3A8C89;">di scuola</span>
                    @elseif($m->share_scope === 'all') di {{ $m->teacher->name ?? '—' }} · <span style="color:#3A8C89;">scuola</span>
                    @else di {{ $m->teacher->name ?? '—' }} · <span style="color:#3A8C89;">materia</span>@endif
                </span>
            </span>
            <form method="POST" action="{{ route('docente.materials.shared.import', $m) }}" onsubmit="this.querySelector('button').disabled=true;">
                @csrf
                <button type="submit" style="padding:7px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">Importa</button>
            </form>
        </div>
    @empty
        <p style="color:#8A9696; font-size:0.85rem; margin-bottom:8px;">Nessun materiale condiviso disponibile per te al momento.</p>
    @endforelse

    {{-- ===== Artefatti condivisi ===== --}}
    <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin:22px 0 10px;">Artefatti</div>

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
