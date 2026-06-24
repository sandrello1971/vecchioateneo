@php($isEdit = $question->exists)
@extends('layouts.admin')
@section('title', $isEdit ? 'Modifica Domanda' : 'Nuova Domanda')
@section('content')
<div style="max-width:700px;">
    <a href="/admin/quizzes/{{ $quiz->id }}/questions" style="color:#8A9696; font-size:0.8rem;">&larr; Domande</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin:8px 0 20px;">
        {{ $isEdit ? 'Modifica domanda' : 'Nuova domanda' }}
    </h2>

    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="{{ $isEdit
            ? route('admin.quizzes.questions.update', [$quiz, $question])
            : route('admin.quizzes.questions.store', $quiz) }}">
            @csrf
            @if($isEdit) @method('PUT') @endif
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Domanda *</label>
                    <textarea name="question" rows="3" required
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">{{ $question->question }}</textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Tipo</label>
                        <select name="type" style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                            <option value="multiple_choice" {{ $question->type==='multiple_choice'?'selected':'' }}>Risposta multipla</option>
                            <option value="true_false" {{ $question->type==='true_false'?'selected':'' }}>Vero/Falso</option>
                            <option value="open" {{ $question->type==='open'?'selected':'' }}>Risposta aperta</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Punti</label>
                        <input type="number" name="points" value="{{ $question->points }}" min="1"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">
                        Opzioni (una per riga) — metti &#10003; o * davanti alla risposta corretta
                    </label>
                    <textarea name="options_text" rows="5"
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none; font-family:monospace;">@foreach($question->options ?? [] as $opt){{ $opt === $question->correct_answer ? '&#10003; ' : '' }}{{ $opt }}
@endforeach</textarea>
                    <p style="font-size:0.75rem; color:#8A9696; margin-top:4px;">Es: &#10003; La risposta giusta</p>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Spiegazione (mostrata dopo la risposta)</label>
                    <textarea name="explanation" rows="2"
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">{{ $question->explanation }}</textarea>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <a href="/admin/quizzes/{{ $quiz->id }}/questions"
                       style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit"
                            style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        {{ $isEdit ? 'Salva modifiche' : 'Crea domanda' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
