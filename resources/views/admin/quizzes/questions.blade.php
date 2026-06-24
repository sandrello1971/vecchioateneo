@extends('layouts.admin')
@section('title', 'Domande — ' . $quiz->title)
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <a href="/admin/quizzes" style="color:#8A9696; font-size:0.8rem;">&larr; Quiz</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            {{ $quiz->title }} — Domande ({{ $questions->count() }})
        </h2>
        <div style="font-size:0.8rem; color:#8A9696;">Soglia: {{ $quiz->passing_score }}%</div>
    </div>
    <a href="/admin/quizzes/{{ $quiz->id }}/questions/create"
       style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
        + Nuova domanda
    </a>
</div>

<div style="display:flex; flex-direction:column; gap:8px;">
    @forelse($questions as $i => $q)
    <div style="background:white; border-radius:10px; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <span style="background:#E8F5F5; color:#55B1AE; font-weight:700; font-size:0.8rem; padding:4px 8px; border-radius:4px; flex-shrink:0;">
                    Q{{ $i+1 }}
                </span>
                <div>
                    <div style="font-weight:600; color:#1A1F1F; line-height:1.5;">{{ $q->question }}</div>
                    <div style="font-size:0.75rem; color:#8A9696; margin-top:2px;">
                        {{ $q->type === 'multiple_choice' ? 'Risposta multipla' : ($q->type === 'true_false' ? 'Vero/Falso' : 'Risposta aperta') }}
                        &middot; {{ $q->points }} punto/i
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-shrink:0;">
                <a href="/admin/quizzes/{{ $quiz->id }}/questions/{{ $q->id }}/edit"
                   style="padding:5px 12px; background:#E8F5F5; color:#55B1AE; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                    Modifica
                </a>
                <form method="POST" action="/admin/quizzes/{{ $quiz->id }}/questions/{{ $q->id }}" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Eliminare questa domanda?')"
                            style="padding:5px 12px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                        Elimina
                    </button>
                </form>
            </div>
        </div>

        @if($q->options)
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:8px;">
            @foreach($q->options as $idx => $option)
            <div style="padding:8px 12px; border-radius:6px; font-size:0.85rem; display:flex; align-items:center; gap:8px;
                background:{{ $option === $q->correct_answer ? '#E8F5F5' : '#F5F7F7' }};
                border:1px solid {{ $option === $q->correct_answer ? '#55B1AE' : 'transparent' }};
                color:{{ $option === $q->correct_answer ? '#3A8C89' : '#4A5252' }};">
                <span style="font-weight:700; font-size:0.75rem;">{{ chr(65+$idx) }})</span>
                {{ $option }}
                @if($option === $q->correct_answer)
                <span style="margin-left:auto; font-size:0.75rem;">&#10003;</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if($q->explanation)
        <div style="margin-top:10px; padding:8px 12px; background:#F5F7F7; border-radius:6px; font-size:0.8rem; color:#4A5252; font-style:italic;">
            {{ $q->explanation }}
        </div>
        @endif
    </div>
    @empty
    <div style="background:white; border-radius:10px; padding:32px; text-align:center; color:#8A9696;">
        Nessuna domanda importata.
        <a href="/admin/quizzes/{{ $quiz->id }}/questions/create" style="color:#55B1AE;">Aggiungi la prima &rarr;</a>
    </div>
    @endforelse
</div>
@endsection
