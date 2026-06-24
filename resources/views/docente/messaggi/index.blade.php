@extends('layouts.docente')
@section('title', 'Messaggi')
@section('breadcrumb', 'Classi / ' . $class->name . ' / Messaggi')
@section('content')
<div style="max-width:820px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.classes.show', $class) }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; {{ $class->name }}</a></div>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Messaggi · {{ $class->name }}</h1>
        <a href="{{ route('docente.classi.messaggi.create', $class) }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">+ Nuovo messaggio</a>
    </div>

    @if(session('success'))<div style="background:#E6F4F1; border:1px solid #3A8C89; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;">{{ session('success') }}</div>@endif

    @forelse($conversations as $c)
        <a href="{{ route('docente.classi.messaggi.show', [$class, $c]) }}" style="display:flex; align-items:center; gap:12px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:8px; text-decoration:none;">
            <div style="flex:1;">
                <div style="font-weight:700; color:#1A1F1F;">{{ $c->student->name ?? '—' }}</div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $c->subject }} · {{ \Illuminate\Support\Str::limit($c->latest_body, 70) }}</div>
            </div>
            @if(($unread[$c->id] ?? 0) > 0)
                <span style="font-size:0.68rem; font-weight:700; color:#fff; background:#E28A53; border-radius:10px; padding:3px 10px;">{{ $unread[$c->id] }}</span>
            @endif
        </a>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessun messaggio. Scrivi a uno studente con "Nuovo messaggio".</p>
    @endforelse
</div>
@endsection
