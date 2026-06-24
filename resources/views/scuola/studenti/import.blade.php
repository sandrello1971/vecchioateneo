@extends('layouts.scuola')
@section('title', 'Import studenti')
@section('breadcrumb', 'Studenti / Import')
@section('content')
@php
    $statusMeta = [
        'valid'     => ['Nuovo',     '#3A8C89', '#E8F5F5'],
        'attach'    => ['Aggancio',  '#3A5A8C', '#EEF3FB'],
        'duplicate' => ['Duplicato', '#9A7B2E', '#FBF3E2'],
        'conflict'  => ['Conflitto', '#A8521F', '#FDECE2'],
        'error'     => ['Errore',    '#C52A2A', '#FCE9E2'],
    ];
@endphp
<div style="max-width:1040px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.studenti.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Studenti</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Import studenti da CSV</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">Colonne: <code>nome, cognome, email (opzionale), data_nascita (YYYY-MM-DD), classe</code> + <code>consenso</code> opzionale. Separatore <code>,</code> o <code>;</code>.</p>

    @if(!$batch)
        <form method="POST" action="{{ route('scuola.studenti.import.preview') }}" enctype="multipart/form-data" data-async
              style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
            @csrf
            <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:8px;">File CSV</label>
            <input type="file" name="file" accept=".csv,text/csv,text/plain" required style="font-size:0.85rem; margin-bottom:16px;">
            <div><button data-busy-label="Analizzo…" style="padding:10px 18px; background:#1A1F1F; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Anteprima (nessuna scrittura)</button></div>
        </form>
    @else
        @php $s = $batch->summary; @endphp
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:14px;">
            <div style="font-size:0.78rem; color:#8A9696; margin-bottom:10px;">Anteprima di <strong>{{ $batch->source_filename }}</strong> — {{ $s['total'] }} righe. <em>Nessun dato è stato scritto.</em></div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; font-size:0.78rem;">
                <span style="background:#E8F5F5; color:#3A8C89; padding:4px 10px; border-radius:6px;">Nuovi: {{ $s['valid'] }}</span>
                <span style="background:#EEF3FB; color:#3A5A8C; padding:4px 10px; border-radius:6px;">Agganci: {{ $s['attach'] ?? 0 }}</span>
                <span style="background:#FBF3E2; color:#9A7B2E; padding:4px 10px; border-radius:6px;">Duplicati: {{ $s['duplicate'] }}</span>
                <span style="background:#FDECE2; color:#A8521F; padding:4px 10px; border-radius:6px;">Conflitti: {{ $s['conflict'] }}</span>
                <span style="background:#FCE9E2; color:#C52A2A; padding:4px 10px; border-radius:6px;">Errori: {{ $s['error'] }}</span>
                <span style="background:#EEF3FB; color:#3A5A8C; padding:4px 10px; border-radius:6px;">&#9888; Minori: {{ $s['minors'] }}</span>
                <span style="background:#F0F2F2; color:#4A5252; padding:4px 10px; border-radius:6px;">Senza email (username): {{ $s['without_email'] }}</span>
            </div>
            @if(!empty($s['classes_to_create']))
                <div style="margin-top:10px; font-size:0.8rem; color:#9A7B2E;">Classi da creare: <strong>{{ implode(', ', $s['classes_to_create']) }}</strong></div>
            @endif
        </div>

        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; overflow:hidden; margin-bottom:14px;">
            <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                <thead><tr style="background:#F5F7F7; text-align:left; color:#4A5252;">
                    <th style="padding:8px 12px;">#</th><th style="padding:8px 12px;">Nome</th><th style="padding:8px 12px;">Accesso</th>
                    <th style="padding:8px 12px;">Nascita</th><th style="padding:8px 12px;">Classe</th><th style="padding:8px 12px;">Esito</th>
                </tr></thead>
                <tbody>
                @foreach($batch->rows as $r)
                    @php [$lab,$fg,$bg] = $statusMeta[$r['status']] ?? ['?','#4A5252','#F0F2F2']; @endphp
                    <tr style="border-top:1px solid #F0F2F2;">
                        <td style="padding:8px 12px; color:#8A9696;">{{ $r['line'] }}</td>
                        <td style="padding:8px 12px;">{{ trim($r['nome'].' '.$r['cognome']) }} @if($r['is_minor'])<span title="minore" style="color:#3A5A8C;">&#9913;</span>@endif</td>
                        <td style="padding:8px 12px; color:#4A5252;">{{ $r['email'] ?: ($r['username_base'] ? $r['username_base'].' (username)' : '—') }}</td>
                        <td style="padding:8px 12px; color:#4A5252;">{{ $r['birth_date'] ?? '—' }}</td>
                        <td style="padding:8px 12px;">{{ $r['class_name'] }} @if($r['class_status']==='to_create')<span style="font-size:0.7rem; color:#9A7B2E;">(da creare)</span>@endif</td>
                        <td style="padding:8px 12px;">
                            <span style="background:{{ $bg }}; color:{{ $fg }}; padding:2px 8px; border-radius:6px; font-size:0.72rem; font-weight:700;">{{ $lab }}</span>
                            @if($r['message'])<div style="font-size:0.7rem; color:#8A9696;">{{ $r['message'] }}</div>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('scuola.studenti.import.commit') }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px; margin-bottom:10px;">
            @csrf
            <input type="hidden" name="batch_id" value="{{ $batch->id }}">
            @if(!empty($s['classes_to_create']))
                <label style="display:flex; gap:8px; align-items:center; font-size:0.82rem; color:#4A5252; margin-bottom:10px;">
                    <input type="checkbox" name="create_missing_classes" value="1">
                    Crea le classi mancanti ({{ implode(', ', $s['classes_to_create']) }})
                </label>
            @endif
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                @if($s['duplicate'] > 0)
                    <label style="font-size:0.8rem; color:#4A5252;">Duplicati:
                        <select name="duplicate_action" style="padding:6px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                            <option value="update">aggiorna (iscrizione/consenso)</option>
                            <option value="skip">salta</option>
                        </select>
                    </label>
                @else
                    <input type="hidden" name="duplicate_action" value="update">
                @endif
                <button data-busy-label="Importo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Conferma e importa ({{ $s['valid'] + ($s['attach'] ?? 0) + $s['duplicate'] }})</button>
            </div>
        </form>
        <form method="POST" action="{{ route('scuola.studenti.import.discard', $batch) }}">
            @csrf
            <button style="padding:10px 16px; background:white; color:#8A9696; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; cursor:pointer;">Scarta</button>
        </form>
    @endif
</div>
@endsection
