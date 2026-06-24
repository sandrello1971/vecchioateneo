@extends('layouts.admin')
@section('title', $quiz->title)
@section('content')
<div style="max-width:1000px;">
    <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px;">
        <div>
            <a href="{{ route('admin.quizzes.index') }}" style="color:#8A9696; font-size:0.8rem; text-decoration:none;">&larr; Quiz</a>
            <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">{{ $quiz->title }}</h2>
            @if($quiz->description)
            <p style="color:#5A6464; font-size:0.875rem; margin-top:4px;">{{ $quiz->description }}</p>
            @endif
        </div>
        <div style="display:flex; gap:8px; flex-shrink:0;">
            <a href="{{ route('admin.quizzes.questions.index', $quiz) }}"
               style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                Domande ({{ $quiz->questions->count() }})
            </a>
            <a href="{{ route('admin.quizzes.results', $quiz) }}"
               style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                Risultati
            </a>
            <a href="{{ route('admin.quizzes.edit', $quiz) }}"
               style="padding:8px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                Modifica
            </a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Metadata --}}
    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h3 style="font-size:0.9rem; font-weight:700; color:#1A1F1F; margin-bottom:12px;">Configurazione</h3>
        <dl style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
            <div>
                <dt style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</dt>
                <dd style="margin-top:4px;">
                    <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                        background:{{ $quiz->is_active ? '#E8F5F5' : '#F5F7F7' }};
                        color:{{ $quiz->is_active ? '#3A8C89' : '#8A9696' }};">
                        {{ $quiz->is_active ? 'Attivo' : 'Disattivato' }}
                    </span>
                </dd>
            </div>
            <div>
                <dt style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Ambito</dt>
                <dd style="margin-top:4px; font-size:0.85rem; color:#1A1F1F;">
                    @if($quiz->module)
                        Quiz di modulo
                        <div style="font-size:0.75rem; color:#8A9696; margin-top:2px;">
                            {{ $quiz->course?->name ?? '—' }} › {{ $quiz->module->title }}
                        </div>
                    @elseif($quiz->course)
                        Esame di corso
                        <div style="font-size:0.75rem; color:#8A9696; margin-top:2px;">{{ $quiz->course->name }}</div>
                    @else
                        — non associato —
                    @endif
                </dd>
            </div>
            <div>
                <dt style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Soglia superamento</dt>
                <dd style="margin-top:4px; font-size:0.85rem; color:#1A1F1F;">{{ $quiz->passing_score }}%</dd>
            </div>
            <div>
                <dt style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Tempo limite</dt>
                <dd style="margin-top:4px; font-size:0.85rem; color:#1A1F1F;">
                    {{ $quiz->time_limit_minutes ? $quiz->time_limit_minutes.' min' : 'nessuno' }}
                </dd>
            </div>
            <div>
                <dt style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Max tentativi</dt>
                <dd style="margin-top:4px; font-size:0.85rem; color:#1A1F1F;">
                    {{ $quiz->max_attempts ?: 'illimitati' }}
                </dd>
            </div>
            <div>
                <dt style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Randomizza domande</dt>
                <dd style="margin-top:4px; font-size:0.85rem; color:#1A1F1F;">
                    {{ $quiz->randomize_questions ? 'Sì' : 'No' }}
                </dd>
            </div>
        </dl>
    </div>

    {{-- Domande --}}
    <div style="background:white; border-radius:10px; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="font-size:0.9rem; font-weight:700; color:#1A1F1F;">Domande</h3>
            <a href="{{ route('admin.quizzes.questions.create', $quiz) }}"
               style="padding:6px 12px; background:#55B1AE; color:white; border-radius:5px; font-size:0.78rem; text-decoration:none; font-weight:600;">
                + Nuova domanda
            </a>
        </div>

        @if($quiz->questions->isEmpty())
            <p style="color:#8A9696; font-size:0.875rem; margin:14px 0;">
                Nessuna domanda. <a href="{{ route('admin.quizzes.questions.create', $quiz) }}" style="color:#3A8C89;">Crea la prima</a>.
            </p>
        @else
            <ol style="padding-left:22px; margin:0; display:flex; flex-direction:column; gap:8px;">
                @foreach($quiz->questions->sortBy('sort_order') as $q)
                <li style="font-size:0.875rem; color:#1A1F1F;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <span>{{ \Illuminate\Support\Str::limit($q->question, 140) }}</span>
                        <a href="{{ route('admin.quizzes.questions.edit', [$quiz, $q]) }}"
                           style="color:#8A9696; font-size:0.75rem; flex-shrink:0;">Modifica</a>
                    </div>
                </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
@endsection
