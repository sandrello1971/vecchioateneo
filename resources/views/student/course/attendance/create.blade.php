@extends('layouts.student')
@section('title', 'Nuova sessione — ' . $course->name)
@section('breadcrumb', 'Nuova sessione')
@section('content')
<div style="max-width:560px; margin:0 auto;">
    <a href="{{ route('student.course.sessions.index', $course->slug) }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none;">&larr; Sessioni</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin:6px 0 18px;">Nuova sessione sincrona</h2>

    @if($errors->any())
        <div style="background:#FDECEA; color:#C0392B; padding:10px 14px; border-radius:6px; margin-bottom:14px; font-size:0.85rem;">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form action="{{ route('student.course.sessions.store', $course->slug) }}" method="POST">
        @csrf
        @php $lbl = 'display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:5px;';
              $inp = 'width:100%; padding:9px 11px; border:1px solid #D1D5DB; border-radius:6px; font-size:0.9rem; margin-bottom:16px; box-sizing:border-box;'; @endphp

        <label style="{{ $lbl }}">Titolo</label>
        <input type="text" name="title" value="{{ old('title') }}" required maxlength="255" style="{{ $inp }}" placeholder="es. Lezione 1 — Introduzione">

        <label style="{{ $lbl }}">Data e ora</label>
        <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required style="{{ $inp }}">

        <label style="{{ $lbl }}">Durata (minuti)</label>
        <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 120) }}" required min="1" max="1440" style="{{ $inp }}">

        <label style="{{ $lbl }}">Modalità</label>
        <select name="modality" style="{{ $inp }}">
            <option value="in_person" @selected(old('modality')==='in_person')>In aula</option>
            <option value="live_online" @selected(old('modality')==='live_online')>Live online</option>
        </select>

        <label style="{{ $lbl }}">Luogo / link (opzionale)</label>
        <input type="text" name="location" value="{{ old('location') }}" maxlength="255" style="{{ $inp }}" placeholder="Aula magna oppure link meeting">

        <button type="submit" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">Crea sessione</button>
    </form>
</div>
@endsection
