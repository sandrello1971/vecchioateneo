@extends('layouts.student')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
<div style="max-width:900px;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:1.5rem; font-weight:700; color:#1A1F1F;">
            Benvenuto, {{ $student->name }} &#128075;
        </h1>
        <p style="color:#8A9696; font-size:0.875rem;">
            {{ now()->locale('it')->isoFormat('dddd D MMMM YYYY') }}
        </p>
    </div>

    {{-- Schola: Le mie classi (contenuti completi nel pacchetto 7) --}}
    @if(!empty($myClasses) && $myClasses->isNotEmpty())
    <div style="margin-bottom:24px;">
        <h2 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:10px;">Le mie classi</h2>
        @foreach($myClasses as $myClass)
        <div style="background:white; border-radius:10px; padding:12px 16px; margin-bottom:8px; border:1px solid #C8D0D0; display:flex; align-items:center; gap:10px;">
            <div style="flex:1;">
                <span style="font-weight:600; color:#1A1F1F;">{{ $myClass->name }}</span>
                <span style="color:#8A9696; font-size:0.8rem;">· {{ $myClass->subject->name ?? '—' }} · {{ $myClass->school_year }}</span>
            </div>
            @if($myClass->pivot->status === 'pending')
                <span style="font-size:0.7rem; font-weight:700; color:#E28A53; background:#FDECE2; border:1px solid #E28A53; border-radius:4px; padding:2px 8px;">In attesa</span>
            @else
                <span style="font-size:0.7rem; font-weight:700; color:#3A8C89; background:#E8F5F5; border:1px solid #55B1AE; border-radius:4px; padding:2px 8px;">Attiva</span>
            @endif
        </div>
        @endforeach
        <a href="{{ route('student.classes.index') }}" style="font-size:0.82rem; color:#55B1AE; text-decoration:none;">Vedi tutte →</a>
    </div>
    @endif

    {{-- Box "nessun corso" SOLO per chi non ha né corsi né classi: gli studenti
         di scuola (con classi, senza corsi) non devono vedere questo rumore
         dual-identity (P23). --}}
    @if($courses->isEmpty() && (empty($myClasses) || $myClasses->isEmpty()))
    <div style="background:white; border-radius:12px; padding:40px; text-align:center; border:2px dashed #C8D0D0;">
        <div style="font-size:3rem; margin-bottom:12px;">&#128218;</div>
        <h2 style="color:#1A1F1F; margin-bottom:8px;">Nessun corso attivo</h2>
        <p style="color:#8A9696; font-size:0.875rem;">
            Non hai ancora corsi assegnati.<br>
            Scrivi a <a href="mailto:{{ atheneum_setting('contact_email', 'info@noscite.it') }}" style="color:#55B1AE;">{{ atheneum_setting('contact_email', 'info@noscite.it') }}</a> per attivare il tuo percorso.
        </p>
    </div>

    @elseif($courses->isNotEmpty())
    <div style="display:grid; gap:16px;">
        @foreach($courses as $course)
        <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="background:{{ $course->color }}; padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size:1.5rem;">{{ $course->icon }}</span>
                    <div>
                        <div style="color:white; font-weight:700;">{{ $course->name }}</div>
                        @unless($student->is_demo)
                        <div style="color:rgba(255,255,255,0.8); font-size:0.75rem;">{{ $course->short_description }}</div>
                        @endunless
                    </div>
                </div>
                @if(!$student->is_demo && empty($course->is_teaching))
                <div style="color:white; font-size:1.25rem; font-weight:700;">{{ $course->progress_pct }}%</div>
                @elseif(!empty($course->is_teaching))
                <span style="padding:4px 10px; background:rgba(226,138,83,0.95); color:white; border-radius:12px; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">
                    &#127979; Insegni questo corso
                </span>
                @endif
            </div>

            <div style="padding:16px 20px;">
                @if($student->is_demo)
                <div style="display:flex; align-items:center; justify-content:flex-end;">
                    <a href="/learn/course/{{ $course->slug }}"
                       style="padding:6px 16px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                        Entra nel corso &rarr;
                    </a>
                </div>
                @elseif(!empty($course->is_teaching))
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="color:#8A9696; font-size:0.8rem;">
                        {{ $course->modules_total }} {{ $course->modules_total === 1 ? 'modulo' : 'moduli' }} &middot; modalit&agrave; docenza
                    </div>
                    <a href="/learn/course/{{ $course->slug }}"
                       style="padding:6px 16px; background:#E28A53; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                        Apri in docenza &rarr;
                    </a>
                </div>
                @else
                <div class="progress-bar" style="margin-bottom:12px;">
                    <div class="progress-fill" style="width:{{ $course->progress_pct }}%"></div>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="color:#8A9696; font-size:0.8rem;">
                        {{ $course->modules_done }} di {{ $course->modules_total }} moduli completati
                    </div>
                    <div style="display:flex; gap:8px;">
                        @if($course->progress_pct >= 100)
                        <span style="padding:6px 12px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.8rem; font-weight:600;">
                            &#10003; Completato
                        </span>
                        @endif
                        <a href="/learn/course/{{ $course->slug }}"
                           style="padding:6px 16px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                            {{ $course->progress_pct == 0 ? 'Inizia' : ($course->progress_pct >= 100 ? 'Rivedi' : 'Continua') }} &rarr;
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
