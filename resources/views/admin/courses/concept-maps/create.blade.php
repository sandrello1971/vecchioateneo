@extends('layouts.admin')
@section('title', $course->name . ' — Nuova mappa concettuale')
@section('content')

<div style="max-width:720px;">
    <a href="/admin/courses/{{ $course->id }}/concept-maps" style="color:#8A9696; font-size:0.8rem;">&larr; Mappe concettuali</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px; margin-bottom:18px;">
        Nuova mappa concettuale &middot; {{ $course->name }}
    </h2>

    @if($errors->any())
        <div style="padding:10px 14px; background:#FEE2E2; color:#991B1B; border-radius:6px; margin-bottom:14px; font-size:0.875rem;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="/admin/courses/{{ $course->id }}/concept-maps" method="POST"
          style="background:white; border-radius:10px; padding:24px;">
        @csrf
        <div style="margin-bottom:14px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Ambito</label>
            <select name="module_id" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.9rem;">
                <option value="" {{ $hasCourseMap ? 'disabled' : '' }}>
                    🌐 Intero corso {{ $hasCourseMap ? '(esiste già)' : '' }}
                </option>
                @foreach($modules as $mod)
                    @php $used = in_array($mod->id, $usedModuleIds); @endphp
                    <option value="{{ $mod->id }}" {{ $used ? 'disabled' : '' }} {{ old('module_id')===$mod->id ? 'selected' : '' }}>
                        📚 Modulo {{ $mod->sort_order }} — {{ $mod->title }} {{ $used ? '(esiste già)' : '' }}
                    </option>
                @endforeach
            </select>
            <p style="font-size:0.72rem; color:#8A9696; margin-top:4px;">
                Una mappa per modulo + una opzionale per l'intero corso.
            </p>
        </div>

        <div style="margin-bottom:14px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Titolo</label>
            <input type="text" name="title" required maxlength="255" value="{{ old('title') }}"
                   placeholder="Es. Mappa concettuale del corso"
                   style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.9rem;">
        </div>
        <div style="margin-bottom:14px;">
            <label style="display:block; font-size:0.8rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Descrizione (opzionale)</label>
            <textarea name="description" rows="3" maxlength="2000"
                      style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.9rem; resize:vertical;">{{ old('description') }}</textarea>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:18px;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Visibilità</label>
                <select name="visibility" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.9rem;">
                    <option value="draft" {{ old('visibility')==='draft'?'selected':'' }}>Draft (solo admin)</option>
                    <option value="published" {{ old('visibility')==='published'?'selected':'' }}>Published (visibile agli studenti)</option>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#1A1F1F; margin-bottom:4px;">Ordinamento</label>
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}"
                       style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.9rem;">
            </div>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <a href="/admin/courses/{{ $course->id }}/concept-maps"
               style="padding:8px 18px; background:white; color:#1A1F1F; border:1px solid #D1D5DB; border-radius:6px; font-size:0.875rem; font-weight:600; text-decoration:none;">
                Annulla
            </a>
            <button type="submit"
                    style="padding:8px 18px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer;">
                Crea mappa &rarr;
            </button>
        </div>
    </form>

    <p style="font-size:0.8rem; color:#8A9696; margin-top:14px;">
        Dopo la creazione potrai popolare la mappa con AI (genera dai contenuti dei moduli) oppure manualmente nel canvas editor.
    </p>
</div>
@endsection
