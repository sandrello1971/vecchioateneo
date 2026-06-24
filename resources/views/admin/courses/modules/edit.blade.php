@extends('layouts.admin')
@section('title', 'Modifica Modulo')
@section('content')

<div style="max-width:900px;">
    <div style="margin-bottom:20px;">
        <a href="/admin/courses/{{ $course->id }}/modules" style="color:#8A9696; font-size:0.8rem;">
            &larr; {{ $course->name }}
        </a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            Modifica: {{ $module->title }}
        </h2>
    </div>

    <form method="POST" action="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <div style="background:white; border-radius:10px; padding:20px;">
                <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; font-size:0.9rem;">Info modulo</h3>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Titolo *</label>
                        <input type="text" name="title" value="{{ $module->title }}" required
                               style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Descrizione</label>
                        <textarea name="description" rows="3"
                                  style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">{{ $module->description }}</textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Durata (min)</label>
                            <input type="number" name="duration_minutes" value="{{ $module->duration_minutes }}"
                                   style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Ordine</label>
                            <input type="number" name="sort_order" value="{{ $module->sort_order }}"
                                   style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                        </div>
                    </div>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ $module->is_active ? 'checked' : '' }}>
                        <span style="font-size:0.875rem; color:#4A5252;">Modulo attivo</span>
                    </label>
                </div>
            </div>

            <div style="background:white; border-radius:10px; padding:20px;">
                <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; font-size:0.9rem;">Materiali ({{ $module->materials->count() }})</h3>
                @foreach($module->materials as $mat)
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #F5F7F7; font-size:0.8rem;">
                    <span style="color:#4A5252; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ \Illuminate\Support\Str::limit($mat->title, 35) }}</span>
                    <span style="color:#8A9696; font-size:0.7rem;">{{ strtoupper($mat->file_type) }}</span>
                    <button type="button"
                            data-material-delete
                            data-material-id="{{ $mat->id }}"
                            data-material-title="{{ $mat->title }}"
                            data-delete-url="{{ route('admin.courses.modules.materials.destroy', [$course, $module, $mat]) }}"
                            title="Elimina materiale"
                            style="padding:2px 6px; background:transparent; border:none; color:#c97a45; cursor:pointer; font-size:0.9rem; line-height:1;">
                        🗑
                    </button>
                </div>
                @endforeach
                <a href="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}/materials/create"
                   style="display:block; text-align:center; margin-top:12px; padding:6px; background:#E8F5F5; color:#55B1AE; border-radius:6px; font-size:0.8rem; text-decoration:none; font-weight:600;">
                    + Aggiungi materiale
                </a>
            </div>
        </div>

        {{-- EDITOR TIPTAP COMPLETO --}}
        <div id="content" style="background:white; border-radius:10px; overflow:hidden; margin-bottom:20px;">
            <div style="padding:16px 20px; border-bottom:1px solid #E8F5F5;">
                <h3 style="font-weight:700; color:#1A1F1F; font-size:0.9rem;">Contenuto del modulo</h3>
            </div>

            <div id="toolbar" style="padding:8px 16px; background:#F5F7F7; border-bottom:1px solid #E8F5F5; display:flex; flex-wrap:wrap; gap:4px; align-items:center;">
                <button type="button" data-cmd="bold" title="Grassetto" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem; font-weight:bold;">B</button>
                <button type="button" data-cmd="italic" title="Corsivo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem; font-style:italic;">I</button>
                <button type="button" data-cmd="underline" title="Sottolineato" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem; text-decoration:underline;">U</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="h2" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem; font-weight:bold;">H2</button>
                <button type="button" data-cmd="h3" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem; font-weight:bold;">H3</button>
                <button type="button" data-cmd="paragraph" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem;">P</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="bulletList" title="Lista puntata" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#8801;</button>
                <button type="button" data-cmd="orderedList" title="Lista numerata" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">1.</button>
                <button type="button" data-cmd="blockquote" title="Citazione" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">"</button>
                <button type="button" data-cmd="hardBreak" title="A capo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem;">&crarr;</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="alignLeft" title="Allinea sinistra" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&larr;</button>
                <button type="button" data-cmd="alignCenter" title="Centra" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&harr;</button>
                <button type="button" data-cmd="alignRight" title="Allinea destra" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&rarr;</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" id="insert-image-btn" title="Inserisci immagine" style="padding:4px 10px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#128247; Immagine</button>
                <input type="file" id="image-upload" accept="image/*" style="display:none;">

                <button type="button" data-cmd="link" title="Inserisci link" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#128279;</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="undo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#8630;</button>
                <button type="button" data-cmd="redo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#8631;</button>

                <div style="margin-left:auto;">
                    <button type="button" id="toggle-source" style="padding:4px 12px; border:1px solid #55B1AE; border-radius:4px; background:white; color:#55B1AE; cursor:pointer; font-size:0.75rem;">
                        &lt;/&gt; HTML
                    </button>
                </div>
            </div>

            <div id="editor-container" style="min-height:500px; padding:0;">
                <div id="tiptap-editor" style="min-height:500px; padding:24px; outline:none; font-family:'Calibri',system-ui,sans-serif; font-size:0.95rem; line-height:1.8; color:#1A1F1F;"></div>
            </div>

            <div id="source-container" style="display:none;">
                <textarea id="source-editor" style="width:100%; min-height:500px; padding:16px; border:none; outline:none; font-family:monospace; font-size:0.8rem; line-height:1.6; resize:vertical; color:#1A1F1F;"></textarea>
            </div>

            <input type="hidden" name="content" id="content-hidden" value="{{ $module->content }}">
        </div>

        <style>
        #tiptap-editor:focus { outline: none; }
        #tiptap-editor h2 { font-size:1.3rem; font-weight:700; color:#1A1F1F; margin:1.5rem 0 0.75rem; border-bottom:2px solid #E8F5F5; padding-bottom:0.4rem; }
        #tiptap-editor h3 { font-size:1.05rem; font-weight:700; color:#3A8C89; margin:1.2rem 0 0.5rem; }
        #tiptap-editor p { margin:0.6rem 0; }
        #tiptap-editor ul { margin:0.5rem 0 0.5rem 1.5rem; list-style:disc; }
        #tiptap-editor ol { margin:0.5rem 0 0.5rem 1.5rem; list-style:decimal; }
        #tiptap-editor li { margin:0.3rem 0; }
        #tiptap-editor blockquote { margin:1rem 0; padding:0.75rem 1.25rem; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:0 8px 8px 0; color:#3A8C89; font-style:italic; }
        #tiptap-editor img { max-width:100%; border-radius:8px; margin:0.5rem 0; cursor:pointer; }
        #tiptap-editor img.selected { outline:3px solid #55B1AE; }
        #tiptap-editor a { color:#55B1AE; text-decoration:underline; }
        #tiptap-editor [style*="text-align: center"] { text-align:center; }
        #tiptap-editor [style*="text-align: right"] { text-align:right; }
        .toolbar-btn-active { background:#E8F5F5 !important; color:#55B1AE !important; border-color:#55B1AE !important; }
        </style>

        <div style="background:linear-gradient(135deg,#1A1F1F,#252B2B); border-radius:10px; padding:20px; margin-bottom:20px;">
            <h3 style="color:#55B1AE; font-weight:700; margin-bottom:12px; font-size:0.9rem;">🎬 Video del modulo</h3>

            @if($module->video_ai_id)
            <div style="padding:10px 14px; background:rgba(85,177,174,0.1); border-radius:8px; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <div style="color:#55B1AE; font-size:0.85rem; font-weight:600;">✓ {{ $module->video_filename }}</div>
                    <div style="color:#8A9696; font-size:0.75rem;">Status: {{ $module->video_status }}</div>
                </div>
                <div style="font-size:0.75rem; color:#8A9696; font-family:monospace;">{{ substr($module->video_ai_id, 0, 12) }}...</div>
            </div>
            @endif

            <div>
                <label style="font-size:0.8rem; color:#8A9696; display:block; margin-bottom:6px;">
                    {{ $module->video_ai_id ? 'Sostituisci video' : 'Carica video' }} (MP4, MOV, AVI — max 2GB)
                </label>
                <input type="file" name="video_file" accept="video/*"
                       style="width:100%; padding:10px; border:1px dashed rgba(85,177,174,0.4); border-radius:8px; color:#8A9696; font-size:0.8rem; background:rgba(255,255,255,0.05);">
                <p style="color:#4A5252; font-size:0.75rem; margin-top:6px;">
                    Il video verrà trascritto automaticamente con AI e indicizzato per il chatbot {{ atheneum_setting('assistant_name', 'Minerva') }}.
                </p>
            </div>
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <a href="/admin/courses/{{ $course->id }}/modules"
               style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                Annulla
            </a>
            <button type="submit"
                    style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Salva modifiche
            </button>
        </div>
    </form>
</div>

{{-- ==================== MODAL CONFERMA ELIMINAZIONE MATERIALE (fuori dal form modulo, no nested forms) ==================== --}}
<div id="material-delete-modal"
     style="display:none; position:fixed; inset:0; background:rgba(26,31,31,0.55); align-items:center; justify-content:center; z-index:1000;">
    <div style="background:white; border-radius:12px; padding:24px; max-width:420px; width:90%; box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">Eliminare il materiale?</h3>
        <p style="font-size:0.85rem; color:#4A5252; line-height:1.5; margin-bottom:6px;">
            Stai per eliminare <strong id="material-delete-title"></strong>.
        </p>
        <p style="font-size:0.78rem; color:#8A9696; line-height:1.5; margin-bottom:20px;">
            Il file verrà rimosso dallo storage e il record cancellato dal database. L'operazione non è reversibile.
        </p>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" id="material-delete-cancel"
                    style="padding:8px 18px; border:1px solid #C8D0D0; background:white; color:#4A5252; border-radius:8px; font-size:0.85rem; cursor:pointer;">
                Annulla
            </button>
            <button type="button" id="material-delete-confirm"
                    style="padding:8px 18px; background:#c97a45; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                Elimina
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const modal = document.getElementById('material-delete-modal');
    if (!modal) return;
    const titleEl = document.getElementById('material-delete-title');
    const cancelBtn = document.getElementById('material-delete-cancel');
    const confirmBtn = document.getElementById('material-delete-confirm');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';
    let currentUrl = null;

    function openModal(url, title) {
        currentUrl = url;
        titleEl.textContent = title;
        modal.style.display = 'flex';
    }
    function closeModal() {
        modal.style.display = 'none';
        currentUrl = null;
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Elimina';
    }

    document.querySelectorAll('[data-material-delete]').forEach(btn => {
        btn.addEventListener('click', () => {
            openModal(btn.dataset.deleteUrl, btn.dataset.materialTitle);
        });
    });
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });

    confirmBtn.addEventListener('click', async () => {
        if (!currentUrl) return;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Eliminazione…';
        try {
            const res = await fetch(currentUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'text/html,application/xhtml+xml,application/json',
                },
                credentials: 'same-origin',
            });
            if (res.ok || res.redirected) {
                window.location.reload();
            } else {
                alert('Errore eliminazione: HTTP ' + res.status);
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Elimina';
            }
        } catch (err) {
            alert('Errore eliminazione: ' + err.message);
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Elimina';
        }
    });
})();
</script>
@endpush

{{-- ==================== PRESENTAZIONE .pptx (P28) — generatore condiviso, pattern async ==================== --}}
@php $modPres = $module->presentation; @endphp
<div style="max-width:900px; margin:20px auto 0;">
    <div style="background:white; border-radius:12px; padding:24px;"
         x-data="modPresentationStatus('{{ $modPres?->status ?? 'none' }}')">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
            <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; flex:1;">📊 Presentazione (.pptx)</h3>
            <template x-if="status==='generating'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.85rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;"></span>
                    <span>Generazione in corso…</span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.85rem;">&#10003; Pronta</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.85rem;">&#10007; Generazione fallita</span></template>
        </div>
        <p style="font-size:0.78rem; color:#8A9696; margin-bottom:12px;">Slide 16:9 brandizzate (tema Noscite di piattaforma) generate dal contenuto del modulo.</p>

        @if(($modPres?->status ?? null) === 'failed' && ($modPres->generation_meta['failure_reason'] ?? null))
            <p style="margin-bottom:10px; font-size:0.82rem; color:#A8521F;">{{ $modPres->generation_meta['failure_reason'] }}</p>
        @endif
        @if(($modPres?->status ?? null) === 'ready' && ($modPres->generation_meta['slides'] ?? null))
            <div style="margin-bottom:10px; font-size:0.75rem; color:#8A9696;">{{ $modPres->generation_meta['slides'] }} slide @isset($modPres->generation_meta['model']) · {{ $modPres->generation_meta['model'] }} @endisset</div>
        @endif
        @if(($modPres->source ?? 'generated') === 'uploaded')
            <div style="margin-bottom:10px;"><span style="display:inline-block; padding:2px 8px; background:#EEF3F3; color:#3A8C89; border-radius:6px; font-size:0.72rem; font-weight:700;">Versione caricata</span></div>
        @endif

        @if(empty($module->content))
            <div style="background:#FBE9E7; border-left:4px solid #E28A53; padding:12px 14px; border-radius:6px; color:#7A4A20; font-size:0.85rem;">
                Il modulo non ha contenuto. Salva prima il contenuto qui sopra, poi torna qui per generare la presentazione.
            </div>
        @else
            {{-- x-show sul wrapper esterno; flex su div interno (riusa il fix bottoni: nessuna sovrapposizione) --}}
            <div x-show="status!=='generating'">
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                @if(!$modPres || $modPres->status === 'pending' || $modPres->status === 'failed')
                    <form method="POST" action="{{ route('admin.courses.modules.presentation.generate', [$course, $module]) }}"
                          onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='⏳ Avvio…';">
                        @csrf
                        <button type="submit" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">{{ ($modPres?->status ?? null) === 'failed' ? '↻ Riprova' : '✨ Genera presentazione' }}</button>
                    </form>
                @elseif($modPres->status === 'ready')
                    <a href="{{ route('admin.courses.modules.presentation.download', [$course, $module]) }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 16px; background:#3A8C89; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11015; Scarica .pptx</a>
                    <form method="POST" action="{{ route('admin.courses.modules.presentation.regenerate', [$course, $module]) }}"
                          onsubmit="return confirm('Rigenerare la presentazione? Il file attuale verrà sovrascritto.') && (this.querySelector('button').disabled=true || true);">
                        @csrf
                        <button type="submit" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Rigenera</button>
                    </form>
                @endif

                {{-- S3 — carica una propria versione .pptx (sostituisce l'unico record del modulo) --}}
                <form method="POST" action="{{ route('admin.courses.modules.presentation.upload', [$course, $module]) }}" enctype="multipart/form-data"
                      style="display:inline-flex; align-items:center; gap:6px;"
                      onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Caricamento…';">
                    @csrf
                    <input type="file" name="presentation" accept=".pptx" required style="font-size:0.78rem; max-width:190px;">
                    <button type="submit" style="padding:9px 14px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">Carica .pptx</button>
                </form>

                {{-- S3 — elimina la presentazione (azione distruttiva, conferma esplicita) --}}
                @if($modPres && $modPres->status !== 'pending')
                    <form method="POST" action="{{ route('admin.courses.modules.presentation.destroy', [$course, $module]) }}"
                          onsubmit="return confirm('Eliminare la presentazione? Operazione non reversibile.') && (this.querySelector('button').disabled=true || true);">
                        @csrf @method('DELETE')
                        <button type="submit" style="padding:9px 14px; background:white; color:#A8521F; border:1px solid #A8521F; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">Elimina</button>
                    </form>
                @endif
            </div>
            </div>
        @endif

        {{-- S1 — anteprima slide (galleria + lightbox). Gemella della galleria docente:
             stesso componente, stesso endpoint preview (storage privato). --}}
        @if(($modPres?->status ?? null) === 'ready' && ($modPres->generation_meta['slides'] ?? 0) > 0)
            @php $slideUrls = array_map(fn ($i) => route('admin.courses.modules.presentation.preview', [$course, $module, $i]), range(1, (int) $modPres->generation_meta['slides'])); @endphp
            <div style="margin-top:16px; border-top:1px solid #F0F2F2; padding-top:14px;">
                <div style="font-size:0.72rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Anteprima slide</div>
                <x-slide-lightbox :images="$slideUrls" />
            </div>
        @endif

        {{-- S2 — correzione via prompt: SOLO se la presentazione ha spec persistita
             (generata dal sistema). Gemella del box docente. --}}
        @if(($modPres?->status ?? null) === 'ready' && !empty($modPres->spec))
            <div style="margin-top:16px; border-top:1px solid #F0F2F2; padding-top:14px;" x-show="status!=='generating'">
                <div style="font-size:0.72rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Correggi le slide</div>
                <form method="POST" action="{{ route('admin.courses.modules.presentation.edit', [$course, $module]) }}"
                      onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='⏳ Correzione…';">
                    @csrf
                    <textarea name="instruction" rows="2" maxlength="2000" required
                              placeholder="Descrivi la modifica (es. «Nella slide 3 aggiungi un esempio pratico»)"
                              style="width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; resize:vertical;"></textarea>
                    <button type="submit" style="margin-top:8px; padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Applica correzione</button>
                </form>
            </div>
        @endif
    </div>
</div>

{{-- ==================== DOCUMENTO PDF (P29) — renderer brandizzato Noscite, stale-then-regenerate ==================== --}}
@php $modDoc = $module->document; @endphp
<div style="max-width:900px; margin:20px auto 0;">
    <div style="background:white; border-radius:12px; padding:24px;"
         x-data="modDocumentStatus('{{ $modDoc?->status ?? 'none' }}')">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
            <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; flex:1;">📄 Documento PDF</h3>
            <template x-if="status==='generating'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.85rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;"></span>
                    <span>Generazione in corso…</span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.85rem;">&#10003; Pronto</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.85rem;">&#10007; Generazione fallita</span></template>
        </div>
        <p style="font-size:0.78rem; color:#8A9696; margin-bottom:12px;">PDF brandizzato (tema Noscite di piattaforma) generato dal contenuto del modulo.</p>

        @if(($modDoc?->status ?? null) === 'failed' && ($modDoc->generation_meta['failure_reason'] ?? null))
            <p style="margin-bottom:10px; font-size:0.82rem; color:#A8521F;">{{ $modDoc->generation_meta['failure_reason'] }}</p>
        @endif

        {{-- Badge stale (pattern mindmap): obsoleto se il content è cambiato dopo la generazione. --}}
        @if(($modDoc?->status ?? null) === 'ready')
            @if($modDoc->isStale())
                <div style="background:rgba(226,138,83,0.12); border-left:4px solid #E28A53; padding:10px 14px; border-radius:6px; margin-bottom:12px; font-size:0.82rem; color:#A8521F;">
                    <strong>⚠ OBSOLETO</strong> — il modulo è cambiato dopo questa versione. Rigenera per allineare il PDF.
                </div>
            @else
                <div style="font-size:0.75rem; color:#3A8C89; margin-bottom:12px; font-weight:600;">&#10003; AGGIORNATO — allineato al contenuto attuale.</div>
            @endif
        @endif

        @if(empty($module->content))
            <div style="background:#FBE9E7; border-left:4px solid #E28A53; padding:12px 14px; border-radius:6px; color:#7A4A20; font-size:0.85rem;">
                Il modulo non ha contenuto. Salva prima il contenuto qui sopra, poi torna qui per generare il documento.
            </div>
        @else
            <div x-show="status!=='generating'">
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                @if(!$modDoc || $modDoc->status === 'pending' || $modDoc->status === 'failed')
                    <form method="POST" action="{{ route('admin.courses.modules.document.generate', [$course, $module]) }}"
                          onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='⏳ Avvio…';">
                        @csrf
                        <button type="submit" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">{{ ($modDoc?->status ?? null) === 'failed' ? '↻ Riprova' : '✨ Genera documento' }}</button>
                    </form>
                @elseif($modDoc->status === 'ready')
                    <a href="{{ route('admin.courses.modules.document.download', [$course, $module]) }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 16px; background:#3A8C89; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11015; Scarica .pdf</a>
                    <form method="POST" action="{{ route('admin.courses.modules.document.regenerate', [$course, $module]) }}"
                          onsubmit="return confirm('Rigenerare il documento? Il file attuale verrà sovrascritto.') && (this.querySelector('button').disabled=true || true);">
                        @csrf
                        <button type="submit" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Rigenera</button>
                    </form>
                @endif
            </div>
            </div>
        @endif
    </div>
</div>

{{-- ==================== MAPPA MENTALE (sezione separata, indipendente dal form principale) ==================== --}}
<div style="max-width:900px; margin:20px auto 40px;">
    <div style="background:white; border-radius:12px; padding:24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <div>
                <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F;">🧠 Mappa mentale</h3>
                <p style="font-size:0.78rem; color:#8A9696; margin-top:2px;">
                    Generata da Claude dal contenuto del modulo. Editabile manualmente.
                </p>
            </div>
            <div>
                @if($module->hasMindmap())
                    @if($module->isMindmapStale())
                    <span style="display:inline-block; background:rgba(226,138,83,0.15); color:#D87840; padding:4px 10px; border-radius:10px; font-size:0.7rem; font-weight:700;">⚠ OBSOLETA</span>
                    @else
                    <span style="display:inline-block; background:rgba(85,177,174,0.15); color:#3A8C89; padding:4px 10px; border-radius:10px; font-size:0.7rem; font-weight:700;">✓ AGGIORNATA</span>
                    @endif
                @endif
            </div>
        </div>

        @if($module->hasMindmap())
            <div style="font-size:0.75rem; color:#8A9696; margin-bottom:12px;">
                Generata: <strong>{{ $module->mindmap_generated_at?->format('d/m/Y H:i') ?? '—' }}</strong>
                @if($module->isMindmapStale())
                    <span style="color:#D87840; margin-left:8px;">— il contenuto del modulo è cambiato dopo la generazione, rigenera per allineare.</span>
                @endif
            </div>
        @endif

        @if(empty($module->content))
            <div style="background:#FBE9E7; border-left:4px solid #E28A53; padding:12px 14px; border-radius:6px; color:#7A4A20; font-size:0.85rem;">
                Il modulo non ha contenuto. Salva prima il contenuto qui sopra, poi torna qui per generare la mappa.
            </div>
        @else
            <div style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
                {{-- Genera / Rigenera --}}
                <form method="POST" action="{{ route('admin.courses.modules.mindmap.generate', [$course, $module]) }}" style="display:inline;"
                      onsubmit="this.querySelector('button').innerHTML='⏳ Generazione in corso (~20s)...'; this.querySelector('button').disabled=true;">
                    @csrf
                    <button type="submit" style="padding:9px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        @if($module->hasMindmap()) 🔄 Rigenera @else ✨ Genera mappa mentale @endif
                    </button>
                </form>

                {{-- Preview toggle --}}
                @if($module->hasMindmap())
                <button type="button" id="mindmap-preview-toggle" style="padding:9px 18px; background:white; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                    👁 Anteprima
                </button>

                {{-- Elimina (svuota campo via update con string vuota) --}}
                <form method="POST" action="{{ route('admin.courses.modules.mindmap.update', [$course, $module]) }}" style="display:inline; margin-left:auto;"
                      onsubmit="return confirm('Eliminare la mappa mentale? Potrai rigenerarla in seguito.');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="mindmap_markdown" value="">
                    <button type="submit" style="padding:9px 14px; background:white; border:1px solid #E28A53; color:#D87840; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                        🗑 Elimina mappa
                    </button>
                </form>
                @endif
            </div>

            {{-- Anteprima markmap (hidden di default, toggle col bottone) --}}
            @if($module->hasMindmap())
            <div id="mindmap-preview" style="display:none; background:#FAFBFB; border:1px solid #E5E7E7; border-radius:8px; padding:8px; margin-bottom:16px; height:500px; overflow:hidden;">
                <svg id="mindmap-svg" style="width:100%; height:100%;"></svg>
            </div>

            {{-- Editor textarea: edit manuale del markdown --}}
            <form method="POST" action="{{ route('admin.courses.modules.mindmap.update', [$course, $module]) }}">
                @csrf
                @method('PATCH')
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#4A5252; margin-bottom:6px;">
                    Markdown (modifica manuale)
                </label>
                <textarea name="mindmap_markdown" rows="16" maxlength="20000"
                          style="width:100%; padding:12px 14px; border:1px solid #C8D0D0; border-radius:8px; font-family:Menlo, Monaco, monospace; font-size:0.85rem; line-height:1.5; resize:vertical; min-height:300px;">{{ old('mindmap_markdown', $module->mindmap_markdown) }}</textarea>
                <div style="font-size:0.7rem; color:#8A9696; margin-top:6px; font-style:italic;">
                    Pattern: <code># Titolo</code> → <code>## Sezione</code> → <code>- voce</code> con indent di 2 spazi per i sotto-livelli. Max 20.000 caratteri.
                </div>
                <div style="margin-top:12px; display:flex; justify-content:flex-end;">
                    <button type="submit" style="padding:9px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        Salva modifiche
                    </button>
                </div>
            </form>
            @endif
        @endif
    </div>
</div>

{{-- Markmap renderer per anteprima admin --}}
@if($module->hasMindmap())
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-view@0.18"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-lib@0.18"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('mindmap-preview-toggle');
    const preview = document.getElementById('mindmap-preview');
    const svg = document.getElementById('mindmap-svg');
    if (!toggle || !preview || !svg) return;

    let rendered = false;

    toggle.addEventListener('click', function() {
        const isVisible = preview.style.display !== 'none';
        preview.style.display = isVisible ? 'none' : 'block';

        if (!isVisible && !rendered) {
            // Lazy render alla prima apertura
            const markdown = @json($module->mindmap_markdown);
            try {
                const { Transformer } = window.markmap;
                const transformer = new Transformer();
                const { root } = transformer.transform(markdown);
                const { Markmap } = window.markmap;
                Markmap.create(svg, null, root);
                rendered = true;
            } catch (e) {
                console.error('Markmap render error:', e);
                preview.innerHTML = '<div style="padding:20px; color:#C52A2A;">Errore rendering markmap: ' + e.message + '</div>';
            }
        }
    });
});
</script>
@endpush
@endif

@push('scripts')
<script type="module">
import { Editor } from 'https://esm.sh/@tiptap/core@2.1.13'
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.1.13'
import Image from 'https://esm.sh/@tiptap/extension-image@2.1.13'
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@2.1.13'
import Link from 'https://esm.sh/@tiptap/extension-link@2.1.13'
import Underline from 'https://esm.sh/@tiptap/extension-underline@2.1.13'

const initialContent = document.getElementById('content-hidden').value || '<p></p>';

const editor = new Editor({
    element: document.getElementById('tiptap-editor'),
    extensions: [
        StarterKit.configure({ hardBreak: { keepMarks: true } }),
        Image.configure({ inline: false, allowBase64: true }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Link.configure({ openOnClick: false }),
        Underline,
    ],
    content: initialContent,
    editorProps: {
        attributes: { style: 'min-height:500px; padding:24px; outline:none;' }
    },
    onUpdate({ editor }) {
        document.getElementById('content-hidden').value = editor.getHTML();
    }
});

document.querySelectorAll('[data-cmd]').forEach(btn => {
    btn.addEventListener('click', () => {
        const cmd = btn.dataset.cmd;
        const chain = editor.chain().focus();
        if (cmd === 'bold') chain.toggleBold().run();
        else if (cmd === 'italic') chain.toggleItalic().run();
        else if (cmd === 'underline') chain.toggleUnderline().run();
        else if (cmd === 'h2') chain.toggleHeading({ level: 2 }).run();
        else if (cmd === 'h3') chain.toggleHeading({ level: 3 }).run();
        else if (cmd === 'paragraph') chain.setParagraph().run();
        else if (cmd === 'bulletList') chain.toggleBulletList().run();
        else if (cmd === 'orderedList') chain.toggleOrderedList().run();
        else if (cmd === 'blockquote') chain.toggleBlockquote().run();
        else if (cmd === 'hardBreak') chain.setHardBreak().run();
        else if (cmd === 'alignLeft') chain.setTextAlign('left').run();
        else if (cmd === 'alignCenter') chain.setTextAlign('center').run();
        else if (cmd === 'alignRight') chain.setTextAlign('right').run();
        else if (cmd === 'undo') chain.undo().run();
        else if (cmd === 'redo') chain.redo().run();
        else if (cmd === 'link') {
            const url = prompt('URL del link:');
            if (url) chain.setLink({ href: url }).run();
        }
        updateToolbarState();
    });
});

function updateToolbarState() {
    document.querySelectorAll('[data-cmd]').forEach(btn => {
        const cmd = btn.dataset.cmd;
        let active = false;
        if (cmd === 'bold') active = editor.isActive('bold');
        else if (cmd === 'italic') active = editor.isActive('italic');
        else if (cmd === 'underline') active = editor.isActive('underline');
        else if (cmd === 'h2') active = editor.isActive('heading', { level: 2 });
        else if (cmd === 'h3') active = editor.isActive('heading', { level: 3 });
        else if (cmd === 'bulletList') active = editor.isActive('bulletList');
        else if (cmd === 'orderedList') active = editor.isActive('orderedList');
        else if (cmd === 'blockquote') active = editor.isActive('blockquote');
        else if (cmd === 'alignLeft') active = editor.isActive({ textAlign: 'left' });
        else if (cmd === 'alignCenter') active = editor.isActive({ textAlign: 'center' });
        else if (cmd === 'alignRight') active = editor.isActive({ textAlign: 'right' });
        btn.classList.toggle('toolbar-btn-active', active);
    });
}

editor.on('selectionUpdate', updateToolbarState);
editor.on('transaction', updateToolbarState);

document.getElementById('insert-image-btn').addEventListener('click', () => {
    document.getElementById('image-upload').click();
});

document.getElementById('image-upload').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    try {
        const res = await fetch('/admin/upload-image', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.url) {
            editor.chain().focus().setImage({ src: data.url }).run();
        } else {
            throw new Error('no url');
        }
    } catch(err) {
        const reader = new FileReader();
        reader.onload = (ev) => editor.chain().focus().setImage({ src: ev.target.result }).run();
        reader.readAsDataURL(file);
    }
    e.target.value = '';
});

let sourceMode = false;
document.getElementById('toggle-source').addEventListener('click', () => {
    sourceMode = !sourceMode;
    const editorContainer = document.getElementById('editor-container');
    const sourceContainer = document.getElementById('source-container');
    const sourceEditor = document.getElementById('source-editor');
    if (sourceMode) {
        sourceEditor.value = editor.getHTML();
        editorContainer.style.display = 'none';
        sourceContainer.style.display = 'block';
        document.getElementById('toggle-source').textContent = 'Visual';
    } else {
        editor.commands.setContent(sourceEditor.value);
        document.getElementById('content-hidden').value = sourceEditor.value;
        editorContainer.style.display = 'block';
        sourceContainer.style.display = 'none';
        document.getElementById('toggle-source').textContent = '</> HTML';
    }
});

document.getElementById('source-editor').addEventListener('input', (e) => {
    document.getElementById('content-hidden').value = e.target.value;
});
</script>
@endpush

@push('scripts')
<script>
// P28 — polling stato presentazione modulo: generating → ready/failed (poi reload).
function modPresentationStatus(initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch('{{ route('admin.courses.modules.presentation.status', [$course, $module]) }}', {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') { clearInterval(timer); window.location.reload(); }
                } catch(e) {}
            }, 5000);
        },
    };
}

// P29 — polling stato documento PDF modulo: generating → ready/failed (poi reload).
function modDocumentStatus(initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch('{{ route('admin.courses.modules.document.status', [$course, $module]) }}', {headers:{'X-Requested-With':'XMLHttpRequest'}});
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
