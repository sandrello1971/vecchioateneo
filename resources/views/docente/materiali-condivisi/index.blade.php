@extends('layouts.docente')
@section('title', 'Materiali condivisi')
@section('breadcrumb', 'Materiali condivisi')
@section('content')
<div style="max-width:980px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Materiali condivisi</h1>
        <a href="{{ route('docente.materials.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">I miei materiali &rarr;</a>
    </div>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">Materiali condivisi da altri docenti (della tua materia e scuola, oppure con tutti). Importali per averne una copia tua.</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif

    {{-- Filtri --}}
    <form method="GET" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px; margin-bottom:16px; display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
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
        <div style="display:flex; gap:8px;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="cerca nel titolo" style="flex:1; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <button style="padding:8px 14px; background:#1A1F1F; color:white; border:none; border-radius:6px; font-size:0.82rem; cursor:pointer;">Filtra</button>
        </div>
    </form>

    @forelse($documents as $doc)
        <div style="display:flex; align-items:center; gap:12px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:8px;">
            <div style="flex:1;">
                <a href="{{ route('docente.materials.shared.show', $doc) }}" style="font-weight:700; color:#1A1F1F; text-decoration:none;">{{ $doc->title }}</a>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $doc->source_type }} · {{ $doc->subject->name ?? '—' }} · di {{ $doc->teacher->name ?? 'docente' }}
                    @if($doc->share_scope === 'all') · <span style="color:#3A8C89;">tutti</span>@else · <span style="color:#3A8C89;">stessa materia</span>@endif
                </div>
            </div>
            <form method="POST" action="{{ route('docente.materials.shared.import', $doc) }}" onsubmit="this.querySelector('button').disabled=true;">
                @csrf
                <button type="submit" style="padding:7px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">Importa</button>
            </form>
        </div>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessun materiale condiviso disponibile per te al momento.</p>
    @endforelse
</div>
@endsection
