@extends('layouts.scuola')
@section('title', 'Classi')
@section('breadcrumb', 'Classi')
@section('content')
<div style="max-width:980px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">Classi</h1>
        <a href="{{ route('scuola.classi.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">+ Nuova classe</a>
    </div>

    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead><tr style="background:#F5F7F7; text-align:left; color:#4A5252;">
                <th style="padding:10px 14px;">Classe</th><th style="padding:10px 14px;">Anno</th>
                <th style="padding:10px 14px;">Coordinatore</th><th style="padding:10px 14px;">Studenti</th><th style="padding:10px 14px;">Cattedre</th>
            </tr></thead>
            <tbody>
            @forelse($classes as $c)
                <tr style="border-top:1px solid #F0F2F2;">
                    <td style="padding:10px 14px;"><a href="{{ route('scuola.classi.show', $c) }}" style="color:#3A8C89; font-weight:600; text-decoration:none;">{{ $c->name }}</a>@if($c->is_archived)<span style="font-size:0.7rem; color:#A8521F;"> (archiviata)</span>@endif</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $c->school_year }}</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $c->coordinator?->name ?? '—' }}</td>
                    <td style="padding:10px 14px;">{{ $c->active_count }}</td>
                    <td style="padding:10px 14px;">{{ $c->cattedre_count }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:18px 14px; color:#8A9696;">Nessuna classe. Creane una per iniziare.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
