@extends('layouts.docente')
@section('title', 'Conversazione')
@section('breadcrumb', 'Classi / ' . $class->name . ' / Messaggi')
@section('content')
@php $me = session('student_id'); @endphp
<div style="max-width:760px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.classi.messaggi.index', $class) }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; Messaggi</a></div>
    <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">{{ $conversation->student->name ?? '—' }}</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">{{ $conversation->subject }}</p>

    <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:18px;">
        @foreach($conversation->messages as $m)
            @php $mine = $m->sender_id === $me; @endphp
            <div style="align-self:{{ $mine ? 'flex-end' : 'flex-start' }}; max-width:80%;">
                <div style="background:{{ $mine ? '#55B1AE' : 'white' }}; color:{{ $mine ? 'white' : '#1A1F1F' }}; border:1px solid {{ $mine ? '#55B1AE' : '#C8D0D0' }}; border-radius:12px; padding:10px 14px; font-size:0.9rem; line-height:1.5; white-space:pre-wrap;">{{ $m->body }}</div>
                <div style="font-size:0.7rem; color:#8A9696; margin-top:3px; text-align:{{ $mine ? 'right' : 'left' }};">{{ $m->sender->name ?? '' }} · {{ $m->created_at?->format('d/m H:i') }}</div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('docente.classi.messaggi.reply', [$class, $conversation]) }}" data-async style="display:flex; gap:8px;">
        @csrf
        <textarea name="body" required rows="2" maxlength="5000" placeholder="Scrivi una risposta…" style="flex:1; padding:10px; border:1px solid #C8D0D0; border-radius:10px; font-size:0.88rem; font-family:inherit; resize:none;"></textarea>
        <button data-busy-label="Invio…" style="padding:0 18px; background:#55B1AE; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer;">Invia</button>
    </form>
</div>
@endsection
