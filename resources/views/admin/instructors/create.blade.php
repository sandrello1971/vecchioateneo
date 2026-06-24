@extends('layouts.admin')
@section('title', 'Aggiungi Formatore')
@section('content')

<div style="max-width:600px;">
    <a href="{{ route('admin.instructors.index') }}" style="font-size:0.8rem; color:#8A9696; text-decoration:none;">← Formatori</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin:10px 0 20px;">Aggiungi formatore</h2>

    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="{{ route('admin.instructors.store') }}"
              x-data="{ sent:false }" @submit="sent=true">
            @csrf
            <div style="display:grid; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome completo *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('name')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('email')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Azienda</label>
                        <input type="text" name="company" value="{{ old('company') }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Telefono</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ruolo aziendale</label>
                    <input type="text" name="job_title" value="{{ old('job_title') }}"
                           placeholder="es. Senior Trainer"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>

                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="send_email" value="1" {{ old('send_email', '1') ? 'checked' : '' }}>
                    <span style="font-size:0.85rem; color:#4A5252;">Invia email di invito con password temporanea</span>
                </label>

                <div style="background:#FFF4E8; border:1px solid rgba(226,138,83,0.3); border-radius:8px; padding:12px;">
                    <div style="font-size:0.8rem; font-weight:600; color:#D87840; margin-bottom:4px;">ℹ️ Email già esistente?</div>
                    <div style="font-size:0.75rem; color:#4A5252;">Se l'email è già nel sistema (studente, docente, ecc.), l'account verrà <strong>promosso a Formatore</strong> mantenendo gli altri ruoli — niente duplicati.</div>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <a href="{{ route('admin.instructors.index') }}" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" :disabled="sent"
                            style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;"
                            :style="sent && 'opacity:0.6; cursor:wait;'">
                        <span x-show="!sent">Aggiungi formatore</span>
                        <span x-show="sent" x-cloak>Creazione…</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
