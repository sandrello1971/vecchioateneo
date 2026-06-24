@extends('layouts.admin')
@section('title', 'Bozza — ' . $gap->title)
@section('content')

<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
    <a href="{{ route('admin.coverage.show', $gap->course) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Copertura</a>
    <h1 style="font-size:1.25rem; color:#1A1F1F; margin:0;">&#9998; Bozza: {{ $gap->title }}</h1>
</div>
<p style="color:#8A9696; font-size:0.8rem; margin:4px 0 14px;">
    Doppia versione nel taglio del corso. <strong>Non è inserita nel corso</strong>: revisiona, modifica, approva (pronta per la Fase D) o scarta.
</p>

@if (session('success'))
    <div style="background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:14px; font-size:0.85rem;">{{ session('success') }}</div>
@endif

@if (!$draft || $draft->status === 'generating')
    <div style="background:#FFF8EE; border:1px solid rgba(226,138,83,0.4); color:#C26A2E; padding:14px; border-radius:8px; font-size:0.85rem;">
        ⏳ Bozza in generazione… ricarica la pagina tra poco.
    </div>
@elseif ($draft->status === 'failed')
    <div style="background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:14px; border-radius:8px; font-size:0.85rem;">
        ✗ Generazione fallita: <span style="font-family:'JetBrains Mono',monospace; font-size:0.78rem;">{{ $draft->error }}</span>
        <form method="POST" action="{{ route('admin.coverage.generate', $gap) }}" style="margin-top:10px;">@csrf
            <button type="submit" style="padding:7px 14px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.78rem; font-weight:700; cursor:pointer;">↻ Rigenera</button>
        </form>
    </div>
@else
    @php($status = $draft->status)
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
        <span style="padding:2px 10px; border-radius:12px; font-size:0.72rem; font-weight:700; color:{{ $status === 'approved' ? '#1A7F5A' : '#3A8C89' }}; background:#F5F7F7;">
            {{ $status === 'approved' ? '✓ approvata — pronta per inserimento (Fase D)' : 'bozza in revisione' }}
        </span>
        @if ($draft->note)<span style="color:#8A9696; font-size:0.76rem;">Nota: {{ $draft->note }}</span>@endif
    </div>

    {{-- Tabs --}}
    <div style="display:flex; gap:4px; margin-bottom:0; border-bottom:1px solid #E6EBEB;">
        <button type="button" class="draft-tab" data-target="tab-form" style="padding:8px 16px; border:1px solid #E6EBEB; border-bottom:none; border-radius:8px 8px 0 0; background:#0E3F3D; color:#D6F0EE; font-weight:700; font-size:0.82rem; cursor:pointer;">📘 Formatore</button>
        <button type="button" class="draft-tab" data-target="tab-stud" style="padding:8px 16px; border:1px solid #E6EBEB; border-bottom:none; border-radius:8px 8px 0 0; background:#F5F7F7; color:#5A6666; font-weight:700; font-size:0.82rem; cursor:pointer;">🎓 Studente</button>
    </div>

    <form method="POST" action="{{ route('admin.coverage.draft.update', $draft) }}">
        @csrf @method('PUT')
        <div style="background:white; border:1px solid #E6EBEB; border-top:none; border-radius:0 0 10px 10px; padding:16px;">
            <div id="tab-form" class="draft-pane">
                <div style="font-size:0.72rem; color:#8A9696; font-weight:700; margin-bottom:6px;">ANTEPRIMA</div>
                <div style="border:1px solid #F0F4F4; border-radius:8px; padding:12px; margin-bottom:12px; font-size:0.88rem; line-height:1.55;">{!! $draft->formatore_html !!}</div>
                <div style="font-size:0.72rem; color:#8A9696; font-weight:700; margin-bottom:6px;">HTML (modificabile)</div>
                <textarea name="formatore_html" rows="12" style="width:100%; font-family:'JetBrains Mono',monospace; font-size:0.78rem; padding:10px; border:1px solid #E8F5F5; border-radius:6px;">{{ $draft->formatore_html }}</textarea>
            </div>
            <div id="tab-stud" class="draft-pane" style="display:none;">
                <div style="font-size:0.72rem; color:#8A9696; font-weight:700; margin-bottom:6px;">ANTEPRIMA</div>
                <div style="border:1px solid #F0F4F4; border-radius:8px; padding:12px; margin-bottom:12px; font-size:0.88rem; line-height:1.55;">{!! $draft->studente_html !!}</div>
                <div style="font-size:0.72rem; color:#8A9696; font-weight:700; margin-bottom:6px;">HTML (modificabile)</div>
                <textarea name="studente_html" rows="12" style="width:100%; font-family:'JetBrains Mono',monospace; font-size:0.78rem; padding:10px; border:1px solid #E8F5F5; border-radius:6px;">{{ $draft->studente_html }}</textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:12px;">
                <button type="submit" style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">💾 Salva modifiche</button>
            </div>
        </div>
    </form>

    <div style="display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;">
        @if ($status !== 'approved')
        <form method="POST" action="{{ route('admin.coverage.draft.approve', $draft) }}">@csrf @method('PATCH')
            <button type="submit" style="padding:8px 16px; background:#1A7F5A; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:700; cursor:pointer;">✓ Approva bozza</button>
        </form>
        @endif
        <form method="POST" action="{{ route('admin.coverage.generate', $gap) }}">@csrf
            <button type="submit" style="padding:8px 16px; background:white; color:#C26A2E; border:1px solid #E28A53; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">↻ Rigenera</button>
        </form>
        <form method="POST" action="{{ route('admin.coverage.draft.discard', $draft) }}" onsubmit="return confirm('Scartare questa bozza?');">@csrf @method('PATCH')
            <button type="submit" style="padding:8px 16px; background:white; color:#C52A2A; border:1px solid #E28282; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">✗ Scarta</button>
        </form>
    </div>

    {{-- Fasi C+D — Posizione (HITL) + Inserimento reversibile. Solo su bozza approvata. --}}
    @if ($status === 'approved')
    <div style="background:white; border:1px solid #E6EBEB; border-radius:10px; padding:16px; margin-top:18px;">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
            <span style="font-weight:700; color:#1A1F1F;">📍 Posizione &amp; inserimento</span>
            <form method="POST" action="{{ route('admin.coverage.place.propose', $gap) }}" style="margin-left:auto;">@csrf
                <button type="submit" style="padding:6px 12px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.76rem; font-weight:600; cursor:pointer;">🤖 Proponi posizione</button>
            </form>
        </div>

        @if ($insertion)
            <div style="background:rgba(85,177,174,0.10); border:1px solid rgba(85,177,174,0.4); border-radius:8px; padding:12px;">
                ✓ <strong>Inserito</strong> nel corso — formatore v{{ $insertion->formatore_version_from }} → v{{ $insertion->formatore_version_to }}@if($insertion->student_version_to), studente v{{ $insertion->student_version_from }} → v{{ $insertion->student_version_to }}@endif.
                <form method="POST" action="{{ route('admin.coverage.revert', $insertion) }}" style="margin-top:10px;" onsubmit="return confirm('Annullare l\'inserimento? Il corso torna allo stato precedente.');">@csrf
                    <button type="submit" style="padding:7px 14px; background:white; color:#C52A2A; border:1px solid #E28282; border-radius:6px; font-size:0.78rem; font-weight:700; cursor:pointer;">↩ Annulla inserimento</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('admin.coverage.place.confirm', $gap) }}" style="display:flex; flex-direction:column; gap:10px;">
                @csrf @method('PUT')
                <div>
                    <label style="font-size:0.72rem; color:#8A9696; font-weight:700; display:block;">Formatore — inserisci DOPO il blocco</label>
                    <select name="place_formatore_block_id" required style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
                        <option value="">— scegli —</option>
                        @foreach ($headings as $h)
                            <option value="{{ $h['id'] }}" @selected($draft->place_formatore_block_id === $h['id'])>[{{ $h['id'] }}] {{ \Illuminate\Support\Str::limit($h['text'], 70) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:200px;">
                        <label style="font-size:0.72rem; color:#8A9696; font-weight:700; display:block;">Studente — modulo (opzionale)</label>
                        <select name="place_student_module_id" style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem;">
                            <option value="">— nessuno —</option>
                            @foreach ($modules as $m)
                                <option value="{{ $m->id }}" @selected($draft->place_student_module_id === $m->id)>{{ $m->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label style="font-size:0.72rem; color:#8A9696; font-weight:700; display:block;">Studente — ancora (frase verbatim del modulo, dopo cui inserire)</label>
                    <textarea name="place_student_anchor" rows="2" style="width:100%; font-size:0.8rem; padding:8px; border:1px solid #E8F5F5; border-radius:6px;">{{ $draft->place_student_anchor }}</textarea>
                </div>
                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">Conferma posizione</button>
                </div>
            </form>

            @if ($draft->placement_confirmed)
            <form method="POST" action="{{ route('admin.coverage.insert', $gap) }}" style="margin-top:12px; border-top:1px solid #F0F4F4; padding-top:12px;">
                @csrf
                @if ($isMinor)
                <label style="display:flex; align-items:center; gap:8px; color:#C26A2E; font-weight:700; font-size:0.8rem; margin-bottom:8px;">
                    <input type="checkbox" name="minor_confirmed" value="1"> ⚠ Corso per MINORI: confermo l'inserimento
                </label>
                @endif
                <button type="submit" style="padding:9px 18px; background:#1A7F5A; color:white; border:none; border-radius:6px; font-size:0.82rem; font-weight:700; cursor:pointer;">⤵ Inserisci nel corso (reversibile)</button>
                <span style="color:#8A9696; font-size:0.74rem; margin-left:8px;">append-only, annullabile in un click.</span>
            </form>
            @else
            <p style="color:#8A9696; font-size:0.76rem; margin:10px 0 0;">Conferma la posizione per abilitare l'inserimento.</p>
            @endif
        @endif
    </div>
    @endif
@endif

<script>
document.querySelectorAll('.draft-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.draft-pane').forEach(function (p) { p.style.display = 'none'; });
        document.getElementById(btn.dataset.target).style.display = 'block';
        document.querySelectorAll('.draft-tab').forEach(function (b) { b.style.background = '#F5F7F7'; b.style.color = '#5A6666'; });
        btn.style.background = '#0E3F3D'; btn.style.color = '#D6F0EE';
    });
});
</script>
@endsection
