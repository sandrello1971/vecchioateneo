{{-- F-c — Avviso sovrascrittura sorgente strutturato: mostrato SOLO se il corso ha storia di
     apply dell'agente. La checkbox confirm_overwrite va inclusa DENTRO la form di upload. --}}
@if(($sourceOverwrite['hasHistory'] ?? false))
@php
    $overwriteCount = $sourceOverwrite['count'] ?? 0;
    $overwriteWord = $overwriteCount == 1 ? 'aggiornamento applicato' : 'aggiornamenti applicati';
    $overwriteLast = !empty($sourceOverwrite['last'])
        ? ' (es. «' . \Illuminate\Support\Str::limit($sourceOverwrite['last'], 80) . '»)'
        : '';
@endphp
<div style="padding:12px 14px; background:rgba(226,138,83,0.10);
            border:1px solid rgba(226,138,83,0.45); border-radius:8px;
            margin:4px 0 4px; font-size:0.78rem; color:#1A1F1F; line-height:1.55;
            font-family:'JetBrains Mono','SF Mono',monospace;">
    <strong style="color:#C26A2E;">⚠ Corso con aggiornamenti dell'agente</strong><br>
    Questo corso ha <strong>{{ $overwriteCount }}</strong> {{ $overwriteWord }} dall'agente{{ $overwriteLast }}.
    Ricaricare il manuale rigenererà il sorgente dal <code>.docx</code> e
    <strong>invaliderà quegli aggiornamenti e le proposte in coda</strong>. Confermi?
    <label style="display:flex; align-items:center; gap:8px; margin-top:8px;
                  cursor:pointer; font-weight:700; color:#C26A2E;">
        <input type="checkbox" name="confirm_overwrite" value="1">
        Confermo la sovrascrittura del sorgente
    </label>
</div>
@endif
