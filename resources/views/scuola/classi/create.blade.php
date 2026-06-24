@extends('layouts.scuola')
@section('title', 'Nuova classe')
@section('breadcrumb', 'Classi / Nuova')
@section('content')
<div style="max-width:560px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.classi.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Classi</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Nuova classe</h1>

    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('scuola.classi.store') }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf
        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome *</label>
        <input type="text" name="name" value="{{ old('name') }}" placeholder="es. 3A" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Anno scolastico *</label>
        <input type="text" name="school_year" value="{{ old('school_year', '2026/2027') }}" placeholder="2026/2027" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Coordinatore (opzionale)</label>
        <select name="coordinator_id" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:18px;">
            <option value="">— nessuno —</option>
            @foreach($teachers as $t)<option value="{{ $t->id }}" @selected(old('coordinator_id')===$t->id)>{{ $t->name }}</option>@endforeach
        </select>

        <button data-busy-label="Creo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Crea classe</button>
    </form>
</div>
@endsection
