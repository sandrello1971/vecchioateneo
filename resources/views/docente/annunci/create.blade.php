@extends('layouts.docente')
@section('title', 'Nuovo annuncio')
@section('breadcrumb', 'Classi / ' . $class->name . ' / Annunci / Nuovo')
@section('content')
<div style="max-width:680px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.classi.annunci.index', $class) }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; Annunci</a></div>
    <h1 style="font-size:1.3rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Nuovo annuncio alla classe</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">Lo vedranno tutti gli studenti attivi di {{ $class->name }} (sola lettura).</p>

    @if($errors->any())<div style="background:#FDECE2; border-left:4px solid #E28A53; color:#A8521F; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('docente.classi.annunci.store', $class) }}" data-async style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; display:flex; flex-direction:column; gap:12px;">
        @csrf
        <label style="font-size:0.82rem; color:#4A5252; font-weight:600;">Oggetto
            <input type="text" name="subject" required minlength="3" maxlength="200" value="{{ old('subject') }}" style="width:100%; margin-top:4px; padding:9px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem;">
        </label>
        <label style="font-size:0.82rem; color:#4A5252; font-weight:600;">Testo
            <textarea name="body" required rows="6" maxlength="5000" style="width:100%; margin-top:4px; padding:10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem; font-family:inherit;">{{ old('body') }}</textarea>
        </label>
        <div><button data-busy-label="Pubblico…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer;">Pubblica annuncio</button></div>
    </form>
</div>
@endsection
