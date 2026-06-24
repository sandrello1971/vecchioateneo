@extends('layouts.student')
@section('title', 'Nuovo annuncio')
@section('content')

<div style="max-width:700px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <a href="{{ route('student.announcements.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.875rem;">← Annunci</a>
        <span style="color:#C8D0D0;">/</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Nuovo annuncio</h2>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">

        <div style="background:#FFF8F1; border-left:4px solid #E28A53; padding:12px 16px; border-radius:6px; margin-bottom:20px; color:#7A5230; font-size:0.8rem; line-height:1.5;">
            📢 L'annuncio sarà inviato a <strong>tutti i discenti iscritti attivi</strong> del corso selezionato.
            Ricevono notifica nella barra laterale in tempo reale; non sono previste risposte dirette
            (per scrivere a un singolo discente usa <strong>Messaggi</strong>).
        </div>

        @if($errors->any())
        <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
            <ul style="margin:0; padding-left:16px; color:#C52A2A; font-size:0.85rem;">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('student.announcements.store') }}">
            @csrf

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Corso destinatario</label>
                <select name="course_id" required
                        style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; background:white;">
                    <option value="">— Seleziona un corso —</option>
                    @foreach($teachingCourses as $course)
                    <option value="{{ $course->id }}" {{ old('course_id') === $course->id ? 'selected' : '' }}>{{ $course->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Oggetto</label>
                <input type="text" name="subject" maxlength="200" required value="{{ old('subject') }}"
                       placeholder="Titolo breve dell'annuncio"
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Messaggio</label>
                <textarea name="body" rows="8" maxlength="5000" required
                          placeholder="Scrivi il messaggio dell'annuncio…"
                          style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; resize:vertical; min-height:160px;">{{ old('body') }}</textarea>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <a href="{{ route('student.announcements.index') }}"
                   style="padding:9px 18px; border:1px solid #C8D0D0; border-radius:8px; color:#4A5252; text-decoration:none; font-size:0.875rem; font-weight:600;">Annulla</a>
                <button type="submit" style="padding:9px 20px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">Pubblica annuncio</button>
            </div>
        </form>
    </div>
</div>

@endsection
