@extends('layouts.docente')
@section('title', 'Dashboard docente')
@section('breadcrumb', 'Dashboard')
@section('content')
<div style="max-width:980px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:4px;">Benvenuto, {{ session('student_name') }}</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:20px;">{{ $classes_count }} {{ $classes_count === 1 ? 'classe' : 'classi' }} · riepilogo</p>

    {{-- KPI rapidi --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; margin-bottom:20px;">
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px;">
            <div style="font-size:1.8rem; font-weight:800; color:{{ $pending_approvals->count() ? '#E28A53' : '#3A8C89' }};">{{ $pending_approvals->count() }}</div>
            <div style="font-size:0.8rem; color:#8A9696;">Iscrizioni da approvare</div>
        </div>
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px;">
            <div style="font-size:1.8rem; font-weight:800; color:{{ $docs_in_flight->where('status','failed')->count() ? '#A8521F' : '#1A1F1F' }};">{{ $docs_in_flight->count() }}</div>
            <div style="font-size:0.8rem; color:#8A9696;">Materiali in elaborazione/falliti</div>
        </div>
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px;">
            <div style="font-size:1.8rem; font-weight:800; color:{{ $open_questions ? '#E28A53' : '#3A8C89' }};">{{ $open_questions }}</div>
            <div style="font-size:0.8rem; color:#8A9696;">Domande scoperte aperte</div>
        </div>
    </div>

    {{-- Iscrizioni da approvare --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">Iscrizioni da approvare</div>
        @forelse($pending_approvals as $cs)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #F0F2F2; font-size:0.85rem;">
                <span>{{ $cs->student?->name ?? '—' }} <span style="color:#8A9696;">· {{ $cs->schoolClass?->name }}</span></span>
                <a href="{{ route('docente.classes.show', $cs->school_class_id) }}" style="color:#55B1AE; text-decoration:none; font-size:0.8rem;">gestisci &rarr;</a>
            </div>
        @empty
            <div style="color:#8A9696; font-size:0.85rem;">Nessuna iscrizione in attesa.</div>
        @endforelse
    </div>

    {{-- Materiali in elaborazione / falliti --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">Materiali in elaborazione / falliti</div>
        @forelse($docs_in_flight as $d)
            <a href="{{ route('docente.materials.show', $d->id) }}" style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #F0F2F2; font-size:0.85rem; text-decoration:none; color:#1A1F1F;">
                <span>{{ $d->title }}</span>
                <span style="font-size:0.72rem; font-weight:700; color:{{ $d->status === 'failed' ? '#A8521F' : '#E28A53' }};">{{ $d->status }}</span>
            </a>
        @empty
            <div style="color:#8A9696; font-size:0.85rem;">Nessun materiale in coda.</div>
        @endforelse
    </div>

    {{-- Ultime attività --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">Ultime attività degli studenti</div>
        @forelse($recent_views as $v)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F0F2F2; font-size:0.84rem;">
                <span>{{ $v->student?->name ?? '—' }} <span style="color:#8A9696;">ha aperto</span> {{ $v->publication?->artifact?->title ?? '—' }}</span>
                <span style="color:#8A9696; font-size:0.75rem;">{{ $v->publication?->schoolClass?->name }} · {{ $v->last_viewed_at?->diffForHumans() }}</span>
            </div>
        @empty
            <div style="color:#8A9696; font-size:0.85rem;">Ancora nessuna attività.</div>
        @endforelse
    </div>
</div>
@endsection
