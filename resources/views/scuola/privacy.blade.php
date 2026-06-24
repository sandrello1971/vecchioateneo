@extends('layouts.scuola')
@section('title', 'Privacy & GDPR')
@section('breadcrumb', 'Privacy')
@section('content')
<div style="max-width:920px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Privacy & GDPR</h1>

    {{-- DPA --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Accordo sul trattamento dati (DPA)</div>
        <p style="font-size:0.83rem; color:#4A5252; margin:0 0 12px;">
            La scuola è <strong>titolare</strong> del trattamento; Noscite è <strong>responsabile</strong> (art. 28 GDPR).
            Il DPA deve esplicitare il flusso dati verso il modello AI (chat Minerva): gli embedding sono locali, la chat va a Claude.
        </p>
        @if($school->dpa_signed_at)
            <div style="font-size:0.9rem; color:#3A8C89; margin-bottom:10px;">&#10003; DPA firmato il {{ $school->dpa_signed_at->format('d/m/Y') }}.</div>
        @else
            <div style="background:#FBF3E2; border-left:4px solid #E2A653; border-radius:6px; padding:10px 14px; color:#9A7B2E; font-size:0.85rem; margin-bottom:10px;">
                &#9888; DPA non ancora firmato. La scuola resta operativa, ma è un adempimento necessario prima del trattamento dei dati degli studenti reali.
            </div>
        @endif
        <form method="POST" action="{{ route('scuola.privacy.dpa') }}">@csrf
            <button style="padding:8px 16px; background:{{ $school->dpa_signed_at ? 'white' : '#55B1AE' }}; color:{{ $school->dpa_signed_at ? '#8A9696' : 'white' }}; border:1px solid {{ $school->dpa_signed_at ? '#C8D0D0' : '#55B1AE' }}; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">
                {{ $school->dpa_signed_at ? 'Revoca firma DPA' : 'Marca DPA come firmato' }}
            </button>
        </form>
    </div>

    {{-- Export dati --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Export dati scuola</div>
        <p style="font-size:0.83rem; color:#4A5252; margin:0 0 12px;">Archivio JSON con docenti, studenti, classi, cattedre e conteggi attività della tua scuola (accesso/portabilità).</p>
        <div style="display:flex; gap:10px; align-items:center;">
            <form method="POST" action="{{ route('scuola.privacy.export') }}" data-async>@csrf
                <button data-busy-label="Preparo…" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Genera export</button>
            </form>
            @if($exportReady)
                <a href="{{ route('scuola.privacy.export.download') }}" style="padding:9px 16px; background:#1A1F1F; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11015; Scarica export</a>
            @endif
        </div>
    </div>

    {{-- Audit import --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Tracciamento import (anagrafiche)</div>
        @forelse($batches as $b)
            <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F0F2F2; font-size:0.83rem;">
                <span>{{ $b->type === 'professors' ? 'Docenti' : 'Studenti' }} <span style="color:#8A9696;">· {{ $b->source_filename }} · {{ $b->creator?->name }}</span></span>
                <span style="font-size:0.72rem; color:#8A9696;">{{ $b->status }} · {{ $b->created_at?->format('d/m/Y H:i') }}</span>
            </div>
        @empty
            <div style="color:#8A9696; font-size:0.83rem;">Nessun import registrato.</div>
        @endforelse
    </div>
</div>
@endsection
