@props([
    'lesson' => null,      // contesto Lezione: materiale legato alla lezione, materia auto
    'topic' => null,       // contesto Argomento: materiale nel pool, materia auto
    'subjects' => null,    // solo standalone: select Materia
    'videoAiDpaMissing' => false,
    'externalTypes' => [],
])
@php
    // In contesto lezione/argomento la materia è ereditata (nessun select) e il
    // redirect torna al contesto: lo store lo decide dai campi lesson_id/topic_id.
    $embedded = $lesson || $topic;
@endphp
<div x-data="materialForm()">
    @if($errors->any())<div style="margin-bottom:14px; padding:12px 16px; background:#FDECE2; border:1px solid #E28A53; border-radius:8px; color:#A8521F; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('docente.materials.store') }}" enctype="multipart/form-data" data-async @submit="prepare($event)"
          style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:22px;">
        @csrf
        @if($lesson)<input type="hidden" name="lesson_id" value="{{ $lesson->id }}">@endif
        @if($topic)<input type="hidden" name="topic_id" value="{{ $topic->id }}">@endif

        @if($embedded)
            <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Titolo *</label>
            <input type="text" name="title" value="{{ old('title') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
        @else
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Titolo *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Materia</label>
                    <select name="subject_id" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                        <option value="">—</option>
                        @foreach(($subjects ?? []) as $s)<option value="{{ $s->id }}" @selected(old('subject_id')===$s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
            </div>
        @endif

        @if(!empty($videoAiDpaMissing))
            {{-- R5 — gate DPA: audio/video/foto bloccati finché la scuola non registra il consenso. --}}
            <div style="margin-top:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.82rem;">
                ⚠️ <strong>Audio/video e foto disabilitati — DPA video-AI mancante.</strong>
                Per elaborarli serve il consenso al trattamento tramite sub-processori esterni (audio/video → Whisper, immagini → Vision). Configura il DPA video-AI della scuola. PDF, Word e testo restano disponibili (elaborazione locale).
            </div>
        @endif

        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-top:12px;">Tipo di sorgente *</label>
        <select name="source_type" x-model="type" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
            <option value="audio" @disabled(!empty($videoAiDpaMissing) && in_array('audio', $externalTypes ?? []))>Audio o video (verrà trascritta la traccia audio)@if(!empty($videoAiDpaMissing) && in_array('audio', $externalTypes ?? [])) — DPA mancante @endif</option>
            <option value="youtube" @disabled(!empty($videoAiDpaMissing) && in_array('youtube', $externalTypes ?? []))>Video YouTube (URL)@if(!empty($videoAiDpaMissing) && in_array('youtube', $externalTypes ?? [])) — DPA mancante @endif</option>
            <option value="photos" @disabled(!empty($videoAiDpaMissing) && in_array('photos', $externalTypes ?? []))>Foto multiple (jpg/png, max 20)@if(!empty($videoAiDpaMissing) && in_array('photos', $externalTypes ?? [])) — DPA mancante @endif</option>
            <option value="pdf">PDF</option>
            <option value="docx">Documento Word (docx)</option>
            <option value="text">Testo incollato</option>
        </select>

        {{-- File singolo (audio/pdf/docx) --}}
        <template x-if="['audio','pdf','docx'].includes(type)">
            <div style="margin-top:14px;">
                <div @dragover.prevent="dragover=true" @dragleave.prevent="dragover=false" @drop.prevent="onDropSingle($event)"
                     :style="dragover ? 'border-color:#55B1AE;background:#E8F5F5' : ''"
                     style="border:2px dashed #C8D0D0; border-radius:10px; padding:24px; text-align:center; transition:all .2s;">
                    <input type="file" name="file" x-ref="single" @change="singleName=$refs.single.files[0]?.name||''" style="display:none">
                    <p style="color:#8A9696; font-size:0.85rem;">Trascina qui il file oppure
                        <button type="button" @click="$refs.single.click()" style="color:#55B1AE; background:none; border:none; cursor:pointer; text-decoration:underline;">scegli dal computer</button>
                    </p>
                    <p x-show="singleName" x-text="singleName" style="margin-top:8px; font-size:0.82rem; color:#1A1F1F; font-weight:600;"></p>
                </div>
            </div>
        </template>

        {{-- YouTube --}}
        <template x-if="type==='youtube'">
            <div style="margin-top:14px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">URL YouTube *</label>
                <input type="url" name="source_url" value="{{ old('source_url') }}" placeholder="https://www.youtube.com/watch?v=…" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
            </div>
        </template>

        {{-- Testo --}}
        <template x-if="type==='text'">
            <div style="margin-top:14px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Testo *</label>
                <textarea name="text_content" rows="12" style="width:100%; padding:12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; font-family:monospace;">{{ old('text_content') }}</textarea>
            </div>
        </template>

        {{-- Foto multiple con drag&drop + riordino --}}
        <template x-if="type==='photos'">
            <div style="margin-top:14px;">
                <div @dragover.prevent="dragover=true" @dragleave.prevent="dragover=false" @drop.prevent="onDropPhotos($event)"
                     :style="dragover ? 'border-color:#55B1AE;background:#E8F5F5' : ''"
                     style="border:2px dashed #C8D0D0; border-radius:10px; padding:20px; text-align:center; transition:all .2s;">
                    <input type="file" name="photos[]" multiple accept="image/jpeg,image/png" x-ref="photos" @change="addPhotos($refs.photos.files)" style="display:none">
                    <p style="color:#8A9696; font-size:0.85rem;">Trascina le foto (max 20) oppure
                        <button type="button" @click="$refs.photos.click()" style="color:#55B1AE; background:none; border:none; cursor:pointer; text-decoration:underline;">scegli</button>.
                        Trascina le righe per riordinare.</p>
                </div>
                <ul style="list-style:none; padding:0; margin:12px 0 0;">
                    <template x-for="(p, idx) in photos" :key="p.key">
                        <li draggable="true" @dragstart="drag=idx" @dragover.prevent @drop="reorder(idx)"
                            style="display:flex; align-items:center; gap:10px; padding:8px 12px; background:#F5F7F7; border:1px solid #C8D0D0; border-radius:8px; margin-bottom:6px; cursor:move;">
                            <span style="color:#8A9696;">&#9776;</span>
                            <span style="font-size:0.7rem; font-weight:700; color:#55B1AE;" x-text="(idx+1)+'.'"></span>
                            <span style="flex:1; font-size:0.82rem; color:#1A1F1F;" x-text="p.file.name"></span>
                            <button type="button" @click="remove(idx)" style="color:#E28A53; background:none; border:none; cursor:pointer;">&times;</button>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-top:14px;">Tag (separati da virgola)</label>
        <input type="text" name="tags" value="{{ old('tags') }}" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">

        <button type="submit" style="margin-top:18px; padding:11px 22px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:700; cursor:pointer;" data-busy-label="Caricamento…">Carica e avvia estrazione</button>
    </form>
</div>

@pushOnce('scripts', 'alpine-cdn')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endPushOnce
@pushOnce('scripts', 'material-upload-fn')
<script>
function materialForm() {
    return {
        type: @json(old('source_type', 'audio')),
        dragover: false,
        singleName: '',
        photos: [],
        drag: null,
        _key: 0,
        onDropSingle(e) {
            this.dragover = false;
            if (e.dataTransfer.files.length) { this.$refs.single.files = e.dataTransfer.files; this.singleName = e.dataTransfer.files[0].name; }
        },
        addPhotos(fileList) {
            for (const f of fileList) { if (this.photos.length < 20) this.photos.push({key: this._key++, file: f}); }
        },
        onDropPhotos(e) { this.dragover = false; this.addPhotos(e.dataTransfer.files); },
        remove(i) { this.photos.splice(i, 1); },
        reorder(target) {
            if (this.drag === null || this.drag === target) return;
            const moved = this.photos.splice(this.drag, 1)[0];
            this.photos.splice(target, 0, moved);
            this.drag = null;
        },
        prepare(e) {
            // Riallinea l'input file all'ordine scelto prima dell'invio
            if (this.type === 'photos') {
                const dt = new DataTransfer();
                this.photos.forEach(p => dt.items.add(p.file));
                this.$refs.photos.files = dt.files;
            }
        },
    };
}
</script>
@endPushOnce
