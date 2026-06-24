@extends('layouts.student')
@section('title', $class->name)
@section('breadcrumb', 'Classi / ' . $class->name)
@section('content')
@php
    $typeLabels = [
        'transcript' => 'Trascrizione', 'summary' => 'Riassunto', 'mindmap' => 'Mappa mentale',
        'conceptmap' => 'Mappa concettuale', 'quiz' => 'Quiz', 'outline' => 'Scaletta',
    ];
    $typeIcon = [
        'transcript' => '📝', 'summary' => '📄', 'mindmap' => '🧠',
        'conceptmap' => '🕸️', 'quiz' => '❓', 'outline' => '🗂️',
    ];
@endphp
<div style="max-width:900px;">
    <div style="margin-bottom:8px;"><a href="{{ route('student.classes.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Le mie classi</a></div>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div>
            <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">{{ $class->name }}</h1>
            <p style="color:#8A9696; font-size:0.875rem; margin:2px 0 0;">{{ $class->subject->name ?? '—' }} · {{ $class->school_year }} · {{ $class->teacher->name ?? '' }}</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('student.classi.messaggi.index', $class) }}" style="padding:9px 16px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none;">&#9993; Messaggi</a>
            <a href="{{ route('student.classi.annunci.index', $class) }}" style="padding:9px 16px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none;">&#128226; Annunci</a>
            <a href="{{ route('student.classes.minerva', $class) }}" style="padding:9px 16px; background:#1A1F1F; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none;">&#9788; Chiedi a Minerva</a>
        </div>
    </div>

    {{-- Trasparenza (§8.1) — informativa non allarmistica --}}
    <div style="margin:16px 0; padding:10px 14px; background:#F0F6F6; border:1px solid #C8E0E0; border-radius:8px; font-size:0.8rem; color:#4A5252;">
        &#128274; La tua attività di studio in questa classe (materiali visti, quiz, domande a Minerva) è visibile al tuo docente.
        <a href="{{ route('student.schola.transparency') }}" style="color:#3A8C89; font-weight:600;">Come funziona</a>
    </div>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif

    {{-- Lezioni pubblicate, organizzate per Argomento → Lezione (P20b) --}}
    @if(!empty($lessonsByTopic) && $lessonsByTopic->isNotEmpty())
        <h2 style="font-size:0.95rem; font-weight:700; color:#4A5252; margin:18px 0 10px;">Lezioni</h2>
        @foreach($lessonsByTopic as $topicName => $lessons)
            <div style="margin-bottom:14px;">
                <div style="font-size:0.78rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px;">{{ $topicName }}</div>
                @foreach($lessons as $lesson)
                    <a href="{{ route('student.classes.lesson.show', [$class, $lesson]) }}"
                       style="display:flex; align-items:center; gap:12px; padding:13px 16px; background:white; border:1px solid #C8D0D0; border-radius:10px; margin-bottom:6px; text-decoration:none;">
                        <span style="font-size:1.3rem;">&#128214;</span>
                        <span style="flex:1; font-weight:600; color:#1A1F1F; font-size:0.92rem;">{{ $lesson->title }}</span>
                        <span style="color:#55B1AE; font-size:0.8rem;">apri &rarr;</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    @endif

    <h2 style="font-size:0.95rem; font-weight:700; color:#4A5252; margin:18px 0 10px;">Materiali pubblicati</h2>

    @forelse($publications as $p)
        @php $isNew = !isset($views[$p->id]); $a = $p->artifact; @endphp
        <a href="{{ route('student.classes.artifact.show', [$class, $p]) }}"
           style="display:flex; align-items:center; gap:12px; padding:14px 16px; background:white; border:1px solid {{ $isNew ? '#55B1AE' : '#C8D0D0' }}; border-radius:10px; margin-bottom:8px; text-decoration:none;">
            <span style="font-size:1.4rem;">{{ $typeIcon[$a->type] ?? '📦' }}</span>
            <span style="flex:1;">
                <span style="display:block; font-weight:600; color:#1A1F1F; font-size:0.92rem;">{{ $a->title }}</span>
                <span style="font-size:0.78rem; color:#8A9696;">{{ $typeLabels[$a->type] ?? $a->type }} · {{ $p->published_at?->format('d/m/Y') }}</span>
            </span>
            @if($isNew)
                <span style="font-size:0.68rem; font-weight:700; color:#fff; background:#55B1AE; border-radius:10px; padding:3px 10px;">NUOVO</span>
            @else
                <span style="font-size:0.68rem; font-weight:700; color:#8A9696;">visto</span>
            @endif
        </a>
    @empty
        <div style="background:white; border:2px dashed #C8D0D0; border-radius:12px; padding:36px; text-align:center; color:#8A9696;">
            <div style="font-size:2rem; margin-bottom:6px;">&#128193;</div>
            Il tuo docente non ha ancora pubblicato materiali per questa classe.
        </div>
    @endforelse
</div>
@endsection
