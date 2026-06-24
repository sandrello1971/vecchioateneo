@extends('layouts.admin')
@section('title', 'Modifica corso: ' . $course->name)
@section('content')

<div style="max-width:700px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
        <a href="/admin/courses" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Corsi</a>
        <span style="color:#C8D0D0;">|</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Modifica {{ $course->name }}</h2>
        <div style="margin-left:auto; display:flex; gap:6px;">
            <a href="/admin/courses/{{ $course->id }}"
               style="padding:6px 12px; background:white; color:#1A1F1F; border:1px solid #D1D5DB;
                      border-radius:6px; font-size:0.78rem; font-weight:600; text-decoration:none;">
                &#128462; Dettaglio
            </a>
            <a href="/admin/courses/{{ $course->id }}/concept-maps"
               style="padding:6px 12px; background:#E8F5F5; color:#3D8B88; border:1px solid #55B1AE;
                      border-radius:6px; font-size:0.78rem; font-weight:600; text-decoration:none;">
                🧭 Mappe concettuali
            </a>
        </div>
    </div>

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12);
                border:1px solid rgba(85,177,174,0.4); border-radius:8px;
                color:#3A8C89; margin-bottom:14px; font-size:0.85rem;">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="padding:10px 14px; background:rgba(226,82,82,0.12);
                border:1px solid rgba(226,82,82,0.4); border-radius:8px;
                color:#C52A2A; margin-bottom:14px; font-size:0.85rem;">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="/admin/courses/{{ $course->id }}" id="edit-course-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div style="background:#FDECE2; border:1px solid #E28A53; color:#A8521F; border-radius:8px; padding:14px 16px; margin-bottom:16px; font-size:0.85rem;">
                    <strong>Impossibile salvare le modifiche. Correggi questi errori:</strong>
                    <ul style="margin:8px 0 0 18px; padding:0;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display:grid; gap:16px;">
                <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome *</label>
                        <input type="text" name="name" value="{{ old('name', $course->name) }}" required
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                        @error('name')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug', $course->slug) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                        @error('slug')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione breve</label>
                    <input type="text" name="short_description" value="{{ old('short_description', $course->short_description) }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione</label>
                    <textarea name="description" rows="4"
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none; resize:vertical;">{{ old('description', $course->description) }}</textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Icona</label>
                        <input type="text" name="icon" value="{{ old('icon', $course->icon) }}" placeholder="🎯"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Colore</label>
                        <input type="text" name="color" value="{{ old('color', $course->color) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ore</label>
                        <input type="number" name="duration_hours" value="{{ old('duration_hours', $course->duration_hours) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ordine</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $course->sort_order) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Certificazione</label>
                    <input type="text" name="certification_name" value="{{ old('certification_name', $course->certification_name) }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>

                <div>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $course->is_active) ? 'checked' : '' }}>
                        <span style="font-size:0.875rem; color:#1A1F1F;">Corso attivo</span>
                    </label>
                </div>

                <div style="background:linear-gradient(135deg,#1A1F1F,#252B2B); border-radius:10px; padding:20px; margin-top:8px;">
                    <h3 style="color:#55B1AE; font-weight:700; margin-bottom:12px; font-size:0.9rem;">🎬 Video del corso</h3>
                    <p style="color:#8A9696; font-size:0.75rem; margin-bottom:12px; line-height:1.5;">
                        Il video del corso vale come video introduttivo/generale: sarà disponibile sulla pagina del corso e la trascrizione sarà indicizzata da Minerva per l'intero corso.
                    </p>

                    @if($course->video_ai_id)
                    <div style="padding:10px 14px; background:rgba(85,177,174,0.1); border-radius:8px; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between;">
                        <div>
                            <div style="color:#55B1AE; font-size:0.85rem; font-weight:600;">✓ {{ $course->video_filename }}</div>
                            <div style="color:#8A9696; font-size:0.75rem;">Status: {{ $course->video_status }}</div>
                        </div>
                        <div style="font-size:0.75rem; color:#8A9696; font-family:monospace;">{{ substr($course->video_ai_id, 0, 12) }}...</div>
                    </div>
                    @endif

                    <div>
                        <label style="font-size:0.8rem; color:#8A9696; display:block; margin-bottom:6px;">
                            {{ $course->video_ai_id ? 'Sostituisci video' : 'Carica video' }} (MP4, MOV, AVI — max 2GB)
                        </label>
                        <input type="file" name="video_file" accept="video/*"
                               style="width:100%; padding:10px; border:1px dashed rgba(85,177,174,0.4); border-radius:8px; color:#8A9696; font-size:0.8rem; background:rgba(255,255,255,0.05);">
                        <p style="color:#4A5252; font-size:0.75rem; margin-top:6px;">
                            Il video verrà trascritto automaticamente con AI e indicizzato per il chatbot Minerva.
                        </p>
                    </div>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <a href="/admin/courses" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Salva modifiche
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Sezione Materiali Formatore --}}
    <div style="background:white; border:1px solid #E8F5F5; border-radius:12px; padding:20px; margin:20px 0;">

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
            <div style="font-size:1.3rem;">🎓</div>
            <div style="font-weight:700; color:#1A1F1F; font-size:1rem;">
                Materiali Formatore
            </div>
            <div style="margin-left:auto; padding:3px 10px; background:rgba(226,138,83,0.2);
                        color:#D87840; border-radius:12px; font-size:0.7rem; font-weight:700;">
                SOLO DOCENTI
            </div>
        </div>

        <p style="font-size:0.8rem; color:#8A9696; margin-bottom:16px;">
            Questi documenti sono visibili solo agli utenti con ruolo "instructor" dentro la pagina del corso lato studenti.
        </p>

        @forelse($course->instructorMaterials as $im)
        <div style="border:1px solid #E8F5F5; border-radius:10px; padding:14px; margin-bottom:10px;">

            <form method="POST"
                  action="{{ route('admin.courses.instructor-materials.update', [$course, $im->id]) }}"
                  enctype="multipart/form-data"
                  style="display:flex; flex-direction:column; gap:10px;">
                @csrf
                @method('PUT')

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div style="flex:2; min-width:200px;">
                        <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Titolo</label>
                        <input type="text" name="title" value="{{ $im->title }}" required
                               style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.85rem;">
                    </div>
                    <div style="flex:3; min-width:200px;">
                        <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">Descrizione</label>
                        <input type="text" name="description" value="{{ $im->description }}"
                               style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.85rem;">
                    </div>
                </div>

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">
                            Sostituisci file .docx o .md (opzionale)
                        </label>
                        <input type="file" name="docx" accept=".docx,.doc,.md,.markdown"
                               style="width:100%; font-size:0.8rem;">
                    </div>
                    <div style="font-size:0.75rem; color:#8A9696;">
                        File attuale: <strong>{{ basename($im->file_path) }}</strong>
                        ({{ number_format(($im->file_size ?? 0) / 1024, 1) }} KB)
                    </div>
                </div>

                @include('admin.courses.partials.source-overwrite-warning')

                <div style="display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
                    <button type="submit"
                            style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                        💾 Salva modifiche
                    </button>
                </div>
            </form>

            <div style="display:flex; gap:8px; margin-top:10px; padding-top:10px; border-top:1px solid #F5F7F7; flex-wrap:wrap;">

                <form method="POST"
                      action="{{ route('admin.courses.instructor-materials.regenerate', [$course, $im->id]) }}"
                      onsubmit="return confirm('Rigenerare HTML dal .docx esistente? Il file non cambia, solo il rendering online.');">
                    @csrf
                    <button type="submit"
                            style="padding:6px 12px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                        ↻ Rigenera HTML
                    </button>
                </form>

                <div style="flex:1;"></div>

                <a href="{{ route('admin.courses.instructor-materials.sections', [$course->id, $im->id]) }}"
                   style="padding:6px 12px; background:white; color:#D87840;
                          border:1px solid #E28A53; border-radius:6px;
                          text-decoration:none; font-size:0.75rem; font-weight:600;
                          display:inline-flex; align-items:center; gap:4px;">
                    📑 Gestisci sezioni
                </a>

                <form method="POST"
                      action="{{ route('admin.courses.instructor-materials.destroy', [$course, $im->id]) }}"
                      onsubmit="return confirm('Eliminare definitivamente questo manuale formatore? L\'azione è irreversibile.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            style="padding:6px 12px; background:white; color:#C52A2A; border:1px solid #E28282; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                        🗑 Elimina
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div style="color:#8A9696; font-size:0.85rem; padding:20px; text-align:center;
                    background:#F5F7F7; border-radius:8px; margin-bottom:14px;">
            Nessun manuale formatore caricato per questo corso.
        </div>
        @endforelse

        <div style="background:#F5F7F7; border-radius:10px; padding:16px; margin-top:16px;">
            <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem; margin-bottom:10px;">
                ➕ Carica nuovo manuale
            </div>
            <form method="POST"
                  action="{{ route('admin.courses.instructor-materials.store', $course) }}"
                  enctype="multipart/form-data"
                  style="display:flex; flex-direction:column; gap:10px;">
                @csrf
                <input type="text" name="title" placeholder="Titolo (es. 'Manuale Formatore v5.0')" required
                       style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.85rem;">
                <input type="text" name="description" placeholder="Descrizione (opzionale)"
                       style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.85rem;">
                <input type="file" name="docx" accept=".docx,.doc,.md,.markdown" required
                       style="font-size:0.8rem;">
                <div style="font-size:0.72rem; color:#8A9696;">DOCX (Word) o Markdown (.md): genera HTML di consultazione + sorgente Freshness.</div>
                @include('admin.courses.partials.source-overwrite-warning')
                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit"
                            style="padding:8px 16px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                        📤 Carica manuale
                    </button>
                </div>
            </form>
            <div style="font-size:0.7rem; color:#8A9696; margin-top:8px;">
                Formato: .docx o .doc • Max 20 MB • Il file verrà convertito automaticamente in HTML per la consultazione online.
            </div>
        </div>
    </div>

    {{-- Form elimina separata --}}
    <div style="background:white; border-radius:10px; padding:16px 24px; margin-top:16px; border-left:4px solid #E28A53;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <div>
                <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">Zona pericolosa</div>
                <div style="font-size:0.75rem; color:#8A9696;">Elimina definitivamente il corso e tutti i suoi moduli.</div>
            </div>
            <form method="POST" action="/admin/courses/{{ $course->id }}" onsubmit="return confirm('Eliminare il corso {{ $course->name }}? Questa azione non e reversibile.')">
                @csrf
                @method('DELETE')
                <button type="submit" style="padding:8px 16px; border:1px solid #E28A53; color:#E28A53; background:white; border-radius:8px; font-size:0.8rem; cursor:pointer; font-weight:600;">Elimina corso</button>
            </form>
        </div>
    </div>
</div>

@endsection
