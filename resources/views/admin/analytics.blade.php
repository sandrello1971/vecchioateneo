@extends('layouts.admin')
@section('title', 'Analytics')
@section('content')

<h2 style="font-size:1.1rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">📊 Performance per corso</h2>
<div style="background:white; border-radius:10px; overflow:hidden; margin-bottom:24px;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#F5F7F7;">
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Corso</th>
                <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Iscritti</th>
                <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Completati</th>
                <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Quiz tentati</th>
                <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Quiz superati</th>
                <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Punteggio medio</th>
            </tr>
        </thead>
        <tbody>
            @foreach($courseStats as $course)
            <tr style="border-bottom:1px solid #F5F7F7;">
                <td style="padding:12px 16px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span>{{ $course->icon }}</span>
                        <span style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $course->name }}</span>
                    </div>
                </td>
                <td style="padding:12px 16px; text-align:center; color:#4A5252;">{{ $course->total_students }}</td>
                <td style="padding:12px 16px; text-align:center;">
                    <span style="color:{{ $course->completed_students > 0 ? '#3A8C89' : '#8A9696' }}; font-weight:600;">
                        {{ $course->completed_students }}
                    </span>
                </td>
                <td style="padding:12px 16px; text-align:center; color:#4A5252;">{{ $course->quiz_attempts }}</td>
                <td style="padding:12px 16px; text-align:center;">
                    <span style="color:{{ $course->quiz_passed > 0 ? '#3A8C89' : '#8A9696' }}; font-weight:600;">
                        {{ $course->quiz_passed }}
                    </span>
                </td>
                <td style="padding:12px 16px; text-align:center;">
                    @if($course->avg_score > 0)
                    <span style="padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700;
                        background:{{ $course->avg_score >= 70 ? '#E8F5F5' : '#fff3ec' }};
                        color:{{ $course->avg_score >= 70 ? '#3A8C89' : '#c97a45' }};">
                        {{ $course->avg_score }}%
                    </span>
                    @else
                    <span style="color:#C8D0D0; font-size:0.8rem;">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">

    <div style="background:white; border-radius:10px; padding:20px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; font-size:0.9rem;">🏆 Top studenti per progresso</h3>
        @forelse($topStudents as $i => $student)
        <div style="display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid #F5F7F7;">
            <div style="width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;
                background:{{ $i === 0 ? '#FFD700' : ($i === 1 ? '#C0C0C0' : ($i === 2 ? '#CD7F32' : '#F5F7F7')) }};
                color:{{ $i < 3 ? 'white' : '#8A9696' }};">
                {{ $i + 1 }}
            </div>
            <div style="flex:1;">
                <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $student->name }}</div>
                <div style="font-size:0.75rem; color:#8A9696;">{{ $student->company ?? $student->email }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.8rem; font-weight:700; color:#55B1AE;">{{ $student->modules_completed }} moduli</div>
                <div style="font-size:0.7rem; color:#8A9696;">{{ $student->quizzes_passed }} quiz</div>
            </div>
        </div>
        @empty
        <p style="color:#8A9696; font-size:0.875rem;">Nessun dato disponibile.</p>
        @endforelse
    </div>

    <div style="background:white; border-radius:10px; padding:20px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; font-size:0.9rem;">⚠ Quiz più difficili</h3>
        @forelse($hardestQuizzes as $quiz)
        <div style="padding:10px 0; border-bottom:1px solid #F5F7F7;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F; flex:1; padding-right:12px;">
                    {{ \Illuminate\Support\Str::limit($quiz->title, 40) }}
                </div>
                <span style="padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700; flex-shrink:0;
                    background:{{ $quiz->avg_score >= 70 ? '#E8F5F5' : '#fff3ec' }};
                    color:{{ $quiz->avg_score >= 70 ? '#3A8C89' : '#c97a45' }};">
                    {{ round($quiz->avg_score) }}%
                </span>
            </div>
            <div style="font-size:0.75rem; color:#8A9696; margin-top:3px;">
                {{ $quiz->attempts }} tentativi · {{ $quiz->passed_count }} superati
            </div>
        </div>
        @empty
        <p style="color:#8A9696; font-size:0.875rem;">Nessun tentativo ancora.</p>
        @endforelse
    </div>
</div>

<div style="background:white; border-radius:10px; padding:20px; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="font-weight:700; color:#1A1F1F; font-size:0.9rem;">
            ⏰ Studenti inattivi da 7+ giorni ({{ $inactiveStudents->count() }})
        </h3>
        @if($inactiveStudents->count() > 0)
        <form method="POST" action="/admin/analytics/send-reminders">
            @csrf
            <button type="submit"
                    style="padding:6px 16px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                📧 Invia reminder a tutti
            </button>
        </form>
        @endif
    </div>

    @forelse($inactiveStudents as $student)
    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid #F5F7F7;">
        <div>
            <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $student->name }}</div>
            <div style="font-size:0.75rem; color:#8A9696;">{{ $student->email }}</div>
        </div>
        <div style="text-align:right; display:flex; align-items:center; gap:12px;">
            <div>
                <div style="font-size:0.8rem; color:#E28A53; font-weight:600;">
                    {{ $student->last_login_at ? $student->last_login_at->diffForHumans() : 'Mai connesso' }}
                </div>
            </div>
            <form method="POST" action="/admin/analytics/send-reminder/{{ $student->id }}">
                @csrf
                <button type="submit"
                        style="padding:4px 12px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                    📧 Reminder
                </button>
            </form>
        </div>
    </div>
    @empty
    <p style="color:#8A9696; font-size:0.875rem; text-align:center; padding:16px;">
        ✓ Tutti gli studenti sono attivi
    </p>
    @endforelse
</div>

@endsection
