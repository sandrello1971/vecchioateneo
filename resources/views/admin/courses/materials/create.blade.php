@extends('layouts.admin')
@section('title', 'Aggiungi materiale')
@section('content')

<div style="max-width:700px;">
    <div style="margin-bottom:20px;">
        <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}"
           style="color:#8A9696; font-size:0.8rem;">← {{ $course->name }} › {{ $module->title }}</a>
        <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:6px;">Aggiungi materiale</h1>
    </div>

    @if($errors->any())
    <div style="padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; margin-bottom:16px; color:#c97a45; font-size:0.875rem;">
        {{ $errors->first() }}
    </div>
    @endif

    <div style="background:white; border-radius:12px; padding:24px;">
        <form method="POST"
              action="{{ route('admin.courses.modules.materials.store', [$course, $module]) }}"
              enctype="multipart/form-data">
            @csrf

            <div style="margin-bottom:20px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:8px;">Tipo di materiale *</label>
                <div style="display:flex; gap:10px;">
                    <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid #C8D0D0; border-radius:8px; cursor:pointer; flex:1;"
                           onclick="showType('file')" id="tab-file" class="type-tab">
                        <input type="radio" name="type" value="file" checked style="display:none;">
                        <span style="font-size:1.2rem;">📄</span>
                        <span style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Documento PDF</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid #C8D0D0; border-radius:8px; cursor:pointer; flex:1;"
                           onclick="showType('video')" id="tab-video" class="type-tab">
                        <input type="radio" name="type" value="video" style="display:none;">
                        <span style="font-size:1.2rem;">🎬</span>
                        <span style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Video</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid #C8D0D0; border-radius:8px; cursor:pointer; flex:1;"
                           onclick="showType('url')" id="tab-url" class="type-tab">
                        <input type="radio" name="type" value="url" style="display:none;">
                        <span style="font-size:1.2rem;">🔗</span>
                        <span style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Link esterno</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid #C8D0D0; border-radius:8px; cursor:pointer; flex:1;"
                           onclick="showType('canvas')" id="tab-canvas" class="type-tab">
                        <input type="radio" name="type" value="canvas" style="display:none;">
                        <span style="font-size:1.2rem;">🎯</span>
                        <span style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Canvas interattivo</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Titolo *</label>
                <input type="text" name="title" required value="{{ old('title') }}"
                       placeholder="Es: Manuale del discente"
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione</label>
                <input type="text" name="description" value="{{ old('description') }}"
                       placeholder="Breve descrizione opzionale"
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
            </div>

            <div id="section-file" style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">
                    File (PDF, Word, Excel, PowerPoint — max 200MB)
                </label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt"
                       style="width:100%; padding:10px; border:1px dashed #C8D0D0; border-radius:8px; font-size:0.875rem; color:#4A5252;">
            </div>

            <div id="section-video" style="display:none; margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">
                    Video (MP4, MOV, AVI, WebM — max 2GB)
                </label>
                <input type="file" name="video_file" accept="video/*"
                       style="width:100%; padding:10px; border:1px dashed #C8D0D0; border-radius:8px; font-size:0.875rem; color:#4A5252;">
                <p style="color:#8A9696; font-size:0.75rem; margin-top:6px;">
                    Il video verrà trascritto automaticamente con AI e sarà disponibile nel modulo con chat e trascrizione sincronizzata.
                </p>
            </div>

            <div id="section-url" style="display:none; margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">URL esterno</label>
                <input type="url" name="url" value="{{ old('url') }}"
                       placeholder="https://..."
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
            </div>

            <div id="section-canvas" style="display:none; margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">File HTML del canvas *</label>
                <input type="file" name="canvas_file" accept=".html,.htm"
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none; background:white;">

                <div style="margin-top:14px; padding:12px 14px; background:#F0FAFA; border-left:4px solid #55B1AE; border-radius:6px; font-size:0.78rem; color:#1A1F1F; line-height:1.55;">
                    <div style="font-weight:700; margin-bottom:6px;">📋 Contratto canvas persistente</div>
                    Per salvare automaticamente le risposte dello studente in <code style="background:white; padding:1px 4px; border-radius:3px;">student_canvas_data</code>, l'HTML deve contenere uno script che:
                    <ol style="margin:6px 0 0 18px; padding:0;">
                        <li>Ricavi il <code>material_id</code> dall'URL (path <code>/learn/material/&lt;uuid&gt;/canvas</code>).</li>
                        <li>Faccia <code>GET /learn/canvas/{material_id}/data</code> al caricamento per ripopolare i campi.</li>
                        <li>Faccia <code>PATCH /learn/canvas/{material_id}/data</code> con header <code>X-XSRF-TOKEN</code> (dal cookie <code>XSRF-TOKEN</code>) e body <code>{"data": {...}}</code> a ogni modifica (con debounce).</li>
                    </ol>
                    Esempio funzionante: <code>storage/app/private/materials/consilium/canvas-1-mappa-processi.html</code>.<br>
                    I canvas statici (senza persistenza, come quelli di Rumore di fondo) sono comunque accettati: vengono mostrati ma le risposte non vengono salvate.
                </div>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px;">
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}"
                   style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                    Annulla
                </a>
                <button type="submit"
                        style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Carica materiale
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showType(type) {
    ['file','video','url','canvas'].forEach(t => {
        document.getElementById('section-' + t).style.display = t === type ? 'block' : 'none';
        document.getElementById('tab-' + t).style.borderColor = t === type ? '#55B1AE' : '#C8D0D0';
    });
    document.querySelector('input[name=type][value=' + type + ']').checked = true;
}
document.getElementById('tab-file').style.borderColor = '#55B1AE';
</script>
@endsection
