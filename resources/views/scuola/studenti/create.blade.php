@extends('layouts.scuola')
@section('title', 'Aggiungi studente')
@section('breadcrumb', 'Studenti / Aggiungi')
@section('content')
<div style="max-width:560px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.studenti.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Studenti</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Aggiungi studente</h1>

    @if(session('error'))<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    @if($classes->isEmpty())
        <div style="background:#FBF3E2; border-left:4px solid #E2A653; border-radius:6px; padding:12px 14px; color:#9A7B2E; font-size:0.85rem;">
            Nessuna classe disponibile. <a href="{{ route('scuola.classi.create') }}" style="color:#9A7B2E; font-weight:600;">Crea prima una classe</a>.
        </div>
    @else
    <form method="POST" action="{{ route('scuola.studenti.store') }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div><label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome *</label>
                <input type="text" name="nome" value="{{ old('nome') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;"></div>
            <div><label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Cognome *</label>
                <input type="text" name="cognome" value="{{ old('cognome') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;"></div>
        </div>

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Email (opzionale)</label>
        <input type="email" name="email" value="{{ old('email') }}" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:4px;">
        <p style="font-size:0.75rem; color:#8A9696; margin:0 0 14px;">Con email → invito via email. Senza email → username interno + password temporanea mostrata una volta.</p>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div><label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Data di nascita *</label>
                <input type="date" name="data_nascita" value="{{ old('data_nascita') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;"></div>
            <div><label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Classe *</label>
                <select name="class_id" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
                    <option value="">— seleziona —</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}" @selected(old('class_id')===$c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <label style="display:flex; gap:8px; align-items:center; font-size:0.82rem; color:#4A5252; margin-bottom:18px;">
            <input type="checkbox" name="consent" value="1"> Consenso al trattamento acquisito (audit, opzionale)
        </label>

        <button data-busy-label="Aggiungo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Aggiungi studente</button>
    </form>
    @endif
</div>
@endsection
