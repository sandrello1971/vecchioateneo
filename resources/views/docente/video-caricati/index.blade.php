@extends('layouts.docente')
@section('title', 'Video caricati')
@section('breadcrumb', 'Video caricati')
@section('content')
<div style="max-width:980px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Video con analisi visiva</h1>
    </div>
    <p style="color:#8A9696; font-size:0.86rem; margin:0 0 18px;">Carica un tuo video: viene analizzato anche nelle immagini (diagrammi, icone, testo a schermo), reso ricercabile al suo interno e disponibile alla Minerva del docente. Per pubblicarlo agli studenti caricalo invece da una lezione.</p>

    @if(session('success'))<div style="margin-bottom:14px; padding:11px 16px; background:#E5F3F0; border:1px solid #55B1AE; border-radius:8px; color:#1F6B63; font-size:0.86rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:14px; padding:11px 16px; background:#FDECE2; border:1px solid #E28A53; border-radius:8px; color:#A8521F; font-size:0.86rem;">{{ session('error') }}</div>@endif

    @forelse($videos as $uv)
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 16px; margin-bottom:12px;"
             x-data="{ s: '{{ $uv->status }}' }"
             x-init="if (s === 'processing') { const t = setInterval(async () => { const r = await fetch('{{ route('docente.videos.status', $uv) }}'); const j = await r.json(); if (j.status !== 'processing') { clearInterval(t); location.reload(); } }, 5000); }">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span style="flex:1; font-size:0.95rem; font-weight:600; color:#1A1F1F;">{{ $uv->title }}</span>
                @if($uv->subject)<span style="font-size:0.74rem; color:#8A9696;">{{ $uv->subject->name }}</span>@endif
                @php $vb = ['processing'=>['#E28A53','analisi…'],'ready'=>['#3A8C89','pronto'],'failed'=>['#A8521F','fallito'],'pending'=>['#8A9696','in coda']]; [$vc,$vl]=$vb[$uv->status]??['#8A9696',$uv->status]; @endphp
                <span style="display:inline-block; padding:2px 9px; background:{{ $vc }}22; color:{{ $vc }}; border-radius:6px; font-size:0.72rem; font-weight:700;">{{ $vl }}</span>
            </div>

            @if($uv->status === 'ready')
                <x-uploaded-video-player
                    :title="$uv->title"
                    :stream-url="route('docente.videos.stream', $uv)"
                    :search-url="route('docente.videos.search', $uv)"
                    :ask-url="route('docente.videos.ask', $uv)"
                    status="ready" />
            @elseif($uv->status === 'failed')
                <p style="font-size:0.82rem; color:#A8521F;">&#10007; Analisi non riuscita{{ $uv->failure_reason ? ': ' . $uv->failure_reason : '' }}.</p>
            @else
                <p style="font-size:0.82rem; color:#8A9696;">Analisi in corso… la pagina si aggiorna da sola.</p>
            @endif

            <form method="POST" action="{{ route('docente.videos.destroy', $uv) }}" onsubmit="return confirm('Eliminare questo video?')" style="margin-top:10px;">
                @csrf @method('DELETE')<button style="padding:6px 13px; background:white; color:#8A9696; border:1px solid #C8D0D0; border-radius:8px; font-size:0.8rem; cursor:pointer;">Elimina</button>
            </form>
        </div>
    @empty
        <p style="color:#8A9696; font-size:0.86rem;">Nessun video caricato.</p>
    @endforelse

    <div style="margin-top:20px;">
        <h2 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:10px;">Carica un video</h2>
        <x-video-upload-form :subjects="$subjects" :video-ai-dpa-missing="$videoAiDpaMissing" />
    </div>
</div>
@endsection
