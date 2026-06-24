@extends('layouts.scuola')
@section('title', 'Import docenti')
@section('breadcrumb', 'Docenti / Import')
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
<div style="max-width:980px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.docenti.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Docenti</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Import docenti da CSV</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">Colonne: <code>nome, cognome, email, materie</code> (materie separate da <code>|</code>). Separatore <code>,</code> o <code>;</code>. UTF-8.</p>

    @if(!$batch)
        {{-- STEP 1: upload --}}
        <form method="POST" action="{{ route('scuola.docenti.import.preview') }}" enctype="multipart/form-data" data-async
              style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
            @csrf
            <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:8px;">File CSV</label>
            <input type="file" name="file" accept=".csv,text/csv,text/plain" required style="font-size:0.85rem; margin-bottom:16px;">
            <div>
                <button data-busy-label="Analizzo…" style="padding:10px 18px; background:#1A1F1F; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Anteprima (nessuna scrittura)</button>
            </div>
        </form>
    @else
        {{-- STEP 2: report dry-run --}}
        @php $s = $batch->summary; @endphp
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:14px;">
            <div style="font-size:0.78rem; color:#8A9696; margin-bottom:10px;">Anteprima di <strong>{{ $batch->source_filename }}</strong> — {{ $s['total'] }} righe. <em>Nessun dato è stato scritto.</em></div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; font-size:0.78rem;">
                <span style="background:#E8F5F5; color:#3A8C89; padding:4px 10px; border-radius:6px;">Nuovi: {{ $s['valid'] }}</span>
                <span style="background:#EEF3FB; color:#3A5A8C; padding:4px 10px; border-radius:6px;">Agganci: {{ $s['attach'] ?? 0 }}</span>
                <span style="background:#FBF3E2; color:#9A7B2E; padding:4px 10px; border-radius:6px;">Duplicati: {{ $s['duplicate'] }}</span>
                <span style="background:#FDECE2; color:#A8521F; padding:4px 10px; border-radius:6px;">Conflitti: {{ $s['conflict'] }}</span>
                <span style="background:#FCE9E2; color:#C52A2A; padding:4px 10px; border-radius:6px;">Errori: {{ $s['error'] }}</span>
                @if($s['unknown_subjects'])<span style="background:#FBF3E2; color:#9A7B2E; padding:4px 10px; border-radius:6px;">Righe con materie ignote: {{ $s['unknown_subjects'] }}</span>@endif
            </div>
        </div>

        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; overflow:hidden; margin-bottom:14px;">
            <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                <thead><tr style="background:#F5F7F7; text-align:left; color:#4A5252;">
                    <th style="padding:8px 12px;">#</th><th style="padding:8px 12px;">Nome</th><th style="padding:8px 12px;">Email</th>
                    <th style="padding:8px 12px;">Materie</th><th style="padding:8px 12px;">Esito</th>
                </tr></thead>
                <tbody>
                @foreach($batch->rows as $r)
                    @php [$lab,$fg,$bg] = $statusMeta[$r['status']] ?? ['?','#4A5252','#F0F2F2']; @endphp
                    <tr style="border-top:1px solid #F0F2F2;">
                        <td style="padding:8px 12px; color:#8A9696;">{{ $r['line'] }}</td>
                        <td style="padding:8px 12px;">{{ trim($r['nome'].' '.$r['cognome']) }}</td>
                        <td style="padding:8px 12px; color:#4A5252;">{{ $r['email'] }}</td>
                        <td style="padding:8px 12px;">
                            {{ $r['materie_raw'] ?: '—' }}
                            @if(!empty($r['unknown']))<div style="font-size:0.72rem; color:#A8521F;">ignote: {{ implode(', ', $r['unknown']) }}</div>@endif
                        </td>
                        <td style="padding:8px 12px;">
                            <span style="background:{{ $bg }}; color:{{ $fg }}; padding:2px 8px; border-radius:6px; font-size:0.72rem; font-weight:700;">{{ $lab }}</span>
                            @if($r['message'])<div style="font-size:0.7rem; color:#8A9696;">{{ $r['message'] }}</div>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            {{-- COMMIT --}}
            <form method="POST" action="{{ route('scuola.docenti.import.commit') }}" data-async style="display:flex; gap:10px; align-items:center;">
                @csrf
                <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                @if($s['duplicate'] > 0)
                    <label style="font-size:0.8rem; color:#4A5252;">Duplicati:
                        <select name="duplicate_action" style="padding:6px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                            <option value="update">aggiorna (materie)</option>
                            <option value="skip">salta</option>
                        </select>
                    </label>
                @else
                    <input type="hidden" name="duplicate_action" value="update">
                @endif
                <button data-busy-label="Importo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Conferma e importa ({{ $s['valid'] + ($s['attach'] ?? 0) + $s['duplicate'] }})</button>
            </form>

            {{-- DISCARD --}}
            <form method="POST" action="{{ route('scuola.docenti.import.discard', $batch) }}">
                @csrf
                <button style="padding:10px 16px; background:white; color:#8A9696; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; cursor:pointer;">Scarta</button>
            </form>
        </div>
    @endif
</div>
@endsection
