@extends('layouts.admin')
@section('title', 'Quiz')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Gestione Quiz</h2>
    <a href="/admin/quizzes/create" style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">+ Nuovo quiz</a>
</div>

<div style="background:white; border-radius:10px; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#F5F7F7;">
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Quiz</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Corso</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Domande</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Soglia</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Tentativi</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quizzes as $quiz)
            <tr style="border-bottom:1px solid #F5F7F7;">
                <td style="padding:12px 16px;">
                    <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $quiz->title }}</div>
                    @if($quiz->description)
                    <div style="color:#8A9696; font-size:0.75rem;">{{ \Illuminate\Support\Str::limit($quiz->description, 50) }}</div>
                    @endif
                </td>
                <td style="padding:12px 16px; font-size:0.875rem; color:#4A5252;">
                    {{ $quiz->course?->name ?? ($quiz->module?->course?->name ?? '—') }}
                </td>
                <td style="padding:12px 16px; font-size:0.875rem; color:#4A5252;">
                    {{ $quiz->questions_count }}
                </td>
                <td style="padding:12px 16px; font-size:0.875rem; color:#4A5252;">
                    {{ $quiz->passing_score }}%
                </td>
                <td style="padding:12px 16px; font-size:0.875rem; color:#4A5252;">
                    {{ $quiz->attempts_count }}
                </td>
                <td style="padding:12px 16px;">
                    <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                        background:{{ $quiz->is_active ? '#E8F5F5' : '#F5F7F7' }};
                        color:{{ $quiz->is_active ? '#3A8C89' : '#8A9696' }};">
                        {{ $quiz->is_active ? 'Attivo' : 'Inattivo' }}
                    </span>
                </td>
                <td style="padding:12px 16px;">
                    <div style="display:flex; gap:8px;">
                        <a href="/admin/quizzes/{{ $quiz->id }}/edit" style="font-size:0.8rem; color:#55B1AE;">Modifica</a>
                        <a href="/admin/quizzes/{{ $quiz->id }}/questions" style="font-size:0.8rem; color:#8A9696;">Domande</a>
                        <a href="/admin/quizzes/{{ $quiz->id }}/results" style="font-size:0.8rem; color:#4A5252;">Risultati</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="padding:32px; text-align:center; color:#8A9696;">
                    Nessun quiz creato. <a href="/admin/quizzes/create" style="color:#55B1AE;">Crea il primo &rarr;</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
