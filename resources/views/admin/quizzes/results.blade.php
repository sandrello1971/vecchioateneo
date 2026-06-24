@extends('layouts.admin')
@section('title', 'Risultati — ' . $quiz->title)
@section('content')
<div style="max-width:1100px;">
    <div style="margin-bottom:20px;">
        <a href="{{ route('admin.quizzes.index') }}" style="color:#8A9696; font-size:0.8rem; text-decoration:none;">&larr; Quiz</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            Risultati: {{ $quiz->title }}
        </h2>
        <div style="font-size:0.8rem; color:#8A9696;">
            @if($quiz->max_attempts)
                max {{ $quiz->max_attempts }} tentativi
            @else
                tentativi illimitati
            @endif
            @if($quiz->time_limit_minutes)
                · time limit {{ $quiz->time_limit_minutes }} min
            @endif
            · soglia {{ $quiz->passing_score }}%
        </div>
    </div>

    @php
        // Aggregato per studente: per ogni studente che ha tentato,
        // riassumo last attempt + counts. Calcolo in vista per non
        // toccare il controller (out of scope di questo fix).
        $perStudent = $quiz->attempts
            ->filter(fn ($a) => $a->student !== null)
            ->groupBy('student_id')
            ->map(function ($attempts) {
                $completed = $attempts->whereNotNull('completed_at');
                $best = $completed->where('passed', true)->first();
                $latest = $attempts->sortByDesc('created_at')->first();
                return (object) [
                    'student'         => $latest->student,
                    'used'            => $completed->count(),
                    'best_score'      => $best?->score,
                    'best_passed'     => (bool) $best,
                    'latest_score'    => $latest->score,
                    'latest_passed'   => (bool) $latest->passed,
                    'latest_at'       => $latest->completed_at ?? $latest->started_at,
                ];
            })
            ->sortByDesc('latest_at')
            ->values();
    @endphp

    @if($perStudent->isEmpty())
    <div style="background:white; border-radius:10px; padding:32px; text-align:center; color:#8A9696;">
        Nessun tentativo per questo quiz.
    </div>
    @else
    <div style="background:white; border-radius:10px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#F5F7F7;">
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Studente</th>
                    <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Tentativi</th>
                    <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Miglior esito</th>
                    <th style="padding:12px 16px; text-align:center; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Ultimo</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Data</th>
                </tr>
            </thead>
            <tbody>
                @foreach($perStudent as $row)
                <tr style="border-top:1px solid #F5F7F7;">
                    <td style="padding:12px 16px;">
                        <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $row->student->name }}</div>
                        <div style="color:#8A9696; font-size:0.75rem;">{{ $row->student->email }}</div>
                    </td>
                    <td style="padding:12px 16px; text-align:center; font-size:0.875rem; color:#4A5252;">
                        {{ $row->used }}
                        @if($quiz->max_attempts)
                            <span style="color:#8A9696;">/ {{ $quiz->max_attempts }}</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px; text-align:center;">
                        @if($row->best_passed)
                            <span style="padding:3px 8px; background:#E8F5F5; color:#3A8C89; border-radius:4px; font-size:0.75rem; font-weight:600;">
                                ✓ {{ $row->best_score }}%
                            </span>
                        @else
                            <span style="color:#8A9696; font-size:0.75rem;">—</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px; text-align:center;">
                        @if($row->latest_passed)
                            <span style="padding:3px 8px; background:#E8F5F5; color:#3A8C89; border-radius:4px; font-size:0.75rem; font-weight:600;">
                                ✓ {{ $row->latest_score }}%
                            </span>
                        @elseif($row->latest_score !== null)
                            <span style="padding:3px 8px; background:#fff3ec; color:#c97a45; border-radius:4px; font-size:0.75rem; font-weight:600;">
                                ✗ {{ $row->latest_score }}%
                            </span>
                        @else
                            <span style="padding:3px 8px; background:#F5F7F7; color:#8A9696; border-radius:4px; font-size:0.75rem;">In corso</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px; color:#8A9696; font-size:0.8rem;">
                        {{ $row->latest_at?->format('d/m/Y H:i') ?? '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
