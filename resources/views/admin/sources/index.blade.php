@extends('layouts.admin')
@section('title', 'Fonti attendibili')
@section('content')

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
    <h1 style="font-size:1.4rem; color:#1A1F1F; margin:0;">&#128218; Fonti attendibili</h1>
</div>
<p style="color:#8A9696; font-size:0.85rem; margin:0 0 18px;">
    Registro delle fonti per <strong>dominio tematico</strong> (condivise tra corsi dello stesso tema).
    Lo Scout cercherà <strong>solo</strong> tra le fonti <strong>approvate</strong>. L'agente può
    <em>proporre</em> candidate: tu approvi quelle valide.
</p>

@if (session('success'))
    <div data-flash style="display:flex; gap:10px; background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
        <span style="flex:1;">{{ session('success') }}</span>
        <button type="button" data-dismiss-flash aria-label="Chiudi" style="background:none; border:none; color:#3A8C89; cursor:pointer; font-size:1rem; line-height:1;">&times;</button>
    </div>
@endif
@if (session('error'))
    <div data-flash style="display:flex; gap:10px; background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
        <span style="flex:1;">{{ session('error') }}</span>
        <button type="button" data-dismiss-flash aria-label="Chiudi" style="background:none; border:none; color:#C0392B; cursor:pointer; font-size:1rem; line-height:1;">&times;</button>
    </div>
@endif

{{-- Filtri --}}
<div style="background:white; border-radius:10px; padding:14px 16px; margin-bottom:16px; border:1px solid #E6EBEB;">
    <form method="GET" action="{{ route('admin.sources.index') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label style="font-size:0.72rem; color:#8A9696; font-weight:700; display:block;">Topic</label>
            <select name="topic" style="padding:7px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
                <option value="">— tutti —</option>
                @foreach ($topics as $t)
                    <option value="{{ $t }}" @selected($filterTopic === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:0.72rem; color:#8A9696; font-weight:700; display:block;">Stato</label>
            <select name="status" style="padding:7px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
                <option value="">— tutti —</option>
                @foreach (['suggested' => 'Suggerite', 'approved' => 'Approvate', 'rejected' => 'Rifiutate'] as $k => $v)
                    <option value="{{ $k }}" @selected($filterStatus === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Filtra</button>
        <a href="{{ route('admin.sources.index') }}" style="padding:8px 12px; color:#8A9696; font-size:0.78rem; text-decoration:none;">azzera</a>
    </form>
</div>

{{-- Aggiungi / Proponi --}}
<div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:20px;">
    <div style="flex:2; min-width:320px; background:#F5F7F7; border-radius:10px; padding:16px;">
        <div style="font-weight:700; color:#1A1F1F; font-size:0.9rem; margin-bottom:10px;">&#10133; Aggiungi fonte</div>
        <form method="POST" action="{{ route('admin.sources.store') }}" style="display:flex; flex-direction:column; gap:8px;">
            @csrf
            <input type="text" name="label" placeholder="Nome (es. arXiv — cs.AI)" required style="padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
            <input type="text" name="url_or_domain" placeholder="Dominio (arxiv.org) o URL (https://…)" required style="padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
            <div style="display:flex; gap:8px;">
                <select name="mode" style="flex:1; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
                    <option value="search">search (dominio da cercare)</option>
                    <option value="fetch">fetch (pagina specifica)</option>
                </select>
                <input type="text" name="topic" placeholder="Topic (es. agenti-ai)" required style="flex:1; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
            </div>
            <input type="text" name="notes" placeholder="Note (opzionale)" style="padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
            <div style="display:flex; justify-content:flex-end;">
                <button type="submit" style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Aggiungi (approvata)</button>
            </div>
        </form>
    </div>

    <div style="flex:1; min-width:240px; background:#FFF8EE; border-radius:10px; padding:16px; border:1px solid rgba(226,138,83,0.35);">
        <div style="font-weight:700; color:#C26A2E; font-size:0.9rem; margin-bottom:10px;">&#129302; Proponi fonti (agente)</div>
        <p style="color:#8A9696; font-size:0.75rem; margin:0 0 10px;">L'agente propone fonti autorevoli per un topic, da rivedere e approvare.</p>
        <form method="POST" action="{{ route('admin.sources.suggest') }}" style="display:flex; flex-direction:column; gap:8px;">
            @csrf
            <input type="text" name="topic" placeholder="Topic (es. agenti-ai)" required value="{{ $filterTopic }}" style="padding:8px; border:1px solid rgba(226,138,83,0.4); border-radius:6px; font-size:0.82rem;">
            <button type="submit" style="padding:8px 16px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Proponi candidate</button>
        </form>
    </div>
</div>

{{-- Lista, raggruppata per topic --}}
@php($badge = ['suggested' => ['#C26A2E','rgba(226,138,83,0.15)','Suggerita'], 'approved' => ['#3A8C89','rgba(85,177,174,0.15)','Approvata'], 'rejected' => ['#C0392B','#FBEDEC','Rifiutata']])
@forelse ($sources as $topic => $list)
<div style="margin-bottom:18px;">
    <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem; margin-bottom:8px; font-family:'JetBrains Mono','SF Mono',monospace;">{{ $topic }} <span style="color:#8A9696; font-weight:400;">({{ count($list) }})</span></div>
    <div style="display:flex; flex-direction:column; gap:6px;">
        @foreach ($list as $s)
        @php($b = $badge[$s->status] ?? ['#8A9696','#F5F7F7',$s->status])
        <div style="display:flex; align-items:center; gap:10px; background:white; border:1px solid #E6EBEB; border-radius:8px; padding:10px 12px; font-size:0.82rem; flex-wrap:wrap;">
            <span style="padding:2px 9px; border-radius:12px; font-size:0.7rem; font-weight:700; color:{{ $b[0] }}; background:{{ $b[1] }};">{{ $b[2] }}</span>
            <span style="padding:2px 8px; border-radius:6px; font-size:0.68rem; font-weight:700; color:#5A6666; background:#F5F7F7;">{{ $s->mode }}</span>
            <div style="flex:1; min-width:200px;">
                <strong style="color:#1A1F1F;">{{ $s->label }}</strong>
                <code style="display:block; color:#8A9696; font-size:0.74rem;">{{ $s->url_or_domain }}</code>
                @if($s->notes)<span style="color:#8A9696; font-size:0.72rem;">{{ $s->notes }}</span>@endif
            </div>
            <span style="color:#8A9696; font-size:0.7rem;">{{ $s->proposed_by === 'agent' ? '🤖 agente' : '👤 admin' }}</span>
            <div style="display:flex; gap:6px;">
                @if ($s->status !== 'approved')
                <form method="POST" action="{{ route('admin.sources.approve', $s) }}">@csrf @method('PATCH')
                    <button type="submit" style="padding:5px 10px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.72rem; font-weight:600; cursor:pointer;">✓ Approva</button>
                </form>
                @endif
                @if ($s->status !== 'rejected')
                <form method="POST" action="{{ route('admin.sources.reject', $s) }}">@csrf @method('PATCH')
                    <button type="submit" style="padding:5px 10px; background:white; color:#C26A2E; border:1px solid #E28A53; border-radius:6px; font-size:0.72rem; font-weight:600; cursor:pointer;">✗ Rifiuta</button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.sources.destroy', $s) }}" onsubmit="return confirm('Rimuovere questa fonte?');">@csrf @method('DELETE')
                    <button type="submit" style="padding:5px 10px; background:white; color:#C52A2A; border:1px solid #E28282; border-radius:6px; font-size:0.72rem; font-weight:600; cursor:pointer;">🗑</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@empty
<div style="color:#8A9696; font-size:0.85rem; padding:24px; text-align:center; background:#F5F7F7; border-radius:8px;">
    Nessuna fonte{{ $filterTopic || $filterStatus ? ' con questi filtri' : '' }}. Aggiungine una a mano o usa «Proponi candidate».
</div>
@endforelse

<script>
document.querySelectorAll('[data-dismiss-flash]').forEach(function (btn) {
    btn.addEventListener('click', function () { var b = btn.closest('[data-flash]'); if (b) b.remove(); });
});
</script>

@endsection
