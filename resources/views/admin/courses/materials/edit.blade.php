@extends('layouts.admin')
@section('title', 'Modifica materiale')
@section('content')

<div style="max-width:700px;">
    <div style="margin-bottom:20px;">
        <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}"
           style="color:#8A9696; font-size:0.8rem;">← {{ $course->name }} › {{ $module->title }}</a>
        <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:6px;">
            Modifica: {{ $material->title }}
        </h1>
    </div>

    @if($errors->any())
    <div style="padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; margin-bottom:16px; color:#c97a45; font-size:0.875rem;">
        @foreach($errors->all() as $e)<div>⚠ {{ $e }}</div>@endforeach
    </div>
    @endif

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12); border:1px solid rgba(85,177,174,0.4); border-radius:8px; margin-bottom:14px; color:#3A8C89; font-size:0.85rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Info read-only sul materiale (il file non si sostituisce qui) --}}
    <div style="background:#F5F7F7; border-radius:10px; padding:14px 18px; margin-bottom:16px; font-size:0.8rem; color:#5A6464;">
        <div style="display:grid; grid-template-columns:auto 1fr; gap:6px 14px;">
            <span style="color:#8A9696;">Tipo:</span>
            <span style="font-weight:600;">
                @if($material->file_path)
                    📄 {{ strtoupper($material->file_type ?? 'FILE') }}
                @elseif($material->video_ai_id)
                    🎬 Video
                @elseif($material->external_url)
                    🔗 Link esterno
                @else
                    — sconosciuto —
                @endif
            </span>
            @if($material->file_path)
                <span style="color:#8A9696;">File:</span>
                <span style="font-family:monospace; font-size:0.75rem;">{{ basename($material->file_path) }}</span>
            @endif
            @if($material->external_url)
                <span style="color:#8A9696;">URL:</span>
                <a href="{{ $material->external_url }}" target="_blank" rel="noopener" style="color:#3A8C89; word-break:break-all;">{{ $material->external_url }}</a>
            @endif
            @if($material->file_size)
                <span style="color:#8A9696;">Dimensione:</span>
                <span>{{ number_format($material->file_size / 1024, 0) }} KB</span>
            @endif
        </div>
        <p style="font-size:0.72rem; color:#8A9696; margin-top:10px; font-style:italic;">
            Per sostituire il file: elimina questo materiale e ricreane uno nuovo dalla pagina del modulo.
        </p>
    </div>

    <div style="background:white; border-radius:12px; padding:24px;">
        <form method="POST"
              action="{{ route('admin.courses.modules.materials.update', [$course, $module, $material]) }}">
            @csrf @method('PUT')

            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Titolo *</label>
                    <input type="text" name="title" value="{{ old('title', $material->title) }}" required maxlength="255"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Descrizione</label>
                    <textarea name="description" rows="3" maxlength="500"
                              style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">{{ old('description', $material->description) }}</textarea>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}"
                   style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                    Annulla
                </a>
                <button type="submit"
                        style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Salva modifiche
                </button>
            </div>
        </form>

        {{-- Form delete separato (HTML non consente form annidati) --}}
        <div style="margin-top:14px; padding-top:14px; border-top:1px dashed #E8F5F5;">
            <form method="POST"
                  action="{{ route('admin.courses.modules.materials.destroy', [$course, $module, $material]) }}"
                  onsubmit="return confirm('Eliminare definitivamente questo materiale?');">
                @csrf @method('DELETE')
                <button type="submit"
                        style="padding:8px 14px; background:rgba(226,138,83,0.1); color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.78rem; cursor:pointer;">
                    Elimina materiale
                </button>
            </form>
        </div>
    </div>
</div>

@endsection
