@extends('layouts.student')
@section('title', 'Registro — ' . $course->name)
@section('breadcrumb', 'Registro')
@section('content')
@php
    if (!function_exists('att_h')) { function att_h($h) { return rtrim(rtrim(number_format($h, 2, ',', ''), '0'), ',') . 'h'; } }
@endphp
<div style="max-width:960px; margin:0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <div>
            <a href="{{ route('student.course.show', $course->slug) }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none;">&larr; {{ $course->name }}</a>
            <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">Registro di frequenza</h2>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('student.course.sessions.index', $course->slug) }}" style="padding:8px 14px; border:1px solid #55B1AE; color:#55B1AE; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">Sessioni</a>
            <a href="{{ route('student.course.register.pdf', $course->slug) }}" style="padding:8px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">Scarica PDF</a>
        </div>
    </div>
    <p style="color:#6B7280; font-size:0.85rem; margin-bottom:18px;"><strong>Sincrono</strong> = sessioni in aula/online; <strong>FAD</strong> = tempo reale tracciato sui moduli asincroni.</p>

    @if($rows->isEmpty())
        <div style="padding:32px; text-align:center; background:#F7F9F9; border-radius:8px; color:#6B7280;">Nessun discente iscritto.</div>
    @else
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="text-align:left; color:#6B7280; border-bottom:2px solid #E5E7EB;">
                    <th style="padding:10px 8px;">Discente</th>
                    <th style="padding:10px 8px; text-align:center;">Sincrono</th>
                    <th style="padding:10px 8px; text-align:center;">FAD</th>
                    <th style="padding:10px 8px; text-align:center;">Totale</th>
                    <th style="padding:10px 8px; text-align:center;">Moduli</th>
                    <th style="padding:10px 8px;">Ultima attività</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $r)
                <tr style="border-bottom:1px solid #F0F0F0;">
                    <td style="padding:10px 8px; font-weight:600; color:#1A1F1F;">{{ $r['student']->name }}</td>
                    <td style="padding:10px 8px; text-align:center;">{{ att_h($r['sync_hours']) }}</td>
                    <td style="padding:10px 8px; text-align:center;">{{ att_h($r['async_hours']) }}</td>
                    <td style="padding:10px 8px; text-align:center; font-weight:700; color:#1A1F1F;">{{ att_h($r['total_hours']) }}</td>
                    <td style="padding:10px 8px; text-align:center;">{{ $r['modules_completed'] }}</td>
                    <td style="padding:10px 8px;">{{ $r['last_activity'] ? \Illuminate\Support\Carbon::parse($r['last_activity'])->format('d/m/Y') : '—' }}</td>
                    <td style="padding:10px 8px; text-align:right;">
                        <a href="{{ route('student.course.register.student', [$course->slug, $r['student']]) }}" style="color:#55B1AE; font-weight:600; text-decoration:none;">Dettaglio</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
