@extends('layouts.admin')
@section('title', 'Corsi')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Gestione Corsi</h2>
    <div style="display:flex; gap:8px;">
        <a href="/admin/courses/ingest" style="padding:8px 18px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">📥 Crea da documenti</a>
        <a href="/admin/courses/create" style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">+ Nuovo corso</a>
    </div>
</div>

<div style="background:white; border-radius:10px; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#F5F7F7;">
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Corso</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Moduli</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Ore</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            @foreach($courses as $course)
            <tr style="border-bottom:1px solid #F5F7F7;">
                <td style="padding:12px 16px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.2rem;">{{ $course->icon }}</span>
                        <div>
                            <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $course->name }}</div>
                            <div style="color:#8A9696; font-size:0.75rem;">{{ $course->short_description }}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">{{ $course->modules_count }}</td>
                <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">{{ $course->duration_hours }}h</td>
                <td style="padding:12px 16px;">
                    <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                        background:{{ $course->is_active ? '#E8F5F5' : '#F5F7F7' }};
                        color:{{ $course->is_active ? '#3A8C89' : '#8A9696' }};">
                        {{ $course->is_active ? 'Attivo' : 'Inattivo' }}
                    </span>
                </td>
                <td style="padding:12px 16px;">
                    <div style="display:flex; gap:8px;">
                        <a href="/admin/courses/{{ $course->id }}/edit" style="font-size:0.8rem; color:#55B1AE;">Modifica</a>
                        <a href="/admin/courses/{{ $course->id }}/modules" style="font-size:0.8rem; color:#8A9696;">Moduli</a>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
