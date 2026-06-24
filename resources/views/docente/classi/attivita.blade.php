@extends('layouts.docente')
@section('title', 'Attività · ' . $class->name)
@section('breadcrumb', 'Classi / ' . $class->name . ' / Attività')
@section('content')
@php
    $typeLabels = ['transcript'=>'Trascrizione','summary'=>'Riassunto','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale','quiz'=>'Quiz','outline'=>'Scaletta'];
    $pctColor = fn($p) => $p >= 66 ? '#3A8C89' : ($p >= 33 ? '#E2A653' : '#A8521F');
@endphp
<div style="max-width:980px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.classes.show', $class) }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; {{ $class->name }}</a></div>
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">Attività della classe</h1>
        <a href="{{ route('docente.classes.questions', $class) }}" style="font-size:0.82rem; font-weight:600; color:#A8521F; text-decoration:none;">Domande scoperte ({{ $openQuestions }}) &rarr;</a>
    </div>

    {{-- Inattivi --}}
    @if(count($inactive))
    <div style="background:#FDF3EC; border:1px solid #E2A653; border-radius:10px; padding:14px 16px; margin:16px 0;">
        <div style="font-size:0.8rem; font-weight:700; color:#A8521F; margin-bottom:6px;">&#9888; Studenti inattivi da oltre 7 giorni ({{ count($inactive) }})</div>
        <div style="font-size:0.85rem; color:#4A5252;">{{ collect($inactive)->pluck('name')->implode(', ') }}</div>
    </div>
    @endif

    {{-- Copertura --}}
    <h2 style="font-size:0.95rem; font-weight:700; color:#4A5252; margin:20px 0 8px;">Copertura dei materiali</h2>
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:8px 18px;">
        @forelse($coverage as $c)
            <div style="padding:10px 0; border-bottom:1px solid #F0F2F2;">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:4px;">
                    <span><strong>{{ $c['title'] }}</strong> <span style="color:#8A9696;">· {{ $typeLabels[$c['type']] ?? $c['type'] }}</span></span>
                    <span style="color:#8A9696;">{{ $c['opened'] }}/{{ $c['total'] }} · {{ $c['pct'] }}%</span>
                </div>
                <div style="height:8px; background:#F0F2F2; border-radius:6px; overflow:hidden;">
                    <div style="height:100%; width:{{ $c['pct'] }}%; background:{{ $pctColor($c['pct']) }};"></div>
                </div>
            </div>
        @empty
            <div style="color:#8A9696; font-size:0.85rem; padding:10px 0;">Nessun materiale pubblicato.</div>
        @endforelse
    </div>

    {{-- Punti critici quiz --}}
    <h2 style="font-size:0.95rem; font-weight:700; color:#4A5252; margin:20px 0 8px;">Punti critici (quiz)</h2>
    @forelse($painPoints as $p)
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
                <strong>{{ $p['title'] }}</strong>
                <span style="color:#8A9696;">{{ $p['attempts'] }} tentativi · media {{ $p['avg_score'] }}%</span>
            </div>
            <div style="display:flex; gap:6px; margin:10px 0; font-size:0.72rem;">
                <span style="background:#FCE9E2; color:#A8521F; padding:3px 8px; border-radius:6px;">&lt;60: {{ $p['distribution']['low'] }}</span>
                <span style="background:#FBF3E2; color:#9A7B2E; padding:3px 8px; border-radius:6px;">60–79: {{ $p['distribution']['mid'] }}</span>
                <span style="background:#E8F5F5; color:#3A8C89; padding:3px 8px; border-radius:6px;">&ge;80: {{ $p['distribution']['high'] }}</span>
            </div>
            @if(count($p['top_wrong']))
                <div style="font-size:0.78rem; color:#8A9696; margin-bottom:4px;">Domande più sbagliate:</div>
                <ul style="margin:0 0 0 16px; font-size:0.82rem; color:#4A5252;">
                    @foreach($p['top_wrong'] as $w)
                        <li>{{ $w['question'] }} <span style="color:#A8521F;">({{ $w['wrong'] }}/{{ $w['total'] }} errate)</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    @empty
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; color:#8A9696; font-size:0.85rem;">Nessun tentativo quiz registrato.</div>
    @endforelse

    {{-- Attività per studente --}}
    <h2 style="font-size:0.95rem; font-weight:700; color:#4A5252; margin:20px 0 8px;">Attività per studente</h2>
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:0.83rem;">
            <thead><tr style="background:#F5F7F7; text-align:left; color:#4A5252;">
                <th style="padding:9px 12px;">Studente</th><th style="padding:9px 12px;">Ultima visita</th>
                <th style="padding:9px 12px;">Viste</th><th style="padding:9px 12px;">Quiz</th>
                <th style="padding:9px 12px;">Minerva</th><th style="padding:9px 12px;">Generati</th>
            </tr></thead>
            <tbody>
            @forelse($activity as $a)
                <tr style="border-top:1px solid #F0F2F2;">
                    <td style="padding:9px 12px; color:#1A1F1F;">{{ $a['name'] }}</td>
                    <td style="padding:9px 12px; color:{{ $a['last_visit'] ? '#4A5252' : '#A8521F' }};">{{ $a['last_visit'] ? \Illuminate\Support\Carbon::parse($a['last_visit'])->diffForHumans() : 'mai' }}</td>
                    <td style="padding:9px 12px;">{{ $a['views'] }}</td>
                    <td style="padding:9px 12px;">{{ $a['attempts'] }}</td>
                    <td style="padding:9px 12px;">{{ $a['chat_messages'] }}</td>
                    <td style="padding:9px 12px;">{{ $a['generations'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:14px 12px; color:#8A9696;">Nessuno studente attivo.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
