@extends('layouts.docente')
@section('title', 'Messaggi')
@section('breadcrumb', 'Messaggi')
@section('content')
<div style="max-width:820px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Messaggi</h1>
        <a href="{{ route('docente.messages.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">+ Nuovo messaggio</a>
    </div>
    <p style="color:#8A9696; font-size:0.85rem; margin:0 0 16px;">Le conversazioni con gli studenti di tutte le tue classi. Ogni thread è privato tra te e quello studente.</p>

    @if(session('success'))<div style="background:#E6F4F1; border:1px solid #3A8C89; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;">{{ session('success') }}</div>@endif

    @forelse($conversations as $c)
        <a href="{{ route('docente.classi.messaggi.show', [$c->schoolClass, $c]) }}" style="display:flex; align-items:center; gap:12px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:8px; text-decoration:none;">
            <div style="flex:1;">
                <div style="font-weight:700; color:#1A1F1F;">{{ $c->student->name ?? '—' }}
                    <span style="font-weight:500; font-size:0.72rem; color:#55B1AE; background:#EAF5F4; border-radius:6px; padding:2px 8px; margin-left:6px;">{{ $c->schoolClass->name ?? '—' }}</span>
                </div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $c->subject }} · {{ \Illuminate\Support\Str::limit($c->latest_body, 70) }}</div>
            </div>
            @if(($unread[$c->id] ?? 0) > 0)
                <span style="font-size:0.68rem; font-weight:700; color:#fff; background:#E28A53; border-radius:10px; padding:3px 10px;">{{ $unread[$c->id] }}</span>
            @endif
        </a>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessun messaggio. Scrivi a una classe o a un singolo studente con "Nuovo messaggio".</p>
    @endforelse
</div>
@endsection
