@extends('layouts.student')
@section('title', 'Risultato — ' . $quiz->title)
@section('breadcrumb', 'Quiz · ' . $quiz->title . ' · Risultato')

@php
    $course = $quiz->course ?? $quiz->module?->course;
    $score = (int) ($attempt->score ?? 0);
    $passed = (bool) $attempt->passed;
    $correctCount = $attempt->answers->where('is_correct', true)->count();
    $totalCount = $attempt->answers->count();
@endphp

@section('content')
<div style="max-width:800px;">

    <div style="background:white; border-radius:16px; padding:40px; text-align:center;">
        <div style="font-size:4rem; margin-bottom:16px;">{{ $passed ? '🎉' : '💪' }}</div>
        <h1 style="font-size:1.75rem; font-weight:700; margin-bottom:8px; color:{{ $passed ? '#3A8C89' : '#E28A53' }};">
            {{ $passed ? 'Quiz superato!' : 'Quasi!' }}
        </h1>
        <p style="color:#8A9696; margin-bottom:32px;">
            {{ $passed
                ? 'Ottimo lavoro! Hai dimostrato una buona comprensione dei contenuti.'
                : 'Non preoccuparti, ripassa i contenuti e riprova.' }}
        </p>

        {{-- PUNTEGGIO --}}
        <div style="position:relative; width:140px; height:140px; margin:0 auto 32px;">
            <svg viewBox="0 0 140 140" style="transform:rotate(-90deg);">
                <circle cx="70" cy="70" r="60" fill="none" stroke="#E8F5F5" stroke-width="12"/>
                <circle cx="70" cy="70" r="60" fill="none" stroke-width="12"
                        stroke="{{ $passed ? '#55B1AE' : '#E28A53' }}"
                        stroke-linecap="round"
                        stroke-dasharray="377"
                        stroke-dashoffset="{{ 377 - (377 * $score / 100) }}"/>
            </svg>
            <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                <div style="font-size:2rem; font-weight:700; color:#1A1F1F;">{{ $score }}%</div>
                <div style="font-size:0.7rem; color:#8A9696;">punteggio</div>
            </div>
        </div>

        {{-- DETTAGLIO --}}
        <div style="display:flex; justify-content:center; gap:32px; margin-bottom:32px;">
            <div>
                <div style="font-size:1.25rem; font-weight:700; color:#3A8C89;">{{ $correctCount }}</div>
                <div style="font-size:0.75rem; color:#8A9696;">Corrette</div>
            </div>
            <div>
                <div style="font-size:1.25rem; font-weight:700; color:#E28A53;">{{ $totalCount - $correctCount }}</div>
                <div style="font-size:0.75rem; color:#8A9696;">Errate</div>
            </div>
            <div>
                <div style="font-size:1.25rem; font-weight:700; color:#8A9696;">{{ $quiz->passing_score }}%</div>
                <div style="font-size:0.75rem; color:#8A9696;">Soglia</div>
            </div>
        </div>

        <div style="text-align:left; margin-bottom:32px;">
            <div style="font-size:0.8rem; font-weight:700; color:#4A5252; margin-bottom:10px;">Riepilogo risposte</div>
            @foreach($attempt->answers as $ans)
                @php $q = $ans->question; @endphp
                @if(!$q) @continue @endif
                <div style="padding:12px 0; border-bottom:1px solid #F5F7F7; display:flex; gap:10px; align-items:flex-start;">
                    <span style="font-size:0.9rem;">{{ $ans->is_correct ? '✅' : '❌' }}</span>
                    <div style="flex:1;">
                        <div style="font-size:0.85rem; color:#1A1F1F; font-weight:500;">{{ $q->question }}</div>
                        <div style="font-size:0.75rem; color:#8A9696; margin-top:2px;">
                            La tua risposta:
                            <span style="font-weight:600; color:{{ $ans->is_correct ? '#3A8C89' : '#E28A53' }};">
                                {{ $ans->answer ?: 'Non risposta' }}
                            </span>
                        </div>
                        @if(!$ans->is_correct)
                        <div style="font-size:0.75rem; color:#3A8C89; margin-top:2px;">
                            Risposta corretta: <span style="font-weight:600;">{{ $q->correct_answer }}</span>
                        </div>
                        @endif
                        @if($q->explanation)
                        <div style="font-size:0.75rem; color:#4A5252; margin-top:6px; padding:8px 10px; background:#E8F5F5; border-left:3px solid #55B1AE; border-radius:0 6px 6px 0; line-height:1.5;">
                            💡 {{ $q->explanation }}
                        </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            @if($course)
            <a href="/learn/course/{{ $course->slug }}"
               style="padding:10px 24px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                ← Torna al corso
            </a>
            @endif
            @if(!$passed)
            <a href="/learn/quiz/{{ $quiz->id }}"
               style="padding:10px 24px; background:#E28A53; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
                Riprova
            </a>
            @endif
        </div>
    </div>

</div>
@endsection
