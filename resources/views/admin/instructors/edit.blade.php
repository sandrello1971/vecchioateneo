@extends('layouts.admin')
@section('title', 'Modifica formatore — ' . $instructor->name)
@section('content')

<div style="max-width:700px;">
    <div style="margin-bottom:20px;">
        <a href="{{ route('admin.instructors.show', $instructor) }}" style="color:#8A9696; font-size:0.8rem; text-decoration:none;">&larr; Dettaglio formatore</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">Modifica: {{ $instructor->name }}</h2>
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        {{ session('success') }}
    </div>
    @endif

    <div style="background:white; border-radius:10px; padding:24px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Dati anagrafici</h3>
        <form method="POST" action="{{ route('admin.instructors.update', $instructor) }}">
            @csrf @method('PUT')
            <div style="display:grid; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome completo *</label>
                    <input type="text" name="name" value="{{ old('name', $instructor->name) }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                    @error('name')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $instructor->email) }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                    @error('email')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Telefono</label>
                        <input type="text" name="phone" value="{{ old('phone', $instructor->phone) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Azienda</label>
                        <input type="text" name="company" value="{{ old('company', $instructor->company) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ruolo aziendale</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $instructor->job_title) }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                </div>

                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px; background:#F5F7F7; border-radius:8px;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $instructor->is_active) ? 'checked' : '' }}>
                    <div>
                        <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Formatore attivo</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Se disattivato, non potrà accedere alla piattaforma</div>
                    </div>
                </label>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <a href="{{ route('admin.instructors.show', $instructor) }}"
                       style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Salva modifiche
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div style="margin-top:16px; padding:12px 16px; background:#FFFBEB; border:1px solid rgba(226,138,83,0.3); border-radius:8px; font-size:0.8rem; color:#5A6464;">
        <strong>Nota:</strong> permessi sistema (ruolo) e credenziali si gestiscono dalla sezione
        <a href="{{ route('admin.students.edit', $instructor) }}" style="color:#3A8C89;">Discenti → Modifica</a>.
        L'associazione ai corsi insegnati si gestisce dal <a href="{{ route('admin.instructors.show', $instructor) }}" style="color:#3A8C89;">dettaglio formatore</a>.
    </div>
</div>

@endsection
