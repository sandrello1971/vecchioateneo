@extends('layouts.student')
@section('title', $note->exists ? 'Modifica nota' : 'Nuova nota')

@section('content')
<div style="max-width:1200px; margin:0 auto;">
    <div style="margin-bottom:16px; font-size:0.85rem;">
        <a href="{{ $returnUrl ?? route('student.knowledge_base.index') }}"
           style="color:#3A8C89; text-decoration:none;">← Torna indietro</a>
    </div>

    @if($errors->any())
    <div style="padding:10px 14px; background:rgba(226,82,82,0.12); border:1px solid rgba(226,82,82,0.4);
                border-radius:8px; color:#C52A2A; margin-bottom:14px; font-size:0.85rem;">
        @foreach($errors->all() as $err)<div>⚠️ {{ $err }}</div>@endforeach
    </div>
    @endif

    <form method="POST"
          action="{{ $note->exists ? route('student.knowledge_base.update', $note->id) : route('student.knowledge_base.store') }}"
          x-data="noteForm()" x-init="init()">
        @csrf
        @if($note->exists) @method('PUT') @endif
        <input type="hidden" name="return_url" value="{{ $returnUrl ?? '' }}">

        <div style="background:white; border-radius:12px; padding:18px; margin-bottom:14px;
                    display:grid; grid-template-columns:1fr auto; gap:14px; align-items:end;">
            <div>
                <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Tipo nota</label>
                <select name="kind" required
                        style="width:100%; padding:8px; border:1px solid #E8F5F5;
                               border-radius:6px; font-size:0.9rem;">
                    @foreach($kinds as $k => $info)
                    <option value="{{ $k }}" {{ $note->kind === $k ? 'selected' : '' }}>
                        {{ $info['emoji'] }} {{ $info['label'] }}
                    </option>
                    @endforeach
                </select>
            </div>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem;">
                <input type="checkbox" name="is_shared" value="1" {{ $note->is_shared ? 'checked' : '' }}>
                🔁 Condividi con altri formatori
            </label>
        </div>

        <div style="background:white; border-radius:12px; padding:18px; margin-bottom:14px;
                    display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
            <div>
                <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Corso *</label>
                <select name="course_id" required x-model="courseId" @change="loadModulesAndSections()"
                        style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.85rem;">
                    <option value="">— Seleziona corso —</option>
                    @foreach($courses as $c)
                    <option value="{{ $c->id }}" {{ $note->course_id === $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Modulo (opzionale)</label>
                <select name="module_id"
                        style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.85rem;">
                    <option value="">— Nota di corso (no modulo) —</option>
                    <template x-for="m in modules" :key="m.id">
                        <option :value="m.id" :selected="m.id === '{{ $note->module_id }}'" x-text="m.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Sezione manuale (opz.)</label>
                <select name="instructor_manual_section_id"
                        style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.85rem;">
                    <option value="">— Nessuna sezione specifica —</option>
                    <template x-for="s in sections" :key="s.id">
                        <option :value="s.id" :selected="s.id === '{{ $note->instructor_manual_section_id }}'" x-text="s.label"></option>
                    </template>
                </select>
            </div>
        </div>

        <div style="background:white; border-radius:12px; padding:18px; margin-bottom:14px;">
            <div style="margin-bottom:14px;">
                <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Titolo *</label>
                <input type="text" name="title" required maxlength="200"
                       value="{{ $note->title }}"
                       placeholder="Es. Metafora del cuoco per spiegare il prompt"
                       style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.95rem;">
            </div>
            <div>
                <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Tag (Invio per aggiungere)</label>
                <div style="display:flex; flex-wrap:wrap; gap:6px; padding:8px; border:1px solid #E8F5F5; border-radius:6px; min-height:40px; background:white;">
                    <template x-for="(tag, i) in tagsList" :key="i">
                        <span style="background:#E8F5F5; padding:4px 10px; border-radius:12px; font-size:0.78rem; display:flex; align-items:center; gap:6px;">
                            <span x-text="'#' + tag"></span>
                            <button type="button" @click="removeTag(i)" style="border:none; background:none; cursor:pointer; color:#8A9696;">×</button>
                        </span>
                    </template>
                    <input type="text" x-ref="tagInput" x-model="tagDraft"
                           @keydown.enter.prevent="addTag()"
                           @keydown.tab.prevent="addTag()"
                           @input.debounce.300ms="suggestTags()"
                           placeholder="aula-piccola, pmi-manifatturiero, …"
                           style="flex:1; min-width:150px; border:none; outline:none; padding:4px; font-size:0.85rem;">
                </div>
                <template x-for="(tag, i) in tagsList" :key="'h'+i">
                    <input type="hidden" :name="'tags[]'" :value="tag">
                </template>
                <div x-show="tagSuggestions.length > 0 && tagDraft.length > 0"
                     style="margin-top:6px; display:flex; gap:4px; flex-wrap:wrap;">
                    <template x-for="s in tagSuggestions" :key="s">
                        <button type="button" @click="addTagFromSuggestion(s)"
                                style="background:#F5F7F7; border:1px solid #E8F5F5; padding:3px 10px; border-radius:10px; font-size:0.75rem; cursor:pointer;">
                            + <span x-text="s"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div style="background:white; border-radius:12px; padding:18px; margin-bottom:14px;">
            <div style="display:flex; gap:6px; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #E8F5F5;">
                <button type="button" @click="wrap('**')" title="Grassetto"
                        style="padding:6px 12px; background:#F5F7F7; border:1px solid #E8F5F5; border-radius:4px; cursor:pointer; font-weight:700;">B</button>
                <button type="button" @click="wrap('*')" title="Corsivo"
                        style="padding:6px 12px; background:#F5F7F7; border:1px solid #E8F5F5; border-radius:4px; cursor:pointer; font-style:italic;">I</button>
                <button type="button" @click="prefix('- ')" title="Lista"
                        style="padding:6px 12px; background:#F5F7F7; border:1px solid #E8F5F5; border-radius:4px; cursor:pointer;">• Lista</button>
                <button type="button" @click="insertLink()" title="Link"
                        style="padding:6px 12px; background:#F5F7F7; border:1px solid #E8F5F5; border-radius:4px; cursor:pointer;">🔗 Link</button>
                <label style="padding:6px 12px; background:#F5F7F7; border:1px solid #E8F5F5; border-radius:4px; cursor:pointer;" title="Carica immagine">
                    📷 Immagine
                    <input type="file" accept="image/png,image/jpeg,image/webp,image/gif"
                           style="display:none;" @change="uploadImage($event)">
                </label>
                <div style="flex:1;"></div>
                <span x-show="uploading" style="font-size:0.8rem; color:#8A9696;">Upload in corso…</span>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:0.7rem; color:#8A9696;">Markdown</label>
                    <textarea x-ref="editor" x-model="bodyMd" name="body_markdown" required maxlength="10000"
                              @input.debounce.150ms="renderPreview()"
                              style="width:100%; height:340px; padding:12px; border:1px solid #E8F5F5;
                                     border-radius:6px; font-family:monospace; font-size:0.85rem;
                                     resize:vertical;">{{ $note->body_markdown }}</textarea>
                    <div style="font-size:0.7rem; color:#8A9696; text-align:right; margin-top:4px;">
                        <span x-text="bodyMd.length"></span> / 10000 caratteri
                    </div>
                </div>
                <div>
                    <label style="font-size:0.7rem; color:#8A9696;">Anteprima</label>
                    <div x-html="previewHtml"
                         class="md-preview"
                         style="min-height:340px; padding:12px; border:1px solid #E8F5F5;
                                border-radius:6px; background:#FAFBFB; overflow:auto;">
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
            @if($note->exists)
            <button type="button"
                    @click="if(confirm('Cancellare questa nota? (Recuperabile dal cestino)')) deleteNote()"
                    style="padding:10px 18px; color:#C52A2A; background:white; border:1px solid #E28282; border-radius:6px; cursor:pointer; font-size:0.85rem;">
                🗑 Cancella
            </button>
            @endif
            <button type="submit"
                    style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:6px; cursor:pointer; font-size:0.85rem; font-weight:600;">
                💾 {{ $note->exists ? 'Salva modifiche' : 'Crea nota' }}
            </button>
        </div>

        @if($note->exists)
        <form id="delete-form" method="POST" style="display:none;"
              action="{{ route('student.knowledge_base.destroy', $note->id) }}">
            @csrf @method('DELETE')
        </form>
        @endif
    </form>
</div>

<style>
.md-preview h1 { font-size:1.3rem; margin:8px 0; }
.md-preview h2 { font-size:1.15rem; margin:7px 0; }
.md-preview h3 { font-size:1.0rem; margin:6px 0; }
.md-preview p { margin:6px 0; line-height:1.5; }
.md-preview ul, .md-preview ol { margin:6px 0 6px 20px; }
.md-preview a { color:#3A8C89; }
.md-preview img { max-width:100%; height:auto; border-radius:6px; margin:6px 0; }
.md-preview code { background:#F5F7F7; padding:2px 5px; border-radius:3px; font-size:0.85em; }
.md-preview pre { background:#F5F7F7; padding:10px; border-radius:6px; overflow:auto; }
.md-preview blockquote { border-left:3px solid #55B1AE; padding-left:12px; margin:8px 0; color:#5A6464; }
</style>

<script>
function noteForm() {
    return {
        courseId: @json($note->course_id),
        modules: @json($modules->map(fn($m) => ['id' => $m->id, 'label' => "[{$m->sort_order}] {$m->title}"])),
        sections: @json($sections->map(fn($s) => ['id' => $s->id, 'label' => $s->title])),
        bodyMd: @json($note->body_markdown ?? ''),
        previewHtml: '',
        tagsList: @json($note->tags ?? []),
        tagDraft: '',
        tagSuggestions: [],
        uploading: false,

        init() {
            this.renderPreview();
        },

        renderPreview() {
            this.previewHtml = window.marked ? window.marked.parse(this.bodyMd || '') : (this.bodyMd || '');
        },

        async loadModulesAndSections() {
            if (!this.courseId) {
                this.modules = []; this.sections = []; return;
            }
            const [m, s] = await Promise.all([
                fetch(`/learn/knowledge-base/modules/${this.courseId}`).then(r => r.json()),
                fetch(`/learn/knowledge-base/sections/${this.courseId}`).then(r => r.json()),
            ]);
            this.modules = m; this.sections = s;
        },

        wrap(token) {
            const ta = this.$refs.editor;
            const start = ta.selectionStart, end = ta.selectionEnd;
            const sel = ta.value.substring(start, end);
            ta.value = ta.value.substring(0, start) + token + sel + token + ta.value.substring(end);
            this.bodyMd = ta.value;
            ta.focus(); ta.setSelectionRange(start + token.length, end + token.length);
            this.renderPreview();
        },

        prefix(text) {
            const ta = this.$refs.editor;
            const start = ta.selectionStart;
            ta.value = ta.value.substring(0, start) + text + ta.value.substring(start);
            this.bodyMd = ta.value;
            ta.focus(); ta.setSelectionRange(start + text.length, start + text.length);
            this.renderPreview();
        },

        insertLink() {
            const url = prompt('URL del link:');
            if (!url) return;
            const text = prompt('Testo del link:', 'qui');
            const md = `[${text || url}](${url})`;
            this.prefix(md);
        },

        async uploadImage(ev) {
            const file = ev.target.files[0];
            if (!file) return;
            this.uploading = true;
            const fd = new FormData();
            fd.append('image', file);
            fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content
                || document.querySelector('input[name=_token]').value);
            try {
                const res = await fetch('/learn/knowledge-base/upload-image', {
                    method: 'POST', body: fd,
                });
                const data = await res.json();
                if (data.markdown) this.prefix('\n' + data.markdown + '\n');
            } catch (e) {
                alert('Upload fallito: ' + e.message);
            }
            this.uploading = false;
            ev.target.value = '';
        },

        addTag() {
            const t = this.tagDraft.trim().toLowerCase().replace(/^#/, '');
            if (!t || this.tagsList.includes(t) || this.tagsList.length >= 10) return;
            this.tagsList.push(t);
            this.tagDraft = ''; this.tagSuggestions = [];
        },

        removeTag(i) { this.tagsList.splice(i, 1); },

        async suggestTags() {
            if (!this.tagDraft) { this.tagSuggestions = []; return; }
            const res = await fetch(`/learn/knowledge-base/tag-suggest?q=${encodeURIComponent(this.tagDraft)}`);
            const tags = await res.json();
            this.tagSuggestions = tags.filter(t => !this.tagsList.includes(t));
        },

        addTagFromSuggestion(t) {
            if (!this.tagsList.includes(t) && this.tagsList.length < 10) {
                this.tagsList.push(t);
            }
            this.tagDraft = ''; this.tagSuggestions = [];
        },

        deleteNote() {
            document.getElementById('delete-form')?.submit();
        },
    };
}
</script>
@endsection
