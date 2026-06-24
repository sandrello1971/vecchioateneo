@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')

<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
    <div style="background:white; border-radius:10px; padding:20px; border-left:4px solid #55B1AE;">
        <div style="color:#8A9696; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Studenti totali</div>
        <div style="font-size:2rem; font-weight:700; color:#1A1F1F;">{{ $totalStudents }}</div>
        <div style="color:#8A9696; font-size:0.8rem;">{{ $activeStudents }} attivi</div>
    </div>
    <div style="background:white; border-radius:10px; padding:20px; border-left:4px solid #E28A53;">
        <div style="color:#8A9696; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Corsi</div>
        <div style="font-size:2rem; font-weight:700; color:#1A1F1F;">{{ $totalCourses }}</div>
        <div style="color:#8A9696; font-size:0.8rem;">{{ $enrollments }} iscrizioni attive</div>
    </div>
    <div style="background:white; border-radius:10px; padding:20px; border-left:4px solid #3A8C89;">
        <div style="color:#8A9696; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Prove quiz svolte</div>
        <div style="font-size:2rem; font-weight:700; color:#1A1F1F;">{{ $quizAttempts }}</div>
        <div style="color:#8A9696; font-size:0.8rem;">{{ $passedAttempts }} superati</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
    <div style="background:white; border-radius:10px; padding:20px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px;">Ultimi studenti</h3>
        @foreach($recentStudents as $s)
        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #F5F7F7;">
            <div>
                <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $s->name }}</div>
                <div style="font-size:0.75rem; color:#8A9696;">{{ $s->email }}</div>
            </div>
            <div style="font-size:0.75rem; color:#8A9696;">{{ $s->created_at?->diffForHumans() ?? '—' }}</div>
        </div>
        @endforeach
        <a href="/admin/students" style="display:block; text-align:center; margin-top:12px; color:#55B1AE; font-size:0.8rem;">Vedi tutti &rarr;</a>
    </div>

    <div style="background:white; border-radius:10px; padding:20px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px;">Corsi</h3>
        @foreach($courses as $c)
        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #F5F7F7;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span>{{ $c->icon }}</span>
                <div>
                    <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $c->name }}</div>
                    <div style="font-size:0.75rem; color:#8A9696;">{{ $c->modules_count }} moduli</div>
                </div>
            </div>
            <a href="/admin/courses/{{ $c->id }}/edit" style="font-size:0.75rem; color:#55B1AE;">Modifica</a>
        </div>
        @endforeach
    </div>
</div>

@endsection
