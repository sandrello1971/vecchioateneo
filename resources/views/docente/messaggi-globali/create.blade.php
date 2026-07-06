@extends('layouts.docente')
@section('title', 'Nuovo messaggio')
@section('breadcrumb', 'Messaggi / Nuovo')
@section('content')
<div style="max-width:680px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.messages.index') }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; Messaggi</a></div>
    <h1 style="font-size:1.3rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Nuovo messaggio</h1>

    @if($errors->any())<div style="background:#FDECE2; border-left:4px solid #E28A53; color:#A8521F; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($classes->isEmpty())
        <p style="color:#8A9696;">Non insegni in nessuna classe.</p>
    @else
    <form method="POST" action="{{ route('docente.messages.store') }}" data-async
          x-data="{ cls: @js(old('school_class_id', '')), students: @js($studentsByClass) }"
          style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; display:flex; flex-direction:column; gap:12px;">
        @csrf
        <label style="font-size:0.82rem; color:#4A5252; font-weight:600;">Classe
            <select name="school_class_id" required x-model="cls" style="width:100%; margin-top:4px; padding:9px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem;">
                <option value="">Seleziona…</option>
                @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </label>

        <label style="font-size:0.82rem; color:#4A5252; font-weight:600;">Destinatario
            <select name="recipient" required :disabled="!cls" style="width:100%; margin-top:4px; padding:9px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem;">
                <option value="all" x-text="'📢 Tutta la classe (' + ((students[cls]||[]).length) + ' studenti)'"></option>
                <optgroup label="Singolo studente">
                    <template x-for="s in (students[cls] || [])" :key="s.id">
                        <option :value="s.id" x-text="s.name"></option>
                    </template>
                </optgroup>
            </select>
        </label>
        <p style="font-size:0.75rem; color:#8A9696; margin:-6px 0 0;">Con «Tutta la classe» ogni studente riceve un thread privato: risponde solo a te, non ai compagni.</p>

        <label style="font-size:0.82rem; color:#4A5252; font-weight:600;">Oggetto
            <input type="text" name="subject" required minlength="3" maxlength="200" value="{{ old('subject') }}" style="width:100%; margin-top:4px; padding:9px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem;">
        </label>
        <label style="font-size:0.82rem; color:#4A5252; font-weight:600;">Messaggio
            <textarea name="body" required rows="6" maxlength="5000" style="width:100%; margin-top:4px; padding:10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem; font-family:inherit;">{{ old('body') }}</textarea>
        </label>
        <div><button data-busy-label="Invio…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer;">Invia messaggio</button></div>
    </form>
    @endif
</div>
@endsection
