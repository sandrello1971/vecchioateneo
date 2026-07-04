@extends('layouts.docente')
@section('title', $document->title)
@section('breadcrumb', 'Materiali / ' . $document->title)
@section('content')
<div style="max-width:980px;" x-data="materialStatus('{{ $document->id }}', '{{ $document->status }}')">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.materials.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Materiali</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $document->title }}</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">{{ $document->source_type }} · {{ $document->subject->name ?? 'nessuna materia' }}</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Stato pipeline (polling) --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Stato estrazione</div>
        <div style="display:flex; align-items:center; gap:12px;">
            <template x-if="status==='pending' || status==='processing'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.9rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite;"></span>
                    <span x-text="status==='pending' ? 'In coda…' : 'Elaborazione in corso…'"></span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.9rem;">&#10003; Testo estratto</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.9rem;">&#10007; Estrazione fallita</span></template>
        </div>
        @if($document->status === 'failed')
            <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $document->failure_reason }}</p>
            <form method="POST" action="{{ route('docente.materials.retry', $document) }}" data-async style="margin-top:10px;">
                @csrf
                <button style="padding:8px 14px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.82rem; cursor:pointer;" data-busy-label="Avvio…">Riprova estrazione</button>
            </form>
        @endif
        @if($document->extraction_meta)
            <div style="margin-top:8px; font-size:0.75rem; color:#8A9696;">
                metodo: {{ $document->extraction_meta['method'] ?? '—' }}
                @isset($document->extraction_meta['pages']) · pagine: {{ $document->extraction_meta['pages'] }} @endisset
                @isset($document->extraction_meta['estimated_cost_usd']) · costo stimato: ${{ $document->extraction_meta['estimated_cost_usd'] }} @endisset
            </div>
        @endif
    </div>

    {{-- Sorgenti --}}
    @if($document->source_files)
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; margin-bottom:8px;">File sorgente</div>
        @foreach($document->source_files as $i => $f)
            <a href="{{ route('docente.materials.download', [$document, $i]) }}" style="display:inline-block; margin:0 8px 6px 0; font-size:0.82rem; color:#55B1AE; text-decoration:none;">&#128190; {{ basename($f) }}</a>
        @endforeach
    </div>
    @elseif($document->source_url)
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px; font-size:0.85rem;">
        URL: <a href="{{ $document->source_url }}" target="_blank" rel="noopener" style="color:#55B1AE;">{{ $document->source_url }}</a>
    </div>
    @endif

    {{-- Condivisione con altri docenti (ambito materia/tutti) — a estrazione completata --}}
    @if($document->status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Condivisione con altri docenti</div>
        @if(in_array($document->source_type, ['photos','pdf'], true))
            <p style="font-size:0.82rem; color:#A8521F;">I materiali da foto/PDF non sono condivisibili (distribuzione di testo potenzialmente protetto).</p>
        @else
            <p style="font-size:0.8rem; color:#8A9696; margin-bottom:10px;">
                @if($document->share_scope === 'all') Attualmente condiviso con <strong>tutti i docenti</strong>.
                @elseif($document->share_scope === 'subject') Attualmente condiviso con i <strong>docenti della stessa materia</strong> nella tua scuola.
                @else Attualmente <strong>privato</strong>. @endif
                Chi ha accesso può vederlo, importarlo e trovarlo con Minerva.
            </p>
            <form method="POST" action="{{ route('docente.materials.sharing', $document) }}" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                @csrf @method('PATCH')
                <div>
                    <label style="font-size:0.72rem; color:#8A9696; display:block;">Ambito</label>
                    <select name="scope" style="padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem;">
                        <option value="none" @selected($document->share_scope === null)>Privato</option>
                        <option value="subject" @selected($document->share_scope === 'subject') @disabled(!$document->subject_id)>Stessa materia (stessa scuola)@if(!$document->subject_id) — assegna una materia @endif</option>
                        <option value="all" @selected($document->share_scope === 'all')>Tutti i docenti</option>
                    </select>
                </div>
                <label style="font-size:0.78rem; color:#4A5252; display:flex; align-items:center; gap:6px;">
                    <input type="checkbox" name="rights_ack" value="1"> Confermo di avere i diritti sul contenuto
                    <span style="color:#8A9696;">(richiesto alla prima condivisione)</span>
                </label>
                <button type="submit" style="padding:9px 16px; background:#3A8C89; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:700; cursor:pointer;">Aggiorna condivisione</button>
            </form>
        @endif
    </div>
    @endif

    {{-- Generazione artefatti (solo a estrazione completata) --}}
    @if($document->status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Genera artefatto</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            {{-- Riassunto con livello --}}
            <form method="POST" action="{{ route('docente.artifacts.generate', $document) }}" data-async style="display:flex; gap:6px; align-items:flex-end;">
                @csrf
                <input type="hidden" name="type" value="summary">
                <div>
                    <label style="font-size:0.7rem; color:#8A9696; display:block;">Riassunto — livello</label>
                    <select name="level" style="padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem;">
                        <option value="breve">Breve</option>
                        <option value="medio" selected>Medio</option>
                        <option value="dispensa">Dispensa</option>
                    </select>
                </div>
                <button style="padding:9px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;" data-busy-label="Generazione in corso…">Genera</button>
            </form>

            {{-- Quiz con n. domande --}}
            <form method="POST" action="{{ route('docente.artifacts.generate', $document) }}" data-async style="display:flex; gap:6px; align-items:flex-end;">
                @csrf
                <input type="hidden" name="type" value="quiz">
                <div>
                    <label style="font-size:0.7rem; color:#8A9696; display:block;">Quiz — domande</label>
                    <input type="number" name="num_questions" min="3" max="20" value="10" style="width:70px; padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.82rem;">
                </div>
                <button style="padding:9px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;" data-busy-label="Generazione in corso…">Genera</button>
            </form>

            {{-- Tipi senza opzioni --}}
            @foreach(['mindmap' => 'Mappa mentale', 'conceptmap' => 'Mappa concettuale', 'outline' => 'Scaletta'] as $t => $label)
            <form method="POST" action="{{ route('docente.artifacts.generate', $document) }}" data-async>
                @csrf
                <input type="hidden" name="type" value="{{ $t }}">
                <button style="padding:9px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;" data-busy-label="Generazione…">{{ $label }}</button>
            </form>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Artefatti esistenti --}}
    @if($document->artifacts->count())
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Artefatti</div>
        @php $typeLabels = ['transcript'=>'Trascrizione','summary'=>'Riassunto','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale','quiz'=>'Quiz','outline'=>'Scaletta'];
             $statusColor = ['generating'=>'#E28A53','ready'=>'#3A8C89','failed'=>'#A8521F'];
             $statusLabel = ['generating'=>'in corso…','ready'=>'pronto','failed'=>'fallito']; @endphp
        @foreach($document->artifacts as $a)
            <a href="{{ route('docente.artifacts.show', $a) }}"
               x-data="artifactRow('{{ $a->id }}', '{{ $a->status }}')"
               style="display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border:1px solid #E5E7E7; border-radius:8px; margin-bottom:6px; text-decoration:none;">
                <span style="font-size:0.875rem; color:#1A1F1F;">
                    <span style="font-weight:600;">{{ $typeLabels[$a->type] ?? $a->type }}</span>
                    <span style="color:#8A9696;"> — {{ $a->title }}</span>
                </span>
                <span style="font-size:0.72rem; font-weight:700;"
                      :style="{color: status==='ready' ? '#3A8C89' : (status==='failed' ? '#A8521F' : '#E28A53')}"
                      x-text="status==='ready' ? 'pronto' : (status==='failed' ? 'fallito' : 'in corso…')">{{ $statusLabel[$a->status] ?? $a->status }}</span>
            </a>
        @endforeach
    </div>
    @endif

    {{-- Editing metadati + testo estratto --}}
    <form method="POST" action="{{ route('docente.materials.update', $document) }}" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
        @csrf @method('PATCH')
        <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Titolo</label>
                <input type="text" name="title" value="{{ old('title', $document->title) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Tag (separati da virgola)</label>
                <input type="text" name="tags" value="{{ old('tags', $document->tags ? implode(', ', $document->tags) : '') }}" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
            </div>
        </div>
        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-top:12px;">Testo estratto (modificabile)</label>
        <textarea name="extracted_text" rows="16" style="width:100%; padding:12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; font-family:monospace; line-height:1.5;">{{ old('extracted_text', $document->extracted_text) }}</textarea>
        <div style="display:flex; gap:10px; margin-top:12px;">
            <button type="submit" style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">Salva</button>
        </div>
    </form>

    <form method="POST" action="{{ route('docente.materials.destroy', $document) }}" style="margin-top:12px;" onsubmit="return confirm('Eliminare questo materiale?');">
        @csrf @method('DELETE')
        <button style="padding:8px 14px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.82rem; cursor:pointer;">Elimina materiale</button>
    </form>
</div>

@push('styles')<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function materialStatus(id, initial) {
    return {
        status: initial,
        init() {
            if (this.status === 'pending' || this.status === 'processing') this.poll();
        },
        poll() {
            const url = `/docente/materiali/${id}/stato`;
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') {
                        clearInterval(timer);
                        window.location.reload(); // mostra testo / errore
                    }
                } catch(e) {}
            }, 4000);
        },
    };
}

// Riga artefatto nella lista: se è "in corso", il polling la porta a
// pronto/fallito senza refresh manuale (regola Feedback UX).
function artifactRow(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/artefatti/${id}/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') clearInterval(timer);
                } catch(e) {}
            }, 4000);
        },
    };
}
</script>
@endpush
@endsection
