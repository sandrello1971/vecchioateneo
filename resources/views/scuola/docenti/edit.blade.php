@extends('layouts.scuola')
@section('title', 'Modifica docente')
@section('breadcrumb', 'Docenti / Modifica')
@section('content')
<div style="max-width:560px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.docenti.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Docenti</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:4px;">Modifica docente</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin:0 0 16px;">{{ $teacher->email }}</p>

    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('scuola.docenti.update', $teacher) }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf
        @method('PATCH')

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome e cognome *</label>
        <input type="text" name="name" value="{{ old('name', $teacher->name) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Email *</label>
        <input type="email" name="email" value="{{ old('email', $teacher->email) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Materie (competenze)</label>
        <select name="materie[]" multiple size="6" style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem; margin-bottom:6px;">
            @foreach($subjects as $s)
                <option value="{{ $s->id }}" {{ in_array($s->id, old('materie', $selected)) ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
        </select>
        <p style="font-size:0.75rem; color:#8A9696; margin:0 0 18px;">Tieni premuto Ctrl/Cmd per selezionarne più di una.</p>

        <button data-busy-label="Salvo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Salva modifiche</button>
    </form>

    {{-- Azioni account --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-top:16px;">
        <h2 style="font-size:0.95rem; font-weight:700; color:#1A1F1F; margin:0 0 6px;">Account</h2>
        <p style="font-size:0.8rem; color:#8A9696; margin:0 0 14px;">
            Stato:
            @if($teacher->must_change_password)<span style="color:#E28A53; font-weight:600;">invito da completare</span>
            @elseif($teacher->is_active)<span style="color:#3A8C89; font-weight:600;">attivo</span>
            @else<span style="color:#A8521F; font-weight:600;">disattivato</span>@endif
        </p>

        <div style="display:flex; flex-wrap:wrap; gap:10px;">
            <form method="POST" action="{{ route('scuola.docenti.reset-password', $teacher) }}" data-async style="margin:0;">
                @csrf
                <button data-busy-label="Reimposto…" style="padding:9px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                    🔑 Reset password &amp; reinvia invito
                </button>
            </form>

            <form method="POST" action="{{ route('scuola.docenti.toggle', $teacher) }}" data-async
                  onsubmit="return confirm('{{ $teacher->is_active ? 'Disattivare l\'accesso di questo docente?' : 'Riattivare l\'accesso di questo docente?' }}');" style="margin:0;">
                @csrf
                @method('PATCH')
                @if($teacher->is_active)
                    <button data-busy-label="Aggiorno…" style="padding:9px 14px; background:white; color:#A8521F; border:1px solid rgba(168,82,31,0.4); border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Disattiva accesso</button>
                @else
                    <button data-busy-label="Aggiorno…" style="padding:9px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Riattiva accesso</button>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
