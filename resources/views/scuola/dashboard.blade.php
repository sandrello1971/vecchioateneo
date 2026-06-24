@extends('layouts.scuola')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
@php $typeLabels = ['liceo'=>'Liceo','istituto_tecnico'=>'Istituto tecnico','altro'=>'Altro']; @endphp
<div style="max-width:980px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:2px;">{{ $school->name }}</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:20px;">
        {{ $typeLabels[$school->type] ?? $school->type }}@if($school->city) · {{ $school->city }}@endif
        @if($school->isSuspended()) · <span style="color:#A8521F; font-weight:700;">SOSPESA</span>@endif
    </p>

    {{-- Conteggi --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:20px;">
        @foreach(['Docenti'=>$counts['teachers'], 'Studenti'=>$counts['students'], 'Classi'=>$counts['classes'], 'Cattedre'=>$counts['assignments']] as $lab=>$n)
            <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px;">
                <div style="font-size:1.8rem; font-weight:800; color:#1A1F1F;">{{ $n }}</div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $lab }}</div>
            </div>
        @endforeach
    </div>

    {{-- Adempimenti --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Adempimenti</div>
        @if($school->dpa_signed_at)
            <div style="font-size:0.9rem; color:#3A8C89;">&#10003; DPA firmato (accordo titolare/responsabile) — {{ $school->dpa_signed_at->format('d/m/Y') }}</div>
        @else
            <div style="font-size:0.9rem; color:#A8521F;">&#9888; DPA non ancora firmato. Necessario prima di trattare dati degli studenti reali.</div>
        @endif
        <p style="font-size:0.78rem; color:#8A9696; margin:8px 0 0;">La scuola è titolare del trattamento, Noscite responsabile (art. 28). Il consenso è gestito dalla scuola, non dalla piattaforma.</p>
    </div>

    {{-- Import recenti --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Import recenti</div>
        @forelse($recentImports as $b)
            <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F0F2F2; font-size:0.85rem;">
                <span>{{ $b->type === 'professors' ? 'Docenti' : 'Studenti' }} <span style="color:#8A9696;">· {{ $b->creator?->name }}</span></span>
                <span style="font-size:0.72rem; color:#8A9696;">{{ $b->status }} · {{ $b->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <div style="color:#8A9696; font-size:0.85rem;">Nessun import. I caricamenti massivi arrivano con i prossimi pacchetti.</div>
        @endforelse
    </div>
</div>
@endsection
