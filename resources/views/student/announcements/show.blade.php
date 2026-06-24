@extends('layouts.student')
@section('title', $announcement->subject)
@section('content')

@php
    $currentUser = \App\Models\Student::find(session('student_id'));
    $isAuthor = $currentUser && $currentUser->id === $announcement->instructor_id;
@endphp

<div style="max-width:780px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a href="{{ route('student.announcements.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.875rem;">← Annunci</a>
    </div>

    <div style="background:white; border-radius:10px; padding:24px; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
            <span style="font-size:0.7rem; color:#E28A53; background:rgba(226,138,83,0.12); padding:2px 8px; border-radius:10px; font-weight:700; letter-spacing:0.05em;">📢 ANNUNCIO</span>
            <span style="font-size:0.7rem; color:#55B1AE; background:rgba(85,177,174,0.1); padding:2px 8px; border-radius:10px; font-weight:600;">{{ $announcement->course->name }}</span>
        </div>

        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">{{ $announcement->subject }}</h2>

        <div style="display:flex; gap:8px; align-items:center; font-size:0.8rem; color:#8A9696; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #F5F7F7;">
            <span>di <strong style="color:#4A5252;">{{ $announcement->instructor->name }}</strong></span>
            <span>•</span>
            <span>{{ $announcement->created_at->format('d/m/Y H:i') }}</span>
        </div>

        <div style="color:#1A1F1F; font-size:0.95rem; line-height:1.6; white-space:pre-wrap; word-wrap:break-word;">{{ $announcement->body }}</div>
    </div>

    @if($isAuthor)
        @php
            $readsCount = $announcement->readsCount();
            $recipientsCount = $announcement->recipientsCount();
            $percent = $recipientsCount > 0 ? round(($readsCount / $recipientsCount) * 100) : 0;
        @endphp
        <div style="background:white; border-radius:10px; padding:18px 22px; border-left:4px solid #55B1AE;">
            <div style="font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700; letter-spacing:0.05em; margin-bottom:8px;">Statistiche lettura</div>
            <div style="display:flex; align-items:baseline; gap:8px; margin-bottom:10px;">
                <span style="font-size:1.5rem; font-weight:700; color:#1A1F1F;">{{ $readsCount }}</span>
                <span style="color:#8A9696; font-size:0.9rem;">di {{ $recipientsCount }} discenti hanno letto</span>
                <span style="margin-left:auto; color:#55B1AE; font-weight:700; font-size:0.9rem;">{{ $percent }}%</span>
            </div>
            <div style="height:6px; background:#F5F7F7; border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:{{ $percent }}%; background:#55B1AE; border-radius:3px; transition:width 0.3s;"></div>
            </div>
            @if($recipientsCount === 0)
            <div style="margin-top:10px; font-size:0.75rem; color:#8A9696; font-style:italic;">Nessun discente iscritto attivo al corso al momento.</div>
            @endif
        </div>
    @endif
</div>

@endsection
