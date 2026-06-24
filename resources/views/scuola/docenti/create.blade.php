@extends('layouts.scuola')
@section('title', 'Aggiungi docente')
@section('breadcrumb', 'Docenti / Aggiungi')
@section('content')
<div style="max-width:560px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.docenti.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Docenti</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Aggiungi docente</h1>

    @if(session('error'))<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('scuola.docenti.store') }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div><label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome *</label>
                <input type="text" name="nome" value="{{ old('nome') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;"></div>
            <div><label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Cognome *</label>
                <input type="text" name="cognome" value="{{ old('cognome') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;"></div>
        </div>

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Email *</label>
        <input type="email" name="email" value="{{ old('email') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">
        <p style="font-size:0.75rem; color:#8A9696; margin:-8px 0 14px;">Riceverà un invito via email con la password temporanea.</p>

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Materie (competenze)</label>
        <select name="materie[]" multiple size="6" style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem; margin-bottom:6px;">
            @foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
        <p style="font-size:0.75rem; color:#8A9696; margin:0 0 18px;">Tieni premuto Ctrl/Cmd per selezionarne più di una. Le materie ignote non vengono create.</p>

        <button data-busy-label="Aggiungo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Aggiungi docente</button>
    </form>
</div>
@endsection
