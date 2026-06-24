@extends('layouts.student')
@section('title', $course->name . ' — Mappe concettuali')
@section('breadcrumb', $course->name . ' / Mappe concettuali')

@section('content')
<div style="max-width:900px;">
    <a href="{{ route('student.course.show', $course->slug) }}" style="color:#8A9696; font-size:0.85rem;">&larr; {{ $course->name }}</a>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
        🧭 Mappe concettuali del corso
    </h1>
    <p style="font-size:0.85rem; color:#8A9696; margin-top:6px; max-width:680px;">
        Le mappe concettuali rappresentano il sapere del corso come grafo di concetti collegati da relazioni esplicite.
        Puoi consultare la mappa originale del corso oppure crearne una versione personale modificabile.
    </p>

    <div style="display:flex; flex-direction:column; gap:10px; margin-top:18px;">
        @forelse($maps as $cm)
        <div style="background:white; border-radius:10px; padding:14px 18px;
                    display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <div style="flex:1;">
                <div style="font-weight:600; color:#1A1F1F;">
                    {{ $cm->title }}
                    @if(in_array($cm->id, $forkedMapIds))
                        <span style="margin-left:6px; padding:2px 8px; background:#FEF3C7; color:#92400E; border-radius:4px; font-size:0.65rem; font-weight:700;">PERSONALIZZATA</span>
                    @endif
                </div>
                @if($cm->description)
                <div style="color:#8A9696; font-size:0.78rem; margin-top:3px;">{{ $cm->description }}</div>
                @endif
                <div style="color:#8A9696; font-size:0.7rem; margin-top:3px;">
                    {{ count($cm->data['nodes'] ?? []) }} concetti &middot; {{ count($cm->data['edges'] ?? []) }} relazioni
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('student.course.concept-map.show', [$course->slug, $cm->id]) }}"
                   style="padding:6px 14px; background:#55B1AE; color:white;
                          border-radius:6px; text-decoration:none; font-size:0.8rem; font-weight:600;">
                    Apri
                </a>
                @if(in_array($cm->id, $forkedMapIds))
                <a href="{{ route('student.course.concept-map.my', [$course->slug, $cm->id]) }}"
                   style="padding:6px 14px; background:white; color:#55B1AE;
                          border:1px solid #55B1AE; border-radius:6px;
                          text-decoration:none; font-size:0.8rem; font-weight:600;">
                    Mia versione
                </a>
                @endif
            </div>
        </div>
        @empty
        <div style="background:white; border-radius:10px; padding:32px; text-align:center; color:#8A9696;">
            Per questo corso non ci sono ancora mappe concettuali pubblicate.
        </div>
        @endforelse
    </div>
</div>
@endsection
