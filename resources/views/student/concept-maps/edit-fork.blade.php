@extends('layouts.student')
@section('title', 'La tua mappa — ' . $map->title)
@section('breadcrumb', $course->name . ' / ' . $map->title . ' / La mia versione')

@push('styles')
<style>
    .cm-canvas { background:#FFFFFF; border:1px solid #D1D5DB; border-radius:8px;
                 width: 100%; height: 72vh; min-height: 520px; max-height: 800px; }
    .vis-manipulation, .vis-edit-mode { display: none !important; }
    .cm-side label { display:block; font-size:0.72rem; font-weight:600; color:#1A1F1F; margin-bottom:4px; }
    .cm-side input, .cm-side textarea, .cm-side select {
        width:100%; padding:7px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.82rem;
        font-family: Calibri, 'Segoe UI', sans-serif;
    }
    .cm-side textarea { resize:vertical; min-height:60px; }
    .cm-btn { padding:7px 12px; border-radius:6px; font-size:0.76rem; font-weight:600; cursor:pointer; border:none; }
    .cm-btn-primary { background:#55B1AE; color:white; }
    .cm-btn-secondary { background:#FFFFFF; color:#1A1F1F; border:1px solid #D1D5DB; }
    .cm-btn-danger { background:#FFFFFF; color:#991B1B; border:1px solid #991B1B; }
    .cm-pill { display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:600; }
    .cm-pill-saved { background:#D1FAE5; color:#059669; }
    .cm-pill-dirty { background:#FEF3C7; color:#92400E; }
    .cm-pill-error { background:#FEE2E2; color:#991B1B; }
</style>
@endpush

@section('content')
<div x-data="studentForkEditor()" x-init="init()" style="max-width:1200px;">

    <a href="{{ route('student.course.concept-map.show', [$course->slug, $map->id]) }}" style="color:#8A9696; font-size:0.82rem;">&larr; Mappa originale</a>

    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-top:4px; flex-wrap:wrap;">
        <div>
            <h1 style="font-size:1.3rem; font-weight:700; color:#1A1F1F;">
                🧭 La mia versione di: {{ $map->title }}
            </h1>
            <div style="font-size:0.75rem; color:#8A9696; margin-top:4px;">
                Le modifiche sono salvate solo per te. La mappa originale del corso resta invariata.
            </div>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <span x-show="status==='saved'" class="cm-pill cm-pill-saved">&#10003; salvata</span>
            <span x-show="status==='dirty'" class="cm-pill cm-pill-dirty">&#9888; modifiche non salvate</span>
            <span x-show="status==='saving'" class="cm-pill cm-pill-saved">salvataggio...</span>
            <span x-show="status==='error'" class="cm-pill cm-pill-error">errore</span>

            <button type="button" class="cm-btn cm-btn-primary" @click="save" :disabled="saving">
                &#128190; Salva la mia versione
            </button>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:14px; align-items:start; margin-top:14px;">
        <div>
            <div style="display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
                <button type="button" class="cm-btn cm-btn-secondary" @click="addNode">+ Concetto</button>
                <button type="button" class="cm-btn cm-btn-secondary" @click="startEdgeMode">+ Relazione</button>
                <button type="button" class="cm-btn cm-btn-danger" @click="removeSelected">– Elimina selezionato</button>
                <button type="button" class="cm-btn cm-btn-secondary" @click="fit">&#128270; Fit</button>
            </div>
            <div id="cm-canvas" class="cm-canvas"></div>
            <p style="font-size:0.7rem; color:#8A9696; margin-top:8px;">
                Clicca su un nodo per editarlo. Usa <em>+ Relazione</em> e poi trascina da un nodo a un altro per creare un arco etichettato.
            </p>
        </div>

        <aside class="cm-side" style="background:white; border-radius:10px; padding:16px; position:sticky; top:14px;">
            <h3 style="font-size:0.88rem; font-weight:700; color:#3D8B88; margin-bottom:10px;">Nodo selezionato</h3>

            <div x-show="!selectedNode" style="font-size:0.78rem; color:#8A9696;">
                Clicca un nodo nel canvas per modificarlo.
            </div>

            <div x-show="selectedNode" style="display:flex; flex-direction:column; gap:10px;">
                <div>
                    <label>Etichetta</label>
                    <input type="text" x-model="selectedNode.label" @input="patchSelected({label: selectedNode.label})" maxlength="120">
                </div>
                <div>
                    <label>Descrizione (le tue note)</label>
                    <textarea x-model="selectedNode.description" @input="patchSelected({description: selectedNode.description})" maxlength="500"></textarea>
                </div>
                <button type="button" class="cm-btn cm-btn-danger" @click="removeSelectedNode" style="margin-top:6px;">
                    &#128465; Elimina nodo
                </button>
            </div>
        </aside>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
<script src="/js/concept-map-editor.js?v={{ filemtime(public_path('js/concept-map-editor.js')) }}"></script>
<script>
    function studentForkEditor() {
        return {
            editor: null,
            status: 'saved',
            saving: false,
            selectedNode: null,
            init() {
                const initial = @json($fork->data ?: ['nodes' => [], 'edges' => []]);
                this.editor = window.NosciteConceptMap.createEditor('#cm-canvas', initial, {
                    onChange: () => { if (this.status === 'saved') this.status = 'dirty'; },
                    onSelect: (node) => {
                        this.selectedNode = node ? JSON.parse(JSON.stringify(node)) : null;
                    },
                });
            },
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
                if (!confirm('Eliminare questo nodo?')) return;
                this.editor.removeNode(this.selectedNode.id);
                this.selectedNode = null;
            },
            patchSelected(patch) {
                if (!this.selectedNode) return;
                Object.assign(this.selectedNode, patch);
                this.editor.updateNode(this.selectedNode.id, patch);
            },
            fit() { this.editor.fit(); },
            async save() {
                if (this.saving) return;
                this.saving = true;
                this.status = 'saving';
                try {
                    const payload = { data: this.editor.getData() };
                    const res = await fetch('{{ route("student.course.concept-map.my.save", [$course->slug, $map->id]) }}', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    this.status = 'saved';
                } catch (e) {
                    console.error(e);
                    this.status = 'error';
                } finally {
                    this.saving = false;
                }
            },
        };
    }
</script>
@endpush
@endsection
