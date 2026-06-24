@extends('layouts.admin')
@section('title', 'Nuovo Quiz')
@section('content')
<div style="max-width:600px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:20px;">Crea nuovo quiz</h2>
    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="/admin/quizzes">
            @csrf
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Titolo *</label>
                    <input type="text" name="title" required value="{{ old('title') }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione</label>
                    <textarea name="description" rows="3"
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">{{ old('description') }}</textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Corso</label>
                        <select name="course_id" style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                            <option value="">— Seleziona —</option>
                            @foreach($courses as $course)
                            <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>
                                {{ $course->icon }} {{ $course->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Soglia superamento %</label>
                        <input type="number" name="passing_score" value="{{ old('passing_score', 70) }}" min="0" max="100"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Tempo limite (minuti, 0=nessuno)</label>
                        <input type="number" name="time_limit_minutes" value="{{ old('time_limit_minutes', 0) }}" min="0"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Max tentativi (0=illimitati)</label>
                        <input type="number" name="max_attempts" value="{{ old('max_attempts', 0) }}" min="0"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>
                <div style="display:flex; gap:16px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="randomize_questions" value="1" {{ old('randomize_questions') ? 'checked' : '' }}>
                        <span style="font-size:0.875rem; color:#4A5252;">Randomizza domande</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span style="font-size:0.875rem; color:#4A5252;">Attivo</span>
                    </label>
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <a href="/admin/quizzes" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Crea quiz
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
