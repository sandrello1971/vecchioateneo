@extends('layouts.admin')
@section('title', $course->name . ' — Moduli')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <a href="/admin/courses" style="color:#8A9696; font-size:0.8rem;">&larr; Corsi</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            {{ $course->icon }} {{ $course->name }} — Moduli
        </h2>
    </div>
    <a href="/admin/courses/{{ $course->id }}/modules/create"
       style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
        + Nuovo modulo
    </a>
</div>

<div style="display:flex; flex-direction:column; gap:8px;">
    @forelse($modules as $module)
    <div style="background:white; border-radius:10px; overflow:hidden;">
        <div style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:32px; height:32px; border-radius:50%; background:#E8F5F5; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.875rem; color:#55B1AE;">
                    {{ $module->sort_order }}
                </div>
                <div>
                    <div style="font-weight:600; color:#1A1F1F;">{{ $module->title }}</div>
                    <div style="font-size:0.75rem; color:#8A9696;">
                        {{ $module->duration_minutes ? $module->duration_minutes.' min' : '' }}
                        &middot; {{ $module->content ? 'Contenuto presente' : '&#9888; Nessun contenuto' }}
                        &middot; {{ $module->materials->count() }} materiali
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}/edit"
                   style="padding:6px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                    Modifica
                </a>
                <a href="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}/edit#content"
                   style="padding:6px 14px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                    Contenuto
                </a>
            </div>
        </div>
    </div>
    @empty
    <div style="background:white; border-radius:10px; padding:32px; text-align:center; color:#8A9696;">
        Nessun modulo. <a href="/admin/courses/{{ $course->id }}/modules/create" style="color:#55B1AE;">Crea il primo &rarr;</a>
    </div>
    @endforelse
</div>

{{-- ==================== DOCUMENTO PDF DEL CORSO (P29 Fase 2) — hash aggregato, stale-then-regenerate ==================== --}}
@php $courseDoc = $course->document; @endphp
<div style="background:white; border-radius:10px; padding:20px; margin-top:16px;"
     x-data="courseDocumentStatus('{{ $courseDoc?->status ?? 'none' }}')">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; flex:1;">📄 Documento del corso</h3>
        <template x-if="status==='generating'">
            <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.85rem; font-weight:600;">
                <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;"></span>
                <span>Generazione in corso…</span>
            </span>
        </template>
        <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.85rem;">&#10003; Pronto</span></template>
        <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.85rem;">&#10007; Generazione fallita</span></template>
    </div>
    <p style="font-size:0.78rem; color:#8A9696; margin-bottom:12px;">Un unico PDF brandizzato (tema Noscite) con tutti i moduli del corso, in ordine.</p>

    @if(($courseDoc?->status ?? null) === 'failed' && ($courseDoc->generation_meta['failure_reason'] ?? null))
        <p style="margin-bottom:10px; font-size:0.82rem; color:#A8521F;">{{ $courseDoc->generation_meta['failure_reason'] }}</p>
    @endif

    {{-- Badge stale: obsoleto se un modulo è cambiato/aggiunto/rimosso/riordinato dopo la generazione. --}}
    @if(($courseDoc?->status ?? null) === 'ready')
        @if($courseDoc->isStale())
            <div style="background:rgba(226,138,83,0.12); border-left:4px solid #E28A53; padding:10px 14px; border-radius:6px; margin-bottom:12px; font-size:0.82rem; color:#A8521F;">
                <strong>⚠ OBSOLETO</strong> — il corso è cambiato dopo questa versione (modulo modificato, aggiunto, rimosso o riordinato). Rigenera per allineare.
            </div>
        @else
            <div style="font-size:0.75rem; color:#3A8C89; margin-bottom:12px; font-weight:600;">&#10003; AGGIORNATO — {{ $courseDoc->generation_meta['modules'] ?? '' }} moduli allineati al contenuto attuale.</div>
        @endif
    @endif

    <div x-show="status!=='generating'">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        @if(!$courseDoc || $courseDoc->status === 'pending' || $courseDoc->status === 'failed')
            <form method="POST" action="{{ route('admin.courses.document.generate', $course) }}"
                  onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='⏳ Avvio…';">
                @csrf
                <button type="submit" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">{{ ($courseDoc?->status ?? null) === 'failed' ? '↻ Riprova' : '✨ Genera documento' }}</button>
            </form>
        @elseif($courseDoc->status === 'ready')
            <a href="{{ route('admin.courses.document.download', $course) }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 16px; background:#3A8C89; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11015; Scarica .pdf</a>
            <form method="POST" action="{{ route('admin.courses.document.regenerate', $course) }}"
                  onsubmit="return confirm('Rigenerare il documento del corso? Il file attuale verrà sovrascritto.') && (this.querySelector('button').disabled=true || true);">
                @csrf
                <button type="submit" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Rigenera</button>
            </form>
        @endif
    </div>
    </div>
</div>

<div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); border-radius:10px; padding:20px; margin-top:16px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <div style="color:#55B1AE; font-weight:700;">&#10022; Genera quiz con Claude AI</div>
        <div style="color:#8A9696; font-size:0.8rem;">Crea automaticamente domande basate sul contenuto dei moduli</div>
    </div>
    <form method="POST" action="/admin/courses/{{ $course->id }}/generate-quiz">
        @csrf
        <div style="display:flex; align-items:center; gap:10px;">
            <label style="color:#8A9696; font-size:0.75rem;">Pool</label>
            <select name="num_questions" title="Dimensione del pool (domande da generare)" style="padding:6px 10px; border-radius:6px; border:none; font-size:0.8rem;">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="15">15</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="40">40</option>
                <option value="50">50</option>
            </select>
            <label style="color:#8A9696; font-size:0.75rem;">Estrai per tentativo</label>
            <input type="number" name="questions_per_attempt" min="1" placeholder="tutte"
                   title="Quante domande estrarre a caso per ogni tentativo (vuoto = tutte)"
                   style="width:80px; padding:6px 10px; border-radius:6px; border:none; font-size:0.8rem;">
            <button type="submit" style="padding:8px 20px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Genera quiz &rarr;
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// P29 — polling stato documento del corso: generating → ready/failed (poi reload).
function courseDocumentStatus(initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch('{{ route('admin.courses.document.status', $course) }}', {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') { clearInterval(timer); window.location.reload(); }
                } catch(e) {}
            }, 5000);
        },
    };
}
</script>
@endpush
@endsection
