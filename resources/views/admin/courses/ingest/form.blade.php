@extends('layouts.admin')
@section('title', 'Crea corso da documenti')
@section('content')

<div style="max-width:800px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="/admin/courses" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Corsi</a>
        <span style="color:#C8D0D0;">|</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Crea corso da documenti</h2>
    </div>

    <div style="background:linear-gradient(135deg,#E8F5F5,#ffffff); border-radius:12px; padding:20px; margin-bottom:20px; border-left:4px solid #55B1AE;">
        <div style="font-size:0.85rem; color:#3A8C89; font-weight:700; margin-bottom:6px;">Come funziona</div>
        <div style="font-size:0.8rem; color:#4A5252; line-height:1.6;">
            1. Carichi il manuale discente (DOCX Word) → identificazione moduli (PARTE PRIMA/SECONDA…) automatica via pandoc, contenuti puliti.<br>
            2. Opzionalmente carichi il documento d'esame → Claude estrae le domande con opzioni e risposte corrette.<br>
            3. Vedi una preview e decidi cosa includere. Alla conferma vengono creati corso, moduli e quiz.
        </div>
    </div>

    @if(session('error'))
    <div style="margin-bottom:16px; padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; color:#c97a45; font-size:0.875rem;">
        ⚠ {{ session('error') }}
    </div>
    @endif

    <form method="POST" action="{{ route('admin.courses.ingest.parse') }}" enctype="multipart/form-data" style="background:white; border-radius:10px; padding:24px;">
        @csrf

        <div style="margin-bottom:20px;">
            <label style="font-size:0.85rem; font-weight:700; color:#1A1F1F; display:block; margin-bottom:8px;">📖 Manuale discente *</label>
            <input type="file" name="manual_file" accept=".docx,.doc,.md,.markdown" required
                   style="width:100%; padding:12px; border:2px dashed #55B1AE; border-radius:8px; font-size:0.85rem; background:#F5F7F7;">
            <p style="font-size:0.75rem; color:#8A9696; margin-top:6px;">
                DOCX (Word) con struttura "PARTE PRIMA — Titolo", "Capitolo N", "X.Y" —
                oppure Markdown strutturato (# = modulo, ## = sezione). Max 50MB.
            </p>
            @error('manual_file')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
        </div>

        <div style="margin-bottom:20px;">
            <label style="font-size:0.85rem; font-weight:700; color:#1A1F1F; display:block; margin-bottom:8px;">📝 Documento esame (opzionale)</label>
            <input type="file" name="exam_file" accept=".docx,.doc"
                   style="width:100%; padding:12px; border:2px dashed #C8D0D0; border-radius:8px; font-size:0.85rem; background:#F5F7F7;">
            <p style="font-size:0.75rem; color:#8A9696; margin-top:6px;">
                DOCX (Word) con domande a risposta multipla. Max 20MB.
            </p>
            @error('exam_file')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
        </div>

        <div style="border-top:1px solid #E8F5F5; padding-top:20px; margin-top:20px;">
            <div style="font-size:0.85rem; font-weight:700; color:#1A1F1F; margin-bottom:12px;">Impostazioni corso</div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Icona</label>
                    <input type="text" name="icon" value="{{ old('icon', '✦') }}"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Colore</label>
                    <input type="text" name="color" value="{{ old('color', '#55B1AE') }}"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Ore</label>
                    <input type="number" name="duration_hours" value="{{ old('duration_hours') }}"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Certificazione</label>
                    <input type="text" name="certification_name" value="{{ old('certification_name') }}"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
            </div>
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px;">
            <a href="/admin/courses" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
            <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Analizza documenti
            </button>
        </div>
    </form>

    <div id="loading-overlay" style="display:none; position:fixed; inset:0; background:rgba(26,31,31,0.8); z-index:100; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:32px; max-width:420px; text-align:center;">
            <div style="font-size:2rem; margin-bottom:12px;">✨</div>
            <div style="font-weight:700; color:#1A1F1F; margin-bottom:6px;">Claude sta analizzando i documenti...</div>
            <div style="color:#8A9696; font-size:0.85rem; line-height:1.5;">
                Può richiedere 30-90 secondi a seconda della lunghezza del manuale. Non chiudere la pagina.
            </div>
            <div style="margin-top:16px; height:4px; background:#E8F5F5; border-radius:2px; overflow:hidden;">
                <div style="height:100%; background:#55B1AE; width:100%; animation:pulse 1.5s ease-in-out infinite;"></div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}
</style>

<script>
document.querySelector('form').addEventListener('submit', () => {
    document.getElementById('loading-overlay').style.display = 'flex';
});
</script>

@endsection
