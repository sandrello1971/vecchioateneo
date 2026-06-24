@extends('layouts.admin')
@section('title', 'Copertura — ' . $course->name)
@section('content')

<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
    <a href="{{ route('admin.coverage.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Copertura</a>
    <h1 style="font-size:1.3rem; color:#1A1F1F; margin:0;">&#129517; {{ $course->name }}</h1>
</div>

@if (session('success'))
    <div data-flash style="display:flex; gap:10px; background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin:12px 0; font-size:0.85rem;">
        <span style="flex:1;">{{ session('success') }}</span>
        <button type="button" data-dismiss-flash style="background:none; border:none; color:#3A8C89; cursor:pointer; font-size:1rem;">&times;</button>
    </div>
@endif
@if (session('error'))
    <div data-flash style="display:flex; gap:10px; background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin:12px 0; font-size:0.85rem;">
        <span style="flex:1;">{{ session('error') }}</span>
        <button type="button" data-dismiss-flash style="background:none; border:none; color:#C0392B; cursor:pointer; font-size:1rem;">&times;</button>
    </div>
@endif

{{-- Topic + Analizza --}}
<div style="background:white; border-radius:10px; padding:16px; border:1px solid #E6EBEB; margin:14px 0;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
        <span style="font-weight:700; color:#1A1F1F;">🏷️ Topic del corso</span>
        <span style="color:#8A9696; font-size:0.76rem;">uno <strong>principale</strong> + eventuali secondari; lo Scout cerca nelle fonti di TUTTI.</span>
        <form method="POST" action="{{ route('admin.coverage.topics.suggest', $course) }}" style="margin-left:auto;">@csrf
            <button type="submit" style="padding:7px 12px; background:#FFF8EE; color:#C26A2E; border:1px solid rgba(226,138,83,0.45); border-radius:6px; font-size:0.77rem; font-weight:600; cursor:pointer;">✨ Suggerisci topic</button>
        </form>
    </div>

    @if ($suggestion)
    <div style="margin-bottom:10px; padding:9px 12px; border-radius:8px; font-size:0.8rem; background:#FFF8EE; border:1px solid rgba(226,138,83,0.45);">
        🤖 <strong>Proposta:</strong>
        @foreach ($suggestion['topics'] as $t)<code>{{ $t['topic'] }}</code><small style="color:#8A9696;">({{ $t['weight'] === 'primary' ? 'principale' : 'secondario' }}{{ $t['is_existing'] ? ', riuso' : ', nuovo' }})</small>@if(!$loop->last) · @endif @endforeach
        <span style="color:#8A9696;"> — precompilata sotto: rivedi pesi e «Salva topic».</span>
    </div>
    @endif

    @php($rows = $suggestion['topics'] ?? $courseTopics->all())
    <form method="POST" action="{{ route('admin.coverage.topics', $course) }}">
        @csrf
        <div id="topic-rows" style="display:flex; flex-direction:column; gap:6px;">
            @forelse ($rows as $r)
            <div class="topic-row" style="display:flex; gap:8px; align-items:center;">
                <input list="topic-list" name="topics[]" value="{{ $r['topic'] ?? '' }}" placeholder="es. agenti-ai" style="flex:1; min-width:180px; padding:7px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
                <select name="weights[]" style="padding:7px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.8rem;">
                    <option value="primary" @selected(($r['weight'] ?? '') === 'primary')>principale</option>
                    <option value="secondary" @selected(($r['weight'] ?? 'secondary') !== 'primary')>secondario</option>
                </select>
                <button type="button" class="topic-remove" title="Rimuovi" style="background:none; border:none; color:#C52A2A; cursor:pointer; font-size:1.1rem; padding:0 4px;">&times;</button>
            </div>
            @empty
            <div class="topic-row" style="display:flex; gap:8px; align-items:center;">
                <input list="topic-list" name="topics[]" value="" placeholder="es. agenti-ai" style="flex:1; min-width:180px; padding:7px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
                <select name="weights[]" style="padding:7px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.8rem;">
                    <option value="primary">principale</option>
                    <option value="secondary" selected>secondario</option>
                </select>
                <button type="button" class="topic-remove" title="Rimuovi" style="background:none; border:none; color:#C52A2A; cursor:pointer; font-size:1.1rem; padding:0 4px;">&times;</button>
            </div>
            @endforelse
        </div>
        <datalist id="topic-list">@foreach ($sourceTopics as $t)<option value="{{ $t }}">@endforeach</datalist>
        <div style="display:flex; gap:8px; align-items:center; margin-top:8px;">
            <button type="button" id="topic-add" style="background:none; border:1px solid #E6EBEB; color:#3A8C89; border-radius:6px; padding:5px 10px; font-size:0.76rem; font-weight:600; cursor:pointer;">+ aggiungi topic</button>
            <div style="flex:1;"></div>
            <button type="submit" style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Salva topic</button>
        </div>
    </form>

    {{-- Fonti mancanti per topic + Analizza --}}
    <div style="margin-top:12px;">
        @foreach ($courseTopics as $t)
            @if (!$t['has_sources'])
            <p style="color:#C26A2E; font-size:0.78rem; margin:3px 0;">⚠ Nessuna fonte approvata per <code>{{ $t['topic'] }}</code> ({{ $t['weight'] === 'primary' ? 'principale' : 'secondario' }}). <a href="{{ route('admin.sources.index', ['topic' => $t['topic']]) }}" style="color:#3A8C89; font-weight:600;">Aggiungi fonti</a>.</p>
            @endif
        @endforeach
        @if ($hasAnyApprovedSources)
        <form method="POST" action="{{ route('admin.coverage.analyze', $course) }}" style="margin-top:8px;">@csrf
            <button type="submit" style="padding:9px 18px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.82rem; font-weight:700; cursor:pointer;">&#128270; Analizza copertura</button>
        </form>
        @elseif ($courseTopics->isEmpty())
        <p style="color:#C26A2E; font-size:0.8rem; margin:6px 0 0;">⚠ Imposta i topic del corso prima di analizzare.</p>
        @endif
    </div>

    @if ($lastRun)
        @php($st = $lastRun->status)
        <div style="margin-top:12px; font-size:0.78rem; color:{{ $st === 'failed' ? '#C0392B' : ($st === 'completed' ? '#3A8C89' : '#C26A2E') }};">
            Ultima analisi: <strong>{{ $st === 'failed' ? '✗ fallita' : ($st === 'completed' ? '✓ completata' : '⏳ in corso') }}</strong>
            · {{ optional($lastRun->created_at)->format('d/m H:i') }}
            @if ($st === 'completed') · {{ $lastRun->gaps_found }} gap nuovi @endif
            @if ($st === 'failed')
                <div style="font-family:'JetBrains Mono','SF Mono',monospace; color:#7B1E1E; font-size:0.74rem; margin-top:3px;">{{ $lastRun->failure_reason }}</div>
            @endif
            @if ($st === 'running') <span style="color:#8A9696;">— ricarica la pagina per aggiornare.</span> @endif
        </div>
    @endif
</div>

{{-- Gap candidati: badge di provenienza (topic + peso), filtro per topic, ordine primary-first --}}
<div style="display:flex; align-items:center; gap:10px; margin:18px 0 8px;">
    <span style="font-weight:700; color:#1A1F1F;">Gap candidati ({{ $gaps->count() }})</span>
    @if ($gapTopics->isNotEmpty())
    <form method="GET" action="{{ route('admin.coverage.show', $course) }}" style="margin-left:auto; display:flex; gap:6px; align-items:center;">
        <label style="font-size:0.74rem; color:#8A9696;">filtra per topic:</label>
        <select name="gap_topic" onchange="this.form.submit()" style="padding:5px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.78rem;">
            <option value="">— tutti —</option>
            @foreach ($gapTopics as $gt)<option value="{{ $gt }}" @selected($filterGapTopic === $gt)>{{ $gt }}</option>@endforeach
        </select>
        @if ($filterGapTopic)<a href="{{ route('admin.coverage.show', $course) }}" style="font-size:0.74rem; color:#8A9696;">azzera</a>@endif
    </form>
    @endif
</div>
@forelse ($gaps as $g)
@php($isPrimary = $g->source_weight === 'primary')
<div style="background:white; border:1px solid #E6EBEB; border-left:3px solid {{ $isPrimary ? '#55B1AE' : '#E6EBEB' }}; border-radius:8px; padding:12px 14px; margin-bottom:8px;">
    <div style="display:flex; gap:10px; align-items:flex-start;">
        <div style="flex:1; min-width:0;">
            @if ($g->source_topic)
            <span style="padding:1px 9px; border-radius:10px; font-size:0.68rem; font-weight:700; margin-right:6px;
                         color:{{ $isPrimary ? '#3A8C89' : '#8A6D3B' }}; background:{{ $isPrimary ? 'rgba(85,177,174,0.15)' : 'rgba(226,138,83,0.12)' }};">
                {{ $g->source_topic }} {{ $isPrimary ? '★ principale' : 'secondario' }}
            </span>
            @endif
            <strong style="color:#1A1F1F;">{{ $g->title }}</strong>
            <span style="margin-left:8px; padding:1px 8px; border-radius:10px; font-size:0.7rem; font-weight:700; color:#5A6666; background:#F5F7F7;">conf {{ $g->confidence !== null ? number_format($g->confidence, 2) : 'n/d' }}</span>
            <p style="color:#5A6666; font-size:0.82rem; margin:5px 0;">{{ $g->rationale }}</p>
            @if ($g->source_url)
                <div style="font-size:0.74rem;">Fonte: <a href="{{ $g->source_url }}" target="_blank" rel="noopener" style="color:#3A8C89;">{{ $g->source_label ?: $g->source_url }}</a></div>
            @elseif ($g->source_label)
                <div style="font-size:0.74rem; color:#8A9696;">Fonte: {{ $g->source_label }}</div>
            @endif
        </div>
        <div style="display:flex; gap:6px;">
            <form method="POST" action="{{ route('admin.coverage.accept', $g) }}">@csrf @method('PATCH')
                <button type="submit" style="padding:6px 12px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">✓ Accetta</button>
            </form>
            <form method="POST" action="{{ route('admin.coverage.dismiss', $g) }}">@csrf @method('PATCH')
                <button type="submit" style="padding:6px 12px; background:white; color:#C26A2E; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">✗ Scarta</button>
            </form>
        </div>
    </div>
</div>
@empty
<div style="color:#8A9696; font-size:0.85rem; padding:20px; text-align:center; background:#F5F7F7; border-radius:8px;">
    Nessun gap candidato{{ $filterGapTopic ? ' per questo topic' : '' }}. {{ $hasAnyApprovedSources ? 'Lancia «Analizza copertura».' : '' }}
</div>
@endforelse

{{-- Fase B — gap accettati: generazione/revisione bozze (NESSUN inserimento nel corso) --}}
@if ($accepted->count() > 0)
<div style="font-weight:700; color:#1A1F1F; margin:22px 0 8px;">Gap accettati — bozze ({{ $accepted->count() }})</div>
<p style="color:#8A9696; font-size:0.76rem; margin:0 0 10px;">Le bozze restano qui per la revisione: <strong>non vengono inserite nel corso</strong> (l'inserimento è una fase successiva).</p>
@php($dbadge = ['generating' => ['#C26A2E','⏳ in generazione'], 'draft' => ['#3A8C89','bozza pronta'], 'approved' => ['#1A7F5A','✓ approvata (pronta per inserimento)'], 'discarded' => ['#8A9696','scartata'], 'failed' => ['#C0392B','✗ generazione fallita']])
@foreach ($accepted as $g)
@php($d = $g->draft)
<div style="background:white; border:1px solid #E6EBEB; border-radius:8px; padding:12px 14px; margin-bottom:8px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <div style="flex:1; min-width:200px;">
        <strong style="color:#1A1F1F;">{{ $g->title }}</strong>
        @if ($d)
            @php($db = $dbadge[$d->status] ?? ['#8A9696', $d->status])
            <span style="margin-left:8px; padding:1px 9px; border-radius:10px; font-size:0.7rem; font-weight:700; color:{{ $db[0] }}; background:#F5F7F7;">{{ $db[1] }}</span>
            @if ($d->status === 'failed')<div style="font-family:'JetBrains Mono',monospace; color:#7B1E1E; font-size:0.72rem; margin-top:3px;">{{ $d->error }}</div>@endif
        @endif
    </div>
    <div style="display:flex; gap:6px;">
        @if (!$d)
            <form method="POST" action="{{ route('admin.coverage.generate', $g) }}">@csrf
                <button type="submit" style="padding:6px 12px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:700; cursor:pointer;">&#9998; Genera bozza</button>
            </form>
        @else
            @if (in_array($d->status, ['draft', 'approved', 'failed']))
            <a href="{{ route('admin.coverage.draft', $g) }}" style="padding:6px 12px; background:#55B1AE; color:white; border-radius:6px; text-decoration:none; font-size:0.75rem; font-weight:600;">Vedi bozza</a>
            @endif
            @if ($d->status !== 'generating')
            <form method="POST" action="{{ route('admin.coverage.generate', $g) }}">@csrf
                <button type="submit" style="padding:6px 12px; background:white; color:#C26A2E; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">↻ Rigenera</button>
            </form>
            @endif
        @endif
    </div>
</div>
@endforeach
@endif

<script>
document.querySelectorAll('[data-dismiss-flash]').forEach(function (b) { b.addEventListener('click', function () { b.closest('[data-flash]').remove(); }); });

// Multi-topic: aggiungi/rimuovi righe.
(function () {
    var rows = document.getElementById('topic-rows');
    if (!rows) return;
    function bindRemove(row) {
        var b = row.querySelector('.topic-remove');
        if (b) b.addEventListener('click', function () {
            if (rows.querySelectorAll('.topic-row').length > 1) row.remove();
            else { row.querySelector('input').value = ''; }
        });
    }
    rows.querySelectorAll('.topic-row').forEach(bindRemove);
    var add = document.getElementById('topic-add');
    if (add) add.addEventListener('click', function () {
        var clone = rows.querySelector('.topic-row').cloneNode(true);
        clone.querySelector('input').value = '';
        clone.querySelector('select').value = 'secondary';
        rows.appendChild(clone);
        bindRemove(clone);
    });
})();
</script>
@endsection
