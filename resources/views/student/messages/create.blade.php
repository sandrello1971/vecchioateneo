@extends('layouts.student')
@section('title', 'Nuova conversazione')
@section('content')

<div style="max-width:700px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <a href="{{ route('student.messages.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.875rem;">← Messaggi</a>
        <span style="color:#C8D0D0;">/</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Nuova conversazione</h2>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">
        <div style="display:flex; gap:12px; align-items:center; padding:12px 16px; background:#F5F7F7; border-radius:8px; margin-bottom:20px;">
            <div style="flex:1;">
                <div style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700; letter-spacing:0.05em;">Destinatario</div>
                <div style="font-weight:600; color:#1A1F1F; font-size:0.95rem;">{{ $instructor->name }}</div>
            </div>
            <div style="flex:1;">
                <div style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700; letter-spacing:0.05em;">Corso</div>
                <div style="font-weight:600; color:#55B1AE; font-size:0.95rem;">{{ $course->name }}</div>
            </div>
        </div>

        @if($errors->any())
        <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
            <ul style="margin:0; padding-left:16px; color:#C52A2A; font-size:0.85rem;">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('student.messages.store') }}">
            @csrf
            <input type="hidden" name="instructor_id" value="{{ $instructor->id }}">
            <input type="hidden" name="course_id" value="{{ $course->id }}">

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Oggetto</label>
                <input type="text" name="subject" maxlength="200" required value="{{ old('subject') }}"
                       placeholder="Breve titolo della conversazione"
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Messaggio</label>
                <textarea name="body" rows="8" maxlength="5000" required
                          placeholder="Scrivi il tuo messaggio…"
                          style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; resize:vertical; min-height:160px;">{{ old('body') }}</textarea>
                <div style="font-size:0.7rem; color:#8A9696; margin-top:4px; font-style:italic;">
                    Il destinatario riceverà una notifica email al primo messaggio. I messaggi successivi nello stesso thread non genereranno email.
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <a href="{{ route('student.messages.index') }}"
                   style="padding:9px 18px; border:1px solid #C8D0D0; border-radius:8px; color:#4A5252; text-decoration:none; font-size:0.875rem; font-weight:600;">Annulla</a>
                <button type="submit" style="padding:9px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">Invia</button>
            </div>
        </form>
    </div>
</div>

@endsection
