@extends('layouts.admin')
@section('title', 'Scuole')
@section('content')
@php $typeLabels = ['liceo'=>'Liceo','istituto_tecnico'=>'Istituto tecnico','altro'=>'Altro']; @endphp
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">Scuole</h1>
    <a href="{{ route('admin.scuole.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">+ Nuova scuola</a>
</div>

@if(session('success'))<div style="margin-bottom:14px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif

<div style="background:white; border:1px solid #C8D0D0; border-radius:10px; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
        <thead><tr style="background:#F5F7F7; text-align:left; color:#4A5252;">
            <th style="padding:10px 14px;">Scuola</th><th style="padding:10px 14px;">Tipo</th>
            <th style="padding:10px 14px;">Città</th><th style="padding:10px 14px;">Stato</th>
            <th style="padding:10px 14px;">Segreteria</th><th style="padding:10px 14px;">Docenti</th>
            <th style="padding:10px 14px;">Studenti</th><th style="padding:10px 14px;">Classi</th>
        </tr></thead>
        <tbody>
        @forelse($schools as $s)
            <tr style="border-top:1px solid #F0F2F2;">
                <td style="padding:10px 14px;"><a href="{{ route('admin.scuole.show', $s) }}" style="color:#3A8C89; font-weight:600; text-decoration:none;">{{ $s->name }}</a></td>
                <td style="padding:10px 14px;">{{ $typeLabels[$s->type] ?? $s->type }}</td>
                <td style="padding:10px 14px;">{{ $s->city ?? '—' }}</td>
                <td style="padding:10px 14px;"><span style="font-size:0.72rem; font-weight:700; color:{{ $s->status==='active' ? '#3A8C89' : '#A8521F' }};">{{ $s->status==='active' ? 'attiva' : 'sospesa' }}</span></td>
                <td style="padding:10px 14px;">{{ $s->school_admins_count }}</td>
                <td style="padding:10px 14px;">{{ $s->teachers_count }}</td>
                <td style="padding:10px 14px;">{{ $s->students_count }}</td>
                <td style="padding:10px 14px;">{{ $s->school_classes_count }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="padding:18px 14px; color:#8A9696;">Nessuna scuola. Creane una per iniziare.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
