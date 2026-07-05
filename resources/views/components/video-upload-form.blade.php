@props([
    'lesson' => null,        // contesto Lezione: video legato + materia auto
    'subjects' => null,      // standalone (materiale): select Materia
    'videoAiDpaMissing' => false,
])
{{-- Upload di un VIDEO caricato dal docente → analisi Vision (videoai). Riusato dalla
     Lezione (più video ammessi) e dalla sezione Materiali. --}}
<div x-data="videoUploadForm()">
    @if($videoAiDpaMissing)
        <div style="padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.82rem;">
            ⚠️ <strong>Upload video disabilitato — DPA video-AI mancante.</strong>
            Il video passa da sub-processori esterni (trascrizione → Whisper, immagini → Vision): serve il consenso DPA video-AI della scuola.
        </div>
    @else
    <form method="POST" action="{{ route('docente.videos.store') }}" enctype="multipart/form-data" data-async
          style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf
        @if($lesson)<input type="hidden" name="lesson_id" value="{{ $lesson->id }}">@endif

        @if($lesson)
            <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Titolo del video *</label>
            <input type="text" name="title" value="{{ old('title') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
        @else
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Titolo del video *</label>
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

        <div style="margin-top:14px;">
            <div @dragover.prevent="dragover=true" @dragleave.prevent="dragover=false" @drop.prevent="onDrop($event)"
                 :style="dragover ? 'border-color:#55B1AE;background:#E8F5F5' : ''"
                 style="border:2px dashed #C8D0D0; border-radius:10px; padding:24px; text-align:center; transition:all .2s;">
                <input type="file" name="file" accept="video/mp4,video/quicktime,video/webm,video/x-matroska,video/x-msvideo" x-ref="file"
                       @change="name=$refs.file.files[0]?.name||''" required style="display:none">
                <p style="color:#8A9696; font-size:0.85rem;">Trascina qui il video (mp4/mov/webm) oppure
                    <button type="button" @click="$refs.file.click()" style="color:#55B1AE; background:none; border:none; cursor:pointer; text-decoration:underline;">scegli dal computer</button>
                </p>
                <p x-show="name" x-text="name" style="margin-top:8px; font-size:0.82rem; color:#1A1F1F; font-weight:600;"></p>
                <p style="margin-top:6px; font-size:0.72rem; color:#8A9696;">Il video verrà analizzato anche nelle immagini (diagrammi, icone, testo a schermo) e reso ricercabile al suo interno.</p>
            </div>
        </div>

        <button type="submit" style="margin-top:18px; padding:11px 22px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:700; cursor:pointer;" data-busy-label="Caricamento…">Carica e analizza</button>
    </form>
    @endif
</div>

@pushOnce('scripts', 'alpine-cdn')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endPushOnce
@pushOnce('scripts', 'video-upload-fn')
<script>
function videoUploadForm() {
    return {
        dragover: false,
        name: '',
        onDrop(e) {
            this.dragover = false;
            if (e.dataTransfer.files.length) { this.$refs.file.files = e.dataTransfer.files; this.name = e.dataTransfer.files[0].name; }
        },
    };
}
</script>
@endPushOnce
