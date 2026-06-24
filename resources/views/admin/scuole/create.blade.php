@extends('layouts.admin')
@section('title', 'Nuova scuola')
@section('content')
<div style="max-width:620px;">
    <div style="margin-bottom:8px;"><a href="{{ route('admin.scuole.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Scuole</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Nuova scuola</h1>

    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.scuole.store') }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf
        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome *</label>
        <input type="text" name="name" value="{{ old('name') }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Slug (opzionale, generato dal nome)</label>
        <input type="text" name="slug" value="{{ old('slug') }}" placeholder="es. liceo-galilei" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Tipo *</label>
        <select name="type" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">
            <option value="liceo">Liceo</option>
            <option value="istituto_tecnico">Istituto tecnico</option>
            <option value="altro">Altro</option>
        </select>

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Città</label>
        <input type="text" name="city" value="{{ old('city') }}" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:flex; gap:8px; align-items:center; font-size:0.82rem; color:#4A5252; margin-bottom:18px;">
            <input type="checkbox" name="allow_professor_create_classes" value="1">
            Consenti ai docenti di creare classi (deroga al modello scolastico puro)
        </label>

        <button data-busy-label="Creo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Crea scuola</button>
    </form>
</div>
@endsection
