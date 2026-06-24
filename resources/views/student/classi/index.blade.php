@extends('layouts.student')
@section('title', 'Le mie classi')
@section('breadcrumb', 'Le mie classi')
@section('content')
<div style="max-width:900px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Le mie classi</h1>
        <a href="{{ route('student.classes.join.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">Unisciti a una classe</a>
    </div>

    @forelse ($classes as $class)
        <div style="background:white; border-radius:10px; padding:16px 20px; margin-bottom:10px; border:1px solid #C8D0D0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="flex:1;">
                    @if($class->pivot->status === 'pending')
                        <div style="font-weight:700; color:#1A1F1F;">{{ $class->name }}</div>
                    @else
                        <a href="{{ route('student.classes.show', $class) }}" style="font-weight:700; color:#1A1F1F; text-decoration:none;">{{ $class->name }} &rarr;</a>
                    @endif
                    <div style="font-size:0.8rem; color:#8A9696;">{{ $class->subject->name ?? '—' }} · {{ $class->school_year }} · {{ $class->teacher->name ?? '' }}</div>
                </div>
                @if($class->pivot->status === 'pending')
                    <span style="font-size:0.72rem; font-weight:700; color:#E28A53; background:#FDECE2; border:1px solid #E28A53; border-radius:4px; padding:3px 10px;">In attesa di approvazione</span>
                @else
                    <a href="{{ route('student.classes.minerva', $class) }}" style="font-size:0.75rem; font-weight:700; color:#55B1AE; background:#1A1F1F; border-radius:6px; padding:6px 12px; text-decoration:none;">&#9788; Chiedi a Minerva</a>
                    <span style="font-size:0.72rem; font-weight:700; color:#3A8C89; background:#E8F5F5; border:1px solid #55B1AE; border-radius:4px; padding:3px 10px;">Attiva</span>
                @endif
            </div>
        </div>
    @empty
        <div style="background:white; border-radius:12px; padding:40px; text-align:center; border:2px dashed #C8D0D0;">
            <div style="font-size:2.4rem; margin-bottom:8px;">&#128218;</div>
            <p style="color:#8A9696; font-size:0.9rem;">Non sei ancora iscritto a nessuna classe.</p>
        </div>
    @endforelse
</div>
@endsection
