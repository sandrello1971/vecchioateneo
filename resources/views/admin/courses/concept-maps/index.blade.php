@extends('layouts.admin')
@section('title', $course->name . ' — Mappe concettuali')
@section('content')

<div style="max-width:980px;">

    <div style="margin-bottom:20px;">
        <a href="/admin/courses/{{ $course->id }}" style="color:#8A9696; font-size:0.8rem;">&larr; {{ $course->name }}</a>
        <h2 style="font-size:1.3rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            🧭 Mappe concettuali — {{ $course->name }}
        </h2>
        <p style="font-size:0.85rem; color:#8A9696; margin-top:6px; max-width:680px;">
            Grafi di concetti con relazioni esplicite (à la Novak/Cmap). Una mappa per ciascun modulo
            e una opzionale per l'intero corso. Genera con AI in un click oppure crea manualmente.
        </p>
    </div>

    @if(session('success'))
        <div style="padding:10px 14px; background:#D1FAE5; color:#059669; border-radius:6px; margin-bottom:14px; font-size:0.875rem;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:10px 14px; background:#FEE2E2; color:#991B1B; border-radius:6px; margin-bottom:14px; font-size:0.875rem;">{{ session('error') }}</div>
    @endif

    {{-- CARD INTERO CORSO --}}
    <div style="background:white; border:1px solid #E8F5F5; border-left:4px solid #E28A53;
                border-radius:10px; padding:18px 22px; margin-bottom:24px;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
            <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="font-size:1.1rem;">🌐</div>
                    <h3 style="font-weight:700; color:#1A1F1F;">Mappa "Intero corso"</h3>
                    @if($courseMap)
                        @if($courseMap->isPublished())
                            <span style="font-size:0.65rem; padding:2px 8px; background:#D1FAE5; color:#059669; border-radius:4px; font-weight:700;">PUBLISHED</span>
                        @else
                            <span style="font-size:0.65rem; padding:2px 8px; background:#F3F4F6; color:#6B7280; border-radius:4px; font-weight:700;">DRAFT</span>
                        @endif
                        @if($courseMap->ai_generated)
                            <span style="font-size:0.65rem; padding:2px 8px; background:#E8F5F5; color:#3D8B88; border-radius:4px; font-weight:700;">AI</span>
                        @endif
                        @if($courseMap->ai_generated && $courseMap->isStale())
                            <span style="font-size:0.65rem; padding:2px 8px; background:#FEF3C7; color:#92400E; border-radius:4px; font-weight:700;">&#9888; OBSOLETA</span>
                        @endif
                    @endif
                </div>
                <div style="font-size:0.78rem; color:#8A9696; margin-top:4px;">
                    @if($courseMap)
                        {{ count($courseMap->data['nodes'] ?? []) }} concetti · {{ count($courseMap->data['edges'] ?? []) }} relazioni
                        @if($courseMap->ai_generated_at) · generata {{ $courseMap->ai_generated_at->diffForHumans() }} @endif
                    @else
                        Sintesi globale dei concetti del corso, generata aggregando tutti i moduli.
                    @endif
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                @if($courseMap)
                    <a href="/admin/courses/{{ $course->id }}/concept-maps/{{ $courseMap->id }}/edit"
                       style="padding:7px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.78rem; font-weight:600; text-decoration:none;">
                        Editor
                    </a>
                    <form action="/admin/courses/{{ $course->id }}/concept-maps/{{ $courseMap->id }}" method="POST"
                          onsubmit="return confirm('Eliminare la mappa &quot;intero corso&quot;?');">
                        @csrf @method('DELETE')
                        <button type="submit" style="padding:7px 14px; background:white; color:#991B1B; border:1px solid #991B1B; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            Elimina
                        </button>
                    </form>
                @else
                    <form action="/admin/courses/{{ $course->id }}/concept-maps/auto-create" method="POST" class="cm-auto-create" style="display:inline-block;">
                        @csrf
                        <button type="submit" style="padding:7px 14px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            ✨ Crea con AI
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- TABELLA PER-MODULO --}}
    <div style="background:white; border-radius:10px; padding:18px 22px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <h3 style="font-weight:700; color:#1A1F1F;">📚 Mappe per modulo</h3>
            <div style="font-size:0.72rem; color:#8A9696;">
                {{ $mapsByModule->count() }} / {{ $modules->count() }} mappe create
            </div>
        </div>

        @if($modules->isEmpty())
            <div style="padding:20px; text-align:center; color:#8A9696; font-size:0.85rem;">
                Questo corso non ha ancora moduli. Aggiungi moduli per creare mappe per ciascuno.
            </div>
        @else
            <div style="display:flex; flex-direction:column; gap:8px;">
                @foreach($modules as $module)
                    @php $m = $mapsByModule->get($module->id); @endphp
                    <div style="display:flex; align-items:center; gap:12px; padding:12px 14px; background:#F5F7F7; border-radius:8px;">
                        <div style="width:30px; height:30px; flex-shrink:0; background:#E8F5F5; color:#3D8B88;
                                    border-radius:50%; display:flex; align-items:center; justify-content:center;
                                    font-weight:700; font-size:0.78rem;">
                            {{ $module->sort_order }}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem;">
                                {{ $module->title }}
                                @if($m)
                                    <span style="margin-left:6px; font-size:0.7rem; color:#3D8B88;">→ {{ $m->title }}</span>
                                @endif
                            </div>
                            @if($m)
                                <div style="font-size:0.72rem; color:#8A9696; margin-top:2px;">
                                    {{ count($m->data['nodes'] ?? []) }} concetti · {{ count($m->data['edges'] ?? []) }} relazioni
                                    @if($m->isPublished())
                                        · <span style="color:#059669; font-weight:600;">PUBLISHED</span>
                                    @else
                                        · <span style="color:#6B7280; font-weight:600;">DRAFT</span>
                                    @endif
                                    @if($m->ai_generated && $m->isStale())
                                        · <span style="color:#92400E; font-weight:600;">&#9888; OBSOLETA</span>
                                    @endif
                                </div>
                            @else
                                <div style="font-size:0.72rem; color:#8A9696; margin-top:2px;">
                                    Nessuna mappa per questo modulo.
                                </div>
                            @endif
                        </div>
                        <div style="display:flex; gap:6px;">
                            @if($m)
                                <a href="/admin/courses/{{ $course->id }}/concept-maps/{{ $m->id }}/edit"
                                   style="padding:6px 12px; background:#55B1AE; color:white; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                                    Editor
                                </a>
                                <form action="/admin/courses/{{ $course->id }}/concept-maps/{{ $m->id }}" method="POST"
                                      onsubmit="return confirm('Eliminare la mappa del modulo {{ $module->title }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="padding:6px 12px; background:white; color:#991B1B; border:1px solid #991B1B; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                                        ✕
                                    </button>
                                </form>
                            @else
                                <form action="/admin/courses/{{ $course->id }}/concept-maps/auto-create" method="POST" class="cm-auto-create" style="display:inline-block;">
                                    @csrf
                                    <input type="hidden" name="module_id" value="{{ $module->id }}">
                                    <button type="submit" style="padding:6px 12px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                                        ✨ Crea con AI
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div style="margin-top:14px; text-align:right;">
            <a href="/admin/courses/{{ $course->id }}/concept-maps/create"
               style="font-size:0.78rem; color:#55B1AE; text-decoration:underline;">
                + Crea mappa manuale (senza AI)
            </a>
        </div>
    </div>
</div>

{{-- Overlay generazione AI: full-page loader durante il submit dei form auto-create --}}
<div id="cm-ai-overlay" style="display:none; position:fixed; inset:0; z-index:9999;
                                background:rgba(245,247,247,0.94); backdrop-filter:blur(2px);
                                align-items:center; justify-content:center; flex-direction:column; gap:18px; padding:24px;">
    <div style="width:54px; height:54px; border:5px solid #E8F5F5; border-top-color:#E28A53;
                border-radius:50%; animation: cm-ai-spin 0.9s linear infinite;"></div>
    <div style="font-size:1.05rem; font-weight:700; color:#1A1F1F; text-align:center;">
        ✨ Generazione mappa concettuale in corso…
    </div>
    <div style="font-size:0.85rem; color:#4A5252; max-width:480px; text-align:center; line-height:1.55;">
        Claude sta analizzando i contenuti e costruendo il grafo di concetti.<br>
        L'operazione può richiedere fino a <strong>60 secondi</strong>. Non chiudere questa pagina.
    </div>
    <div id="cm-ai-elapsed" style="font-size:0.75rem; color:#8A9696; font-variant-numeric:tabular-nums;">0s</div>
</div>

<style>
    @keyframes cm-ai-spin { to { transform: rotate(360deg); } }
</style>

<script>
    (function () {
        const overlay = document.getElementById('cm-ai-overlay');
        const elapsedEl = document.getElementById('cm-ai-elapsed');
        let ticker = null;

        function showOverlay() {
            overlay.style.display = 'flex';
            const t0 = Date.now();
            ticker = setInterval(() => {
                const s = Math.round((Date.now() - t0) / 1000);
                elapsedEl.textContent = s + 's';
            }, 1000);
        }

        document.querySelectorAll('form.cm-auto-create').forEach(function (f) {
            f.addEventListener('submit', function () {
                // Disabilita tutti i bottoni della pagina per evitare doppi click + cambia label del bottone clickato
                document.querySelectorAll('form.cm-auto-create button').forEach(function (b) {
                    b.disabled = true;
                    b.style.opacity = '0.6';
                    b.style.cursor = 'wait';
                });
                showOverlay();
            });
        });

        // Sicurezza: se l'utente torna a questa pagina tramite back-button, nasconde l'overlay
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                overlay.style.display = 'none';
                if (ticker) clearInterval(ticker);
            }
        });
    })();
</script>
@endsection
