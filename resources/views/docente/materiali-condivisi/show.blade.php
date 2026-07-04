@extends('layouts.docente')
@section('title', $document->title)
@section('breadcrumb', 'Materiali condivisi / ' . $document->title)
@section('content')
<div style="max-width:820px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.materials.shared.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Materiali condivisi</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $document->title }}</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">
        {{ $document->source_type }} · {{ $document->subject->name ?? '—' }} · condiviso da {{ $document->teacher->name ?? 'docente' }}
    </p>

    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif

    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px; margin-bottom:16px;">
        <div style="display:flex; gap:10px; align-items:center; margin-bottom:14px;">
            <form method="POST" action="{{ route('docente.materials.shared.import', $document) }}" onsubmit="this.querySelector('button').disabled=true;">
                @csrf
                <button type="submit" style="padding:9px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">Importa nel mio pool</button>
            </form>
            @foreach(($document->source_files ?? []) as $i => $f)
                <a href="{{ route('docente.materials.shared.download', [$document, $i]) }}" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:8px; font-size:0.8rem; font-weight:600; text-decoration:none;">&#11015; File {{ $i + 1 }}</a>
            @endforeach
            @if($document->source_url)
                <a href="{{ $document->source_url }}" target="_blank" rel="noopener" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:8px; font-size:0.8rem; font-weight:600; text-decoration:none;">Apri sorgente</a>
            @endif
        </div>

        <div style="font-size:0.75rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Testo estratto</div>
        <div style="white-space:pre-wrap; font-size:0.88rem; color:#1A1F1F; max-height:520px; overflow:auto; border-top:1px solid #F0F2F2; padding-top:10px;">{{ $document->extracted_text }}</div>
    </div>
</div>
@endsection
