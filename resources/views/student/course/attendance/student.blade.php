@extends('layouts.student')
@section('title', 'Presenza — ' . $student->name)
@section('breadcrumb', $student->name)
@section('content')
@php
    $srcLabel = [
        'instructor_mark'   => 'Sessione sincrona',
        'module_completion' => 'Modulo completato (FAD)',
        'module_access'     => 'Accesso modulo',
        'quiz_attempt'      => 'Tentativo quiz',
        'login'             => 'Accesso',
        'heartbeat'         => 'Attività',
    ];
@endphp
<div style="max-width:840px; margin:0 auto;">
    <a href="{{ route('student.course.register', $course->slug) }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none;">&larr; Registro {{ $course->name }}</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin:6px 0 2px;">{{ $student->name }}</h2>
    <p style="color:#6B7280; font-size:0.85rem; margin-bottom:16px;">{{ $student->email }}</p>

    @if($row)
    <div style="display:flex; gap:14px; margin-bottom:20px;">
        @foreach([['Sincrono', $row['sync_hours']], ['FAD', $row['async_hours']], ['Totale', $row['total_hours']]] as [$k, $v])
        <div style="flex:1; background:#F7F9F9; border-radius:8px; padding:14px 16px;">
            <div style="font-size:0.75rem; color:#6B7280; text-transform:uppercase; letter-spacing:0.5px;">{{ $k }}</div>
            <div style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') }}h</div>
        </div>
        @endforeach
    </div>
    @endif

    <h3 style="font-size:0.95rem; font-weight:700; color:#1A1F1F; margin-bottom:10px;">Cronologia</h3>
    @if($records->isEmpty())
        <div style="padding:24px; text-align:center; background:#F7F9F9; border-radius:8px; color:#6B7280;">Nessuna attività registrata.</div>
    @else
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="text-align:left; color:#6B7280; border-bottom:2px solid #E5E7EB;">
                    <th style="padding:9px 8px;">Quando</th><th style="padding:9px 8px;">Tipo</th>
                    <th style="padding:9px 8px;">Dettaglio</th><th style="padding:9px 8px; text-align:right;">Ore</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $rec)
                <tr style="border-bottom:1px solid #F0F0F0;">
                    <td style="padding:9px 8px;">{{ $rec->occurred_at?->format('d/m/Y H:i') }}</td>
                    <td style="padding:9px 8px;">{{ $srcLabel[$rec->source] ?? $rec->source }}</td>
                    <td style="padding:9px 8px; color:#6B7280;">{{ $rec->session?->title ?? $rec->module?->title ?? '—' }}</td>
                    <td style="padding:9px 8px; text-align:right;">{{ $rec->hours_credited > 0 ? rtrim(rtrim(number_format($rec->hours_credited, 2, ',', ''), '0'), ',') . 'h' : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
