@extends('layouts.scuola')
@section('title', 'Modifica studente')
@section('breadcrumb', 'Studenti / Modifica')
@section('content')
<div style="max-width:560px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.studenti.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Studenti</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:4px;">Modifica studente</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin:0 0 16px;">{{ $student->email ?? $student->username }}</p>

    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('scuola.studenti.update', $student) }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf
        @method('PATCH')

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome e cognome *</label>
        <input type="text" name="name" value="{{ old('name', $student->name) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Email</label>
        <input type="email" name="email" value="{{ old('email', $student->email) }}" placeholder="opzionale — se assente, accesso via username" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:4px;">
        <p style="font-size:0.75rem; color:#8A9696; margin:0 0 14px;">Correggi qui l'indirizzo se l'invito non è arrivato. Per applicarlo, reinvia l'invito qui sotto.</p>

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Data di nascita *</label>
        <input type="date" name="data_nascita" value="{{ old('data_nascita', $student->birth_date?->format('Y-m-d')) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Classe</label>
        <input type="text" value="{{ $student->classEnrollments->first()?->schoolClass?->name ?? '—' }}" disabled style="width:100%; padding:9px 12px; border:1px solid #E0E5E5; border-radius:8px; font-size:0.9rem; background:#F5F7F7; color:#8A9696; margin-bottom:4px;">
        <p style="font-size:0.75rem; color:#8A9696; margin:0 0 18px;">L'assegnazione alle classi si gestisce dalla sezione Classi.</p>

        <button data-busy-label="Salvo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Salva modifiche</button>
    </form>

    {{-- Azioni account --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-top:16px;">
        <h2 style="font-size:0.95rem; font-weight:700; color:#1A1F1F; margin:0 0 6px;">Account</h2>
        <p style="font-size:0.8rem; color:#8A9696; margin:0 0 14px;">
            Stato:
            @if($student->must_change_password)<span style="color:#E28A53; font-weight:600;">invito da completare</span>
            @elseif($student->is_active)<span style="color:#3A8C89; font-weight:600;">attivo</span>
            @else<span style="color:#A8521F; font-weight:600;">disattivato</span>@endif
        </p>

        <div style="display:flex; flex-wrap:wrap; gap:10px;">
            <form method="POST" action="{{ route('scuola.studenti.reset-password', $student) }}" data-async style="margin:0;">
                @csrf
                <button data-busy-label="Reimposto…" @disabled(!$student->email) title="{{ $student->email ? '' : 'Assegna prima un\'email' }}" style="padding:9px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                    🔑 Reset password &amp; reinvia invito
                </button>
            </form>

            <form method="POST" action="{{ route('scuola.studenti.toggle', $student) }}" data-async
                  onsubmit="return confirm('{{ $student->is_active ? 'Disattivare l\'accesso di questo studente?' : 'Riattivare l\'accesso di questo studente?' }}');" style="margin:0;">
                @csrf
                @method('PATCH')
                @if($student->is_active)
                    <button data-busy-label="Aggiorno…" style="padding:9px 14px; background:white; color:#A8521F; border:1px solid rgba(168,82,31,0.4); border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Disattiva accesso</button>
                @else
                    <button data-busy-label="Aggiorno…" style="padding:9px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Riattiva accesso</button>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
