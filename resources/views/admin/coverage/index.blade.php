@extends('layouts.admin')
@section('title', 'Copertura corsi')
@section('content')

<h1 style="font-size:1.4rem; color:#1A1F1F; margin:0 0 6px;">&#129517; Copertura corsi</h1>
<p style="color:#8A9696; font-size:0.85rem; margin:0 0 18px;">
    Lo Scout confronta la mappa di un corso con ciò che dicono <strong>oggi le fonti approvate</strong>
    del suo dominio, e propone <strong>argomenti emergenti non coperti</strong> (HITL: li accetti o scarti).
    È uno scout rumoroso — confidenza bassa è normale.
</p>

@if (session('success'))
    <div data-flash style="display:flex; gap:10px; background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
        <span style="flex:1;">{{ session('success') }}</span>
        <button type="button" data-dismiss-flash style="background:none; border:none; color:#3A8C89; cursor:pointer; font-size:1rem;">&times;</button>
    </div>
@endif

<div style="background:white; border-radius:10px; border:1px solid #E6EBEB; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
        <thead>
            <tr style="background:#F5F7F7; color:#5A6666; text-align:left;">
                <th style="padding:10px 14px;">Corso</th>
                <th style="padding:10px 14px;">Topic</th>
                <th style="padding:10px 14px;">Gap suggeriti</th>
                <th style="padding:10px 14px;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($courses as $c)
            @php($topic = optional($c->freshnessConfig)->topic)
            <tr style="border-top:1px solid #F0F4F4;">
                <td style="padding:10px 14px; font-weight:600; color:#1A1F1F;">{{ $c->name }}</td>
                <td style="padding:10px 14px;">
                    @if($topic)
                        <code style="color:#3A8C89;">{{ $topic }}</code>
                    @else
                        <span style="color:#C26A2E; font-size:0.78rem;">— da impostare —</span>
                    @endif
                </td>
                <td style="padding:10px 14px;">
                    @if($c->suggested_gaps_count > 0)
                        <span style="padding:2px 9px; background:rgba(226,138,83,0.15); color:#C26A2E; border-radius:12px; font-weight:700; font-size:0.74rem;">{{ $c->suggested_gaps_count }}</span>
                    @else
                        <span style="color:#8A9696;">0</span>
                    @endif
                </td>
                <td style="padding:10px 14px; text-align:right;">
                    <a href="{{ route('admin.coverage.show', $c) }}" style="padding:6px 12px; background:#55B1AE; color:white; border-radius:6px; text-decoration:none; font-size:0.78rem; font-weight:600;">Apri</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('[data-dismiss-flash]').forEach(function (b) { b.addEventListener('click', function () { b.closest('[data-flash]').remove(); }); });
</script>
@endsection
