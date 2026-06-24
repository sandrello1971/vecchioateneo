@extends('layouts.admin')
@section('title', $map->title . ' — Editor mappa concettuale')

@push('styles')
<style>
    .cm-canvas { background:#FFFFFF; border:1px solid #D1D5DB; border-radius:8px;
                 width: 100%; height: 76vh; min-height: 560px; max-height: 880px; }
    /* Nasconde il toolbar built-in di vis-network (Edit/Add/Delete galleggiante in basso a sinistra):
       usiamo solo i nostri bottoni esterni in toolbar */
    .vis-manipulation, .vis-edit-mode { display: none !important; }
    .cm-side label { display:block; font-size:0.75rem; font-weight:600; color:#1A1F1F; margin-bottom:4px; }
    .cm-side input, .cm-side textarea, .cm-side select {
        width:100%; padding:7px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.85rem;
        font-family: Calibri, 'Segoe UI', sans-serif;
    }
    .cm-side textarea { resize:vertical; min-height:64px; }
    .cm-btn { padding:7px 12px; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer; border:none; }
    .cm-btn-primary { background:#55B1AE; color:white; }
    .cm-btn-primary:hover { background:#3D8B88; }
    .cm-btn-orange { background:#E28A53; color:white; }
    .cm-btn-orange:hover { opacity:.9; }
    .cm-btn-secondary { background:#FFFFFF; color:#1A1F1F; border:1px solid #D1D5DB; }
    .cm-btn-secondary:hover { background:#F5F7F7; }
    .cm-btn-danger { background:#FFFFFF; color:#991B1B; border:1px solid #991B1B; }
    .cm-pill { display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:600; }
    .cm-pill-saved { background:#D1FAE5; color:#059669; }
    .cm-pill-dirty { background:#FEF3C7; color:#92400E; }
    .cm-pill-error { background:#FEE2E2; color:#991B1B; }
</style>
@endpush

@section('content')
<div x-data="conceptMapEditor()" x-init="init()" style="display:flex; flex-direction:column; gap:14px;">

    {{-- TOOLBAR --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
        <div>
            <a href="/admin/courses/{{ $course->id }}/concept-maps" style="color:#8A9696; font-size:0.8rem;">&larr; Mappe concettuali</a>
            <h2 style="font-size:1.15rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
                {{ $map->title }}
                @if($map->isCourseLevel())
                    <span style="margin-left:6px; padding:2px 8px; background:rgba(226,138,83,0.13); color:#E28A53; border-radius:4px; font-size:0.65rem; font-weight:700;">🌐 INTERO CORSO</span>
                @else
                    <span style="margin-left:6px; padding:2px 8px; background:#E8F5F5; color:#3D8B88; border-radius:4px; font-size:0.65rem; font-weight:700;">📚 MODULO</span>
                @endif
            </h2>
            <div style="font-size:0.75rem; color:#8A9696; margin-top:2px;">
                Corso: <strong>{{ $course->name }}</strong>
                @if($map->isModuleLevel() && $map->module) &middot; Modulo: <strong>{{ $map->module->title }}</strong> @endif
                @if($map->ai_generated_at) &middot; AI generato {{ $map->ai_generated_at->diffForHumans() }} @endif
            </div>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <span x-show="status==='saved'" class="cm-pill cm-pill-saved">&#10003; salvata</span>
            <span x-show="status==='dirty'" class="cm-pill cm-pill-dirty">&#9888; modifiche non salvate</span>
            <span x-show="status==='saving'" class="cm-pill cm-pill-saved">salvataggio...</span>
            <span x-show="status==='error'" class="cm-pill cm-pill-error">errore salvataggio</span>

            <form @submit.prevent="generateWithAI"
                  action="/admin/courses/{{ $course->id }}/concept-maps/{{ $map->id }}/generate" method="POST"
                  style="display:inline-block;">
                @csrf
                <button type="submit" class="cm-btn cm-btn-orange" :disabled="generating"
                        x-text="generating ? 'Generazione in corso...' : '&#10024; Genera con AI'"
                        onclick="return confirm('Rigenerare la mappa dai contenuti del corso? Le modifiche manuali correnti verranno sovrascritte.')">
                </button>
            </form>

            <button type="button" class="cm-btn cm-btn-primary" @click="save" :disabled="saving">
                &#128190; Salva
            </button>
        </div>
    </div>

    @if(session('success'))
        <div style="padding:10px 14px; background:#D1FAE5; color:#059669; border-radius:6px; font-size:0.875rem;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:10px 14px; background:#FEE2E2; color:#991B1B; border-radius:6px; font-size:0.875rem;">{{ session('error') }}</div>
    @endif

    {{-- METADATA STRIP (title/desc/visibility editable) --}}
    <div style="background:white; border-radius:10px; padding:14px 18px; display:grid; grid-template-columns:1fr 1fr 200px; gap:12px;">
        <div>
            <label style="display:block; font-size:0.72rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Titolo</label>
            <input type="text" x-model="meta.title" @input="markDirty"
                   style="width:100%; padding:7px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.85rem;">
        </div>
        <div>
            <label style="display:block; font-size:0.72rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Descrizione</label>
            <input type="text" x-model="meta.description" @input="markDirty" maxlength="2000"
                   style="width:100%; padding:7px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.85rem;">
        </div>
        <div>
            <label style="display:block; font-size:0.72rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Visibilità</label>
            <select x-model="meta.visibility" @change="markDirty"
                    style="width:100%; padding:7px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.85rem;">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
            </select>
        </div>
    </div>

    {{-- EDITOR + SIDEBAR --}}
    <div style="display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:14px; align-items:start;">
        <div>
            <div style="display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
                <button type="button" class="cm-btn cm-btn-secondary" @click="addNode">+ Concetto (nodo)</button>
                <button type="button" class="cm-btn cm-btn-secondary" @click="startEdgeMode">+ Relazione (arco)</button>
                <button type="button" class="cm-btn cm-btn-danger" @click="removeSelected">– Elimina selezionato</button>
                <button type="button" class="cm-btn cm-btn-secondary" @click="fit">&#128270; Fit</button>
            </div>
            <div id="cm-canvas" class="cm-canvas"></div>
            <p style="font-size:0.72rem; color:#8A9696; margin-top:8px;">
                Suggerimento: clicca su un nodo per editarlo nella sidebar a destra. Usa <em>+ Relazione</em>
                e poi trascina da un nodo a un altro per creare un arco etichettato.
            </p>
        </div>

        <aside class="cm-side" style="background:white; border-radius:10px; padding:18px; position:sticky; top:14px;">
            <h3 style="font-size:0.9rem; font-weight:700; color:#3D8B88; margin-bottom:12px;">Nodo selezionato</h3>

            <div x-show="!selectedNode" style="font-size:0.8rem; color:#8A9696;">
                Nessun nodo selezionato. Clicca un nodo nel canvas o aggiungine uno.
            </div>

            <div x-show="selectedNode" style="display:flex; flex-direction:column; gap:10px;">
                <div>
                    <label>Etichetta (label visibile)</label>
                    <input type="text" x-model="selectedNode.label" @input="patchSelected({label: selectedNode.label})" maxlength="120">
                </div>
                <div>
                    <label>Descrizione (tooltip)</label>
                    <textarea x-model="selectedNode.description" @input="patchSelected({description: selectedNode.description})" maxlength="500"></textarea>
                </div>
                <div>
                    <label>Collegamento</label>
                    <select x-model="selectedNode.link_type" @change="resetLinkTargets(); patchSelected(selectedNode)">
                        <option value="">Nessuno</option>
                        <option value="module">Modulo del corso</option>
                        <option value="material">Materiale del corso</option>
                        <option value="url">URL esterna</option>
                    </select>
                </div>
                <template x-if="selectedNode.link_type === 'module'">
                    <div>
                        <label>Modulo target</label>
                        <select x-model="selectedNode.link_module_id" @change="patchSelected(selectedNode)">
                            <option value="">— scegli —</option>
                            @foreach($modules as $m)
                                <option value="{{ $m->id }}">{{ $m->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </template>
                <template x-if="selectedNode.link_type === 'material'">
                    <div>
                        <label>Materiale target</label>
                        <select x-model="selectedNode.link_material_id" @change="patchSelected(selectedNode)">
                            <option value="">— scegli —</option>
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}">{{ $mat->title }} ({{ $mat->file_type }})</option>
                            @endforeach
                        </select>
                    </div>
                </template>
                <template x-if="selectedNode.link_type === 'url'">
                    <div>
                        <label>URL</label>
                        <input type="url" x-model="selectedNode.link_url" @input="patchSelected(selectedNode)" placeholder="https://...">
                    </div>
                </template>

                <button type="button" class="cm-btn cm-btn-danger" @click="removeSelectedNode" style="margin-top:8px;">
                    &#128465; Elimina questo nodo
                </button>
            </div>

            <hr style="margin:18px 0; border:0; border-top:1px solid #E5E7EB;">

            <h3 style="font-size:0.85rem; font-weight:700; color:#3D8B88; margin-bottom:6px;">Statistiche</h3>
            <div style="font-size:0.75rem; color:#4A5252;">
                <div>Nodi: <strong x-text="nodeCount"></strong></div>
                <div>Archi: <strong x-text="edgeCount"></strong></div>
                @if($map->ai_generated)
                    <div style="margin-top:6px;">
                        @if($map->isStale())
                            <span class="cm-pill cm-pill-dirty">&#9888; OBSOLETA</span>
                            <div style="margin-top:4px;">Il contenuto dei moduli è cambiato dopo l'ultima generazione AI. Rigenera per allineare.</div>
                        @else
                            <span class="cm-pill cm-pill-saved">&#10003; AI aggiornata</span>
                        @endif
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
<script src="/js/concept-map-editor.js?v={{ filemtime(public_path('js/concept-map-editor.js')) }}"></script>
<script>
    function conceptMapEditor() {
        return {
            editor: null,
            status: 'saved',
            saving: false,
            generating: false,
            selectedNode: null,
            nodeCount: 0,
            edgeCount: 0,
            meta: {
                title: @json($map->title),
                description: @json($map->description ?? ''),
                visibility: @json($map->visibility),
            },
            init() {
                const initial = @json($map->data ?: ['nodes' => [], 'edges' => []]);
                this.editor = window.NosciteConceptMap.createEditor('#cm-canvas', initial, {
                    onChange: (data) => {
                        this.nodeCount = data.nodes.length;
                        this.edgeCount = data.edges.length;
                        this.markDirty();
                    },
                    onSelect: (node) => {
                        this.selectedNode = node ? JSON.parse(JSON.stringify(node)) : null;
                    },
                });
                const initialData = this.editor.getData();
                this.nodeCount = initialData.nodes.length;
                this.edgeCount = initialData.edges.length;
            },
            markDirty() { if (this.status === 'saved') this.status = 'dirty'; },
            addNode() {
                const label = window.prompt('Etichetta del nuovo concetto:');
                if (!label || !label.trim()) return;
                this.editor.addNode({ label: label.trim() });
            },
            startEdgeMode() {
                this.editor.startAddEdgeMode();
                alert('Clicca su un nodo e trascina su un altro per creare un arco. Verrà richiesta l\'etichetta della relazione.');
            },
            removeSelected() { this.editor.removeSelected(); this.selectedNode = null; },
            removeSelectedNode() {
                if (!this.selectedNode) return;
                if (!confirm('Eliminare questo nodo (e gli archi collegati)?')) return;
                this.editor.removeNode(this.selectedNode.id);
                this.selectedNode = null;
            },
            patchSelected(patch) {
                if (!this.selectedNode) return;
                Object.assign(this.selectedNode, patch);
                this.editor.updateNode(this.selectedNode.id, patch);
            },
            resetLinkTargets() {
                if (!this.selectedNode) return;
                if (this.selectedNode.link_type !== 'module') this.selectedNode.link_module_id = null;
                if (this.selectedNode.link_type !== 'material') this.selectedNode.link_material_id = null;
                if (this.selectedNode.link_type !== 'url') this.selectedNode.link_url = null;
            },
            fit() { this.editor.fit(); },
            async save() {
                if (this.saving) return;
                this.saving = true;
                this.status = 'saving';
                try {
                    const payload = {
                        title: this.meta.title,
                        description: this.meta.description,
                        visibility: this.meta.visibility,
                        data: this.editor.getData(),
                    };
                    const res = await fetch('/admin/courses/{{ $course->id }}/concept-maps/{{ $map->id }}', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });
                    if (!res.ok) {
                        const body = await res.json().catch(() => null);
                        const flatErrors = body?.errors
                            ? Object.entries(body.errors).map(([k,v]) => `• ${k}: ${Array.isArray(v)?v.join(', '):v}`).join('\n')
                            : (body?.message || ('HTTP ' + res.status));
                        console.error('Save failed', res.status, body);
                        window.alert('Salvataggio fallito (HTTP ' + res.status + ')\n\n' + flatErrors);
                        throw new Error('HTTP ' + res.status);
                    }
                    this.status = 'saved';
                } catch (e) {
                    console.error(e);
                    this.status = 'error';
                } finally {
                    this.saving = false;
                }
            },
            async generateWithAI(e) {
                this.generating = true;
                try {
                    e.target.submit();
                } finally {
                    // Non-AJAX, lascia che la submit ricarichi la pagina
                }
            },
        };
    }
</script>
@endpush
@endsection
