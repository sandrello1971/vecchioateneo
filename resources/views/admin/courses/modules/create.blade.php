@extends('layouts.admin')
@section('title', 'Nuovo Modulo')
@section('content')

<div style="max-width:700px;">
    <div style="margin-bottom:20px;">
        <a href="/admin/courses/{{ $course->id }}/modules" style="color:#8A9696; font-size:0.8rem;">
            &larr; {{ $course->name }}
        </a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">Nuovo modulo</h2>
    </div>

    @if($errors->any())
    <div style="background:#fff3ec; border-left:4px solid #E28A53; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#c97a45; font-size:0.875rem;">
        @foreach($errors->all() as $e)<div>⚠ {{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#FFFBEB; border:1px solid rgba(226,138,83,0.3); border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:0.78rem; color:#5A6464;">
        Imposta i campi essenziali. Dopo la creazione potrai aggiungere
        contenuto ricco (editor avanzato), materiali, video e quiz dalla
        pagina di modifica.
    </div>

    <form method="POST" action="{{ route('admin.courses.modules.store', $course) }}">
        @csrf

        <div style="background:white; border-radius:10px; padding:20px;">
            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Titolo *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Descrizione</label>
                    <textarea name="description" rows="2"
                              style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Contenuto iniziale (HTML)</label>
                    <textarea name="content" rows="6" placeholder="Lascia vuoto per editare con il rich editor nella pagina di modifica."
                              style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem; font-family:monospace; outline:none;">{{ old('content') }}</textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Durata (min)</label>
                        <input type="number" name="duration_minutes" value="{{ old('duration_minutes') }}" min="0"
                               style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Ordine</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $course->modules->count() + 1) }}" min="0"
                               style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:8px; background:#F5F7F7; border-radius:6px;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <span style="font-size:0.875rem; color:#4A5252;">Modulo attivo (visibile ai discenti iscritti)</span>
                </label>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:20px;">
                <a href="/admin/courses/{{ $course->id }}/modules"
                   style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                    Annulla
                </a>
                <button type="submit"
                        style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Crea modulo
                </button>
            </div>
        </div>
    </form>
</div>

@endsection
