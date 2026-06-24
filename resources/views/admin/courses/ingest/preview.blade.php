@extends('layouts.admin')
@section('title', 'Preview corso')
@section('content')

@php
    $course = $data['course'] ?? [];
    $modules = $data['modules'] ?? [];
    $exam = $data['exam'] ?? null;
    $questions = $exam['questions'] ?? [];
    $quizTitle = $exam['quiz_title'] ?? '';
    $passingScore = $exam['passing_score'] ?? 70;
@endphp

<div style="max-width:1000px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
        <a href="{{ route('admin.courses.ingest.form') }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Indietro</a>
        <span style="color:#C8D0D0;">|</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Preview e conferma</h2>
    </div>

    @if(session('error'))
    <div style="margin-bottom:16px; padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; color:#c97a45; font-size:0.875rem;">
        ⚠ {{ session('error') }}
    </div>
    @endif

    @if($errors->any())
    <div style="margin-bottom:16px; padding:12px 16px; background:#FDECE2; border:1px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.875rem;">
        <strong>Impossibile creare il corso. Correggi questi errori:</strong>
        <ul style="margin:8px 0 0 18px; padding:0;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.courses.ingest.confirm') }}">
        @csrf
        <input type="hidden" name="job_id" value="{{ $jobId }}">

        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:16px;">
            <div style="font-weight:700; color:#1A1F1F; margin-bottom:12px; font-size:0.95rem;">📘 Corso</div>
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:10px;">
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Nome *</label>
                    <input type="text" name="course_name" value="{{ old('course_name', $course['name'] ?? '') }}" required
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Slug (auto se vuoto)</label>
                    <input type="text" name="course_slug" value="{{ old('course_slug') }}" placeholder="auto da nome"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem;">
                </div>
            </div>
            <div style="margin-bottom:10px;">
                <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Descrizione breve</label>
                <input type="text" name="course_short_description" value="{{ old('course_short_description', $course['short_description'] ?? '') }}"
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem;">
            </div>
            <div>
                <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Descrizione estesa</label>
                <textarea name="course_description" rows="3"
                          style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; resize:vertical;">{{ old('course_description', $course['description'] ?? '') }}</textarea>
            </div>
        </div>

        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">📚 Moduli rilevati ({{ count($modules) }})</div>
                <div style="font-size:0.8rem; color:#8A9696;">Deseleziona quelli da escludere</div>
            </div>

            @foreach($modules as $i => $mod)
            <details style="border:1px solid #E8F5F5; border-radius:8px; margin-bottom:8px; overflow:hidden;">
                <summary style="padding:12px 14px; cursor:pointer; display:flex; align-items:center; gap:10px; background:#F5F7F7;">
                    <input type="checkbox" name="modules[{{ $i }}][include]" value="1" checked
                           onclick="event.stopPropagation()"
                           style="cursor:pointer;">
                    <div style="flex:1;">
                        <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $mod['title'] ?? 'Modulo ' . ($i + 1) }}</div>
                        <div style="color:#8A9696; font-size:0.75rem;">{{ \Illuminate\Support\Str::limit($mod['description'] ?? '', 90) }}</div>
                    </div>
                    <span style="font-size:0.7rem; color:#8A9696; font-family:monospace;">{{ strlen($mod['content_html'] ?? '') }} ch</span>
                </summary>
                <div style="padding:14px;">
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Titolo</label>
                    <input type="text" name="modules[{{ $i }}][title]" value="{{ $mod['title'] ?? '' }}" required
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; margin-bottom:10px;">
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Descrizione</label>
                    <input type="text" name="modules[{{ $i }}][description]" value="{{ $mod['description'] ?? '' }}"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; margin-bottom:10px;">
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Contenuto HTML</label>
                    <textarea name="modules[{{ $i }}][content_html]" rows="8"
                              style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.75rem; font-family:monospace; resize:vertical;">{{ $mod['content_html'] ?? '' }}</textarea>
                </div>
            </details>
            @endforeach
        </div>

        @if(!empty($questions))
        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">📝 Quiz — {{ count($questions) }} domande rilevate</div>
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Titolo quiz</label>
                    <input type="text" name="quiz_title" value="{{ $quizTitle }}"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Soglia % (passing_score)</label>
                    <input type="number" name="quiz_passing_score" value="{{ $passingScore }}" min="0" max="100"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem;">
                </div>
            </div>

            @foreach($questions as $i => $q)
            @php $opts = $q['options'] ?? []; @endphp
            <details style="border:1px solid #E8F5F5; border-radius:8px; margin-bottom:8px;">
                <summary style="padding:10px 14px; cursor:pointer; display:flex; align-items:center; gap:10px; background:#F5F7F7;">
                    <input type="checkbox" name="questions[{{ $i }}][include]" value="1"
                           {{ count($opts) === 4 ? 'checked' : '' }}
                           onclick="event.stopPropagation()" style="cursor:pointer;">
                    <div style="flex:1; font-size:0.85rem; color:#1A1F1F;">{{ \Illuminate\Support\Str::limit($q['question'] ?? '', 90) }}</div>
                    @if(count($opts) !== 4)
                    <span style="font-size:0.7rem; padding:2px 6px; background:#fff3ec; color:#c97a45; border-radius:4px;">{{ count($opts) }} opz</span>
                    @endif
                </summary>
                <div style="padding:12px 14px;">
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Domanda</label>
                    <textarea name="questions[{{ $i }}][question]" rows="2"
                              style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">{{ $q['question'] ?? '' }}</textarea>
                    <div style="margin-top:8px;">
                        <label style="font-size:0.75rem; color:#4A5252; display:block; margin-bottom:4px;">Opzioni (esattamente 4)</label>
                        @foreach([0,1,2,3] as $oi)
                        <input type="text" name="questions[{{ $i }}][options][]" value="{{ $opts[$oi] ?? '' }}"
                               style="width:100%; padding:6px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; margin-bottom:4px;"
                               placeholder="Opzione {{ chr(65 + $oi) }}">
                        @endforeach
                    </div>
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-top:8px; margin-bottom:4px;">Risposta corretta (testo esatto)</label>
                    <input type="text" name="questions[{{ $i }}][correct_answer]" value="{{ $q['correct_answer'] ?? '' }}"
                           style="width:100%; padding:6px 10px; border:1px solid #55B1AE; border-radius:6px; font-size:0.8rem; background:#E8F5F5;">
                    <label style="font-size:0.75rem; color:#4A5252; display:block; margin-top:8px; margin-bottom:4px;">Spiegazione</label>
                    <textarea name="questions[{{ $i }}][explanation]" rows="2"
                              style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">{{ $q['explanation'] ?? '' }}</textarea>
                </div>
            </details>
            @endforeach
        </div>
        @else
        <div style="background:white; border-radius:12px; padding:16px; margin-bottom:16px; text-align:center; color:#8A9696; font-size:0.85rem;">
            Nessun documento esame caricato — il corso verrà creato senza quiz (potrai aggiungerlo dopo).
        </div>
        @endif

        <div style="display:flex; gap:12px; justify-content:space-between; align-items:center;">
            <button type="submit" formaction="{{ route('admin.courses.ingest.cancel') }}" formnovalidate
                    style="padding:10px 20px; border:1px solid #E28A53; color:#E28A53; background:white; border-radius:8px; font-size:0.875rem; cursor:pointer;">
                Annulla e ricomincia
            </button>
            <button type="submit" style="padding:10px 28px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:700; cursor:pointer;">
                ✓ Conferma e crea corso
            </button>
        </div>
    </form>
</div>

@endsection
