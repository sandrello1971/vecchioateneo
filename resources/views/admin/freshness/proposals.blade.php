@extends('layouts.admin')
@section('title', 'Aggiornamenti corsi')
@section('content')

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
    <h1 style="font-size:1.4rem; color:#1A1F1F; margin:0;">&#128260; Aggiornamenti corsi — coda proposte</h1>
</div>
<p style="color:#8A9696; font-size:0.85rem; margin:0 0 18px;">
    L'agente <strong>propone</strong>; tu <strong>disponi</strong>. Verifica la fonte di ogni proposta
    <em>prima</em> di approvarla: nessuna modifica raggiunge un corso senza la tua approvazione. L'applicazione
    al contenuto avviene in un passo separato.
</p>

{{-- Notifiche transitorie: DISMISSIBILI (X). Lo storico durevole delle analisi è il pannello sotto. --}}
@if (session('success'))
    <div data-flash style="display:flex; align-items:flex-start; gap:10px; background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
        <span style="flex:1;">{{ session('success') }}</span>
        <button type="button" data-dismiss-flash aria-label="Chiudi" style="background:none; border:none; color:#3A8C89; cursor:pointer; font-size:1rem; line-height:1; padding:0;">&times;</button>
    </div>
@endif
@if (session('error'))
    <div data-flash style="display:flex; align-items:flex-start; gap:10px; background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
        <span style="flex:1;">{{ session('error') }}</span>
        <button type="button" data-dismiss-flash aria-label="Chiudi" style="background:none; border:none; color:#C0392B; cursor:pointer; font-size:1rem; line-height:1; padding:0;">&times;</button>
    </div>
@endif

{{-- Banner LIVE: mostrato dal polling JS finché un'analisi è in corso (auto-aggiornato). --}}
<div id="run-live-banner" style="display:none; align-items:center; gap:10px; background:#FFF8EE; border:1px solid rgba(226,138,83,0.45); color:#C26A2E; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem; font-weight:700;">
    <span class="run-live-spinner" style="display:inline-block; width:12px; height:12px; border:2px solid rgba(226,138,83,0.35); border-top-color:#C26A2E; border-radius:50%; animation:run-spin 0.8s linear infinite;"></span>
    <span id="run-live-text">Analisi in corso…</span>
</div>
<style>@keyframes run-spin { to { transform: rotate(360deg); } }</style>

{{-- Storico analisi: spazio DEDICATO e durevole (run dell'agente). Si auto-aggiorna col polling. --}}
<div style="background:white; border-radius:10px; padding:14px 16px; margin-bottom:18px; border:1px solid #E6EBEB;">
    <div style="font-weight:700; color:#1A1F1F; margin-bottom:10px;">&#128202; Storico analisi</div>
    <div id="runs-history">
        @include('admin.freshness._runs_history', ['recentRuns' => $recentRuns ?? collect()])
    </div>
</div>

{{-- P25.B-a — Due tab per sorgente: Formatore (instructor) / Studente (student). --}}
@php($tabBase = 'display:inline-block; padding:9px 18px; border-radius:8px 8px 0 0; font-size:0.85rem; font-weight:700; text-decoration:none; border:1px solid #E6EBEB; border-bottom:none; margin-right:4px;')
<div style="margin-bottom:0; border-bottom:1px solid #E6EBEB;">
    <a href="{{ route('admin.freshness.proposals.index', ['source' => 'instructor']) }}"
       style="{{ $tabBase }} {{ $source === 'instructor' ? 'background:#0E3F3D; color:#D6F0EE;' : 'background:#F5F7F7; color:#5A6666;' }}">
        📘 Formatore <span style="opacity:.8;">({{ $pendingCounts['instructor'] }})</span>
    </a>
    <a href="{{ route('admin.freshness.proposals.index', ['source' => 'student']) }}"
       style="{{ $tabBase }} {{ $source === 'student' ? 'background:#3B2E5A; color:#EDE7F6;' : 'background:#F5F7F7; color:#5A6666;' }}">
        🎓 Studente <span style="opacity:.8;">({{ $pendingCounts['student'] }})</span>
    </a>
</div>
<p style="color:#8A9696; font-size:0.78rem; margin:8px 0 16px;">
    Stai vedendo la sorgente <strong>{{ $source === 'student' ? 'STUDENTE (materiale fruito dagli studenti)' : 'FORMATORE (manuale del docente)' }}</strong>.
    Apply e rollback agiscono <strong>solo</strong> su questa sorgente — i due flussi non si mescolano mai.
</p>

{{-- P25.3d — Controlli manuali (async) + cadenza scheduler per corso --}}
<div style="background:white; border-radius:10px; padding:16px 18px; margin-bottom:20px;">
    <div style="font-weight:700; color:#1A1F1F; margin-bottom:4px;">&#9881; Controlli & cadenze</div>
    <p style="color:#8A9696; font-size:0.78rem; margin:0 0 12px;">
        "Lancia ora" avvia un controllo in background (può richiedere qualche minuto: genera solo proposte, non applica nulla).
        La cadenza pianifica i controlli automatici. Default <strong>off</strong> per contenere i costi — abilitala dove serve.
    </p>
    <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
        <thead>
            <tr style="text-align:left; color:#8A9696; border-bottom:1px solid #E6EBEB;">
                <th style="padding:6px 8px;">Corso</th>
                <th style="padding:6px 8px;">Audience</th>
                <th style="padding:6px 8px;">Cadenza</th>
                <th style="padding:6px 8px;">Ultimo controllo</th>
                <th style="padding:6px 8px; text-align:right;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allCourses as $c)
                @php($cfg = $c->freshnessConfig)
                @php($aud = optional($cfg)->audience ?? 'adult')
                <tr style="border-bottom:1px solid #F0F4F4; {{ $aud === 'minor' ? 'background:#FCF4F3;' : '' }}">
                    <td style="padding:6px 8px; color:#1A1F1F;">{{ $c->name }}</td>
                    <td style="padding:6px 8px;">
                        <form method="POST" action="{{ route('admin.freshness.proposals.audience', $c) }}" style="margin:0; display:flex; gap:6px; align-items:center;">
                            @csrf
                            @include('admin.freshness._audience_badge', ['audience' => $aud])
                            <select name="audience" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.78rem;">
                                <option value="adult" @selected($aud === 'adult')>adulti</option>
                                <option value="minor" @selected($aud === 'minor')>minori</option>
                            </select>
                            <button type="submit" style="padding:4px 10px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; cursor:pointer;">Salva</button>
                        </form>
                    </td>
                    <td style="padding:6px 8px;">
                        <form method="POST" action="{{ route('admin.freshness.proposals.cadence', $c) }}" style="margin:0; display:flex; gap:6px;">
                            @csrf
                            <select name="cadence" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.78rem;">
                                @foreach (['off' => 'off', 'weekly' => 'settimanale', 'monthly' => 'mensile', 'quarterly' => 'trimestrale'] as $val => $label)
                                    <option value="{{ $val }}" @selected(optional($cfg)->cadence === $val || (is_null(optional($cfg)->cadence) && $val === 'off'))>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" style="padding:4px 10px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; cursor:pointer;">Salva</button>
                        </form>
                    </td>
                    <td style="padding:6px 8px; color:#8A9696;">{{ optional(optional($cfg)->last_run_at)->format('d/m/Y H:i') ?? 'mai' }}</td>
                    <td style="padding:6px 8px; text-align:right; white-space:nowrap;">
                        <form method="POST" action="{{ route('admin.freshness.proposals.run') }}" style="margin:0 0 4px; display:inline-block;">
                            @csrf
                            <input type="hidden" name="course_id" value="{{ $c->id }}">
                            <button type="submit" style="padding:5px 12px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.78rem; cursor:pointer;">&#9658; Lancia ora</button>
                        </form>
                        {{-- Apply/rollback PER-SORGENTE (la sorgente è quella del tab attivo). --}}
                        @if ($c->approved_count > 0)
                            <form method="POST" action="{{ route('admin.freshness.proposals.apply', $c) }}" style="margin:0 0 4px; display:inline-block;"
                                  onsubmit="return {{ $aud === 'minor' ? 'confirm(\'Stai per applicare modifiche (' . $source . ') a un corso per MINORI. Confermi?\')' : 'true' }};">
                                @csrf
                                <input type="hidden" name="content_source" value="{{ $source }}">
                                @if ($aud === 'minor')
                                    {{-- Gate 2 (minori): conferma esplicita aggiuntiva richiesta. --}}
                                    <label style="font-size:0.7rem; color:#7B1E1E; display:block; margin-bottom:3px;">
                                        <input type="checkbox" name="confirm_minor" value="1" required> ⚠ Confermo l'applicazione ({{ $source === 'student' ? 'STUDENTE' : 'formatore' }}) a un corso per MINORI
                                    </label>
                                @endif
                                <button type="submit" style="padding:5px 12px; background:{{ $aud === 'minor' ? '#7B1E1E' : '#1E8449' }}; color:white; border:none; border-radius:6px; font-size:0.78rem; cursor:pointer;">
                                    &#10003; Applica {{ $source === 'student' ? 'STUDENTE' : 'formatore' }} ({{ $c->approved_count }})
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.freshness.proposals.rollback', $c) }}" style="margin:0; display:inline-block;"
                              onsubmit="return confirm('Rollback alla versione precedente ({{ $source }}) di «{{ $c->name }}»?');">
                            @csrf
                            <input type="hidden" name="content_source" value="{{ $source }}">
                            <button type="submit" style="padding:5px 12px; background:white; color:#8A6D1B; border:1px solid #C9A227; border-radius:6px; font-size:0.75rem; cursor:pointer;">&#8617; Rollback {{ $source === 'student' ? 'studente' : 'formatore' }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- P25.B-b.3 — Alert orfani: modifiche discente già LIVE il cui padre formatore è stato annullato. --}}
@if ($orphanAlerts->isNotEmpty())
    <div style="background:#FBEDEC; border:1px solid #C0392B; border-radius:10px; padding:14px 18px; margin-bottom:20px;">
        <div style="font-weight:700; color:#7B1E1E; margin-bottom:6px;">⚠ {{ $orphanAlerts->count() }} modifiche discente LIVE con padre formatore annullato</div>
        <p style="color:#7B1E1E; font-size:0.8rem; margin:0 0 8px;">Queste modifiche sono già applicate sul materiale studente, ma l'aggiornamento formatore da cui nascevano è stato rifiutato o rollbackato. Valuta il <strong>rollback studente</strong>.</p>
        @foreach ($orphanAlerts as $o)
            <div style="font-size:0.8rem; color:#1A1F1F; padding:4px 0; border-top:1px solid #F2C9C4;">
                «{{ \Illuminate\Support\Str::limit($o->before, 80) }}» → «{{ \Illuminate\Support\Str::limit($o->after, 80) }}» <span style="color:#8A9696;">({{ optional($o->course)->name }} · {{ optional($o->orphan_reason) }})</span>
            </div>
        @endforeach
    </div>
@endif

{{-- P25.B-b.3 — Candidate coordinate da confermare (status='matched', generate dal matching). --}}
@if ($candidates->isNotEmpty())
    <div style="background:white; border-radius:10px; padding:16px 18px; margin-bottom:20px; border:1px solid #C9A227;">
        <div style="font-weight:700; color:#1A1F1F; margin-bottom:4px;">🔗 Candidate coordinate — da confermare</div>
        <p style="color:#8A9696; font-size:0.78rem; margin:0 0 14px;">Porzioni discente trovate dal matching su un aggiornamento formatore approvato. <strong>Conferma</strong> per avviare la riscrittura conservativa (→ diventa proposta da approvare), o <strong>Scarta</strong>.</p>
        @foreach ($candidates as $courseId => $items)
            <div style="margin-bottom:10px;">
                <div style="font-family:'JetBrains Mono',monospace; font-weight:700; color:#1A1F1F; font-size:0.85rem; margin-bottom:8px;">{{ optional($items->first()->course)->name }}</div>
                @foreach ($items as $cand)
                    <div style="border:1px solid #E6EBEB; border-radius:8px; padding:12px 14px; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                            <span style="background:#3B2E5A; color:#EDE7F6; font-size:0.66rem; font-weight:700; padding:2px 9px; border-radius:10px;">↳ DA AGGIORNAMENTO FORMATORE</span>
                            <span style="color:#8A9696; font-size:0.75rem;">confidenza match: <strong style="color:#1A1F1F;">{{ is_null($cand->match_confidence) ? '—' : round($cand->match_confidence * 100) . '%' }}</strong></span>
                            <span style="font-size:0.66rem; font-weight:700; padding:2px 8px; border-radius:10px; {{ $cand->match_trust === 'low' ? 'background:#F4E1C1; color:#8A6D1B;' : 'background:#EEF3F3; color:#5A6666;' }}">trust: {{ $cand->match_trust ?? 'n/d' }}</span>
                        </div>
                        @if (optional($cand->parentProposal))
                            <div style="font-size:0.78rem; color:#5A6666; margin-bottom:8px;">Fatto aggiornato (formatore): «{{ \Illuminate\Support\Str::limit(optional($cand->parentProposal)->after, 110) }}»</div>
                        @endif
                        <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:#5A6666; margin-bottom:4px;">Porzione discente (da riscrivere)</div>
                        <div style="background:#F5F7F7; border:1px solid #E6EBEB; border-radius:6px; padding:10px; font-size:0.85rem; color:#1A1F1F; white-space:pre-wrap;">{{ $cand->before }}</div>
                        <div style="margin-top:10px; display:flex; gap:8px;">
                            <form method="POST" action="{{ route('admin.freshness.proposals.confirm', $cand) }}" style="margin:0;">
                                @csrf @method('PATCH')
                                <button type="submit" style="padding:7px 14px; background:#1E8449; color:white; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">&#10003; Conferma (riscrivi)</button>
                            </form>
                            <form method="POST" action="{{ route('admin.freshness.proposals.reject', $cand) }}" style="margin:0;">
                                @csrf @method('PATCH')
                                <button type="submit" style="padding:7px 14px; background:white; color:#C0392B; border:1px solid #C0392B; border-radius:6px; font-size:0.8rem; cursor:pointer;">&#10007; Scarta</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif

@php($flatCount = $proposals->flatten()->count())

@if ($flatCount === 0)
    <div style="background:white; border-radius:10px; padding:40px; text-align:center; color:#8A9696;">
        Nessuna proposta in attesa. Quando l'agente troverà contenuti obsoleti, le proposte compariranno qui.
    </div>
@else
    {{-- Form massivo (vuoto): le checkbox e i bottoni vi si agganciano via attributo form= --}}
    <form id="bulkForm" method="POST" action="{{ route('admin.freshness.proposals.bulk') }}">@csrf</form>

    <div style="display:flex; gap:10px; align-items:center; margin-bottom:16px;">
        <button type="submit" form="bulkForm" name="action" value="approve"
                style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">
            &#10003; Approva selezionate
        </button>
        <button type="submit" form="bulkForm" name="action" value="reject"
                style="padding:8px 14px; background:white; color:#C0392B; border:1px solid #C0392B; border-radius:6px; font-size:0.8rem; cursor:pointer;">
            &#10007; Rifiuta selezionate
        </button>
        <span style="color:#8A9696; font-size:0.78rem;">{{ $flatCount }} proposte in attesa</span>
    </div>

    @foreach ($proposals as $courseId => $items)
        @php($course = $items->first()->course)
        @php($audience = optional($course->freshnessConfig)->audience ?? $items->first()->audience)
        <div style="background:white; border-radius:10px; padding:16px 18px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #E6EBEB;">
                <span style="font-family:'JetBrains Mono',monospace; font-weight:700; color:#1A1F1F;">{{ $course->name }}</span>
                @include('admin.freshness._audience_badge', ['audience' => $audience])
                <span style="margin-left:auto; color:#8A9696; font-size:0.78rem;">{{ $items->count() }} proposte</span>
            </div>

            @foreach ($items as $p)
                <div style="border:1px solid #E6EBEB; border-radius:8px; padding:12px 14px; margin-bottom:12px;">
                    {{-- Intestazione riga: selezione + meta + fonte --}}
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                        <input type="checkbox" name="ids[]" value="{{ $p->id }}" form="bulkForm" style="width:16px; height:16px;">
                        @include('admin.freshness._source_badge', ['source' => $p->content_source])
                        <span style="background:#EEF3F3; color:#1A1F1F; font-size:0.7rem; padding:2px 8px; border-radius:10px; text-transform:uppercase; letter-spacing:0.05em;">{{ optional($p->claim)->category ?? '—' }}</span>
                        <code style="color:#8A9696; font-size:0.75rem;">{{ $p->content_source === 'student' ? 'modulo' : $p->block_id }}@if(!is_null($p->sentence_ref)) · frase {{ $p->sentence_ref }}@endif</code>
                        @include('admin.freshness._audience_badge', ['audience' => $p->audience])
                        <span style="color:#8A9696; font-size:0.75rem;">confidenza: <strong style="color:#1A1F1F;">{{ is_null($p->confidence) ? '—' : round($p->confidence * 100) . '%' }}</strong></span>
                        @if ($p->source)
                            <a href="{{ $p->source }}" target="_blank" rel="noopener noreferrer"
                               style="margin-left:auto; font-size:0.78rem; color:#55B1AE; text-decoration:none; border:1px solid #55B1AE; padding:3px 10px; border-radius:6px;">
                                &#128279; Fonte ({{ $p->source_type ?? 'n/d' }}) &#8599;
                            </a>
                        @else
                            <span style="margin-left:auto; font-size:0.75rem; color:#C0392B;">nessuna fonte</span>
                        @endif
                    </div>

                    {{-- Diff before/after affiancati --}}
                    <div style="display:flex; gap:14px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:260px;">
                            <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:#C0392B; margin-bottom:4px;">Before (attuale)</div>
                            <div style="background:#FBEDEC; border:1px solid #F2C9C4; border-radius:6px; padding:10px; font-size:0.85rem; color:#1A1F1F; white-space:pre-wrap;">{{ $p->before }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.freshness.proposals.approve', $p) }}" style="flex:1; min-width:260px; margin:0;">
                            @csrf @method('PATCH')
                            <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:#1E8449; margin-bottom:4px;">After (proposto — modificabile)</div>
                            <textarea name="after" rows="3" style="width:100%; background:#EDF7F0; border:1px solid #BFE3CC; border-radius:6px; padding:10px; font-size:0.85rem; color:#1A1F1F; box-sizing:border-box; resize:vertical;">{{ $p->after }}</textarea>
                            <button type="submit" style="margin-top:8px; padding:7px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                                &#10003; Approva (con eventuale modifica)
                            </button>
                        </form>
                    </div>

                    @if ($p->reason)
                        <div style="margin-top:10px; font-size:0.8rem; color:#5A6666;"><strong>Motivazione:</strong> {{ $p->reason }}</div>
                    @endif

                    <div style="margin-top:10px; display:flex; gap:8px;">
                        <form method="POST" action="{{ route('admin.freshness.proposals.reject', $p) }}" style="margin:0;">
                            @csrf @method('PATCH')
                            <button type="submit" style="padding:7px 14px; background:white; color:#C0392B; border:1px solid #C0392B; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                                &#10007; Rifiuta
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif

<script>
(function () {
    // Notifiche dismissibili (X).
    document.querySelectorAll('[data-dismiss-flash]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = btn.closest('[data-flash]');
            if (box) box.remove();
        });
    });

    // Indicatore LIVE: polling dello stato run, auto-aggiorna storico + banner senza reload.
    var statusUrl = "{{ route('admin.freshness.proposals.runs-status') }}";
    var banner = document.getElementById('run-live-banner');
    var bannerText = document.getElementById('run-live-text');
    var history = document.getElementById('runs-history');
    var timer = null;
    var wasRunning = false; // un'analisi è stata in corso durante questa sessione di polling?

    function schedule(ms) { clearTimeout(timer); timer = setTimeout(poll, ms); }

    function poll() {
        fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { schedule(20000); return; }
                if (history && typeof data.html === 'string') history.innerHTML = data.html;
                if (data.running) {
                    wasRunning = true;
                    bannerText.textContent = 'Analisi in corso' + (data.banner ? ' per ' + data.banner : '') + '…';
                    banner.style.display = 'flex';
                    schedule(4000);   // poll veloce mentre lavora
                } else {
                    // Un'analisi era in corso e ora è finita → ricarica UNA volta per mostrare
                    // le proposte appena generate (niente refresh manuale).
                    if (wasRunning) { window.location.reload(); return; }
                    banner.style.display = 'none';
                    schedule(20000);  // idle: poll lento
                }
            })
            .catch(function () { schedule(20000); });
    }

    schedule(1500); // primo controllo poco dopo il caricamento
})();
</script>

@endsection
