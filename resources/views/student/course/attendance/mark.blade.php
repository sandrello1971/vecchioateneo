@extends('layouts.student')
@section('title', 'Presenze — ' . $session->title)
@section('breadcrumb', 'Presenze')
@section('content')
<div style="max-width:760px; margin:0 auto;">
    <a href="{{ route('student.course.sessions.index', $course->slug) }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none;">&larr; Sessioni</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin:6px 0 2px;">{{ $session->title }}</h2>
    <p style="color:#6B7280; font-size:0.85rem; margin-bottom:18px;">
        {{ $session->scheduled_at?->format('d/m/Y H:i') }} &middot; {{ $session->duration_minutes }} min &middot;
        {{ $session->modality === 'in_person' ? 'In aula' : 'Live online' }}
        @if($session->location) &middot; {{ $session->location }} @endif
    </p>

    @if($students->isEmpty())
        <div style="padding:32px; text-align:center; background:#F7F9F9; border-radius:8px; color:#6B7280;">Nessun discente iscritto al corso.</div>
    @else
    <form action="{{ route('student.course.sessions.mark', [$course->slug, $session]) }}" method="POST">
        @csrf
        <div style="display:flex; gap:10px; margin-bottom:10px;">
            <button type="button" onclick="document.querySelectorAll('.pres-chk').forEach(c=>c.checked=true)" style="font-size:0.78rem; color:#55B1AE; background:none; border:1px solid #55B1AE; border-radius:5px; padding:5px 10px; cursor:pointer;">Tutti presenti</button>
            <button type="button" onclick="document.querySelectorAll('.pres-chk').forEach(c=>c.checked=false)" style="font-size:0.78rem; color:#6B7280; background:none; border:1px solid #D1D5DB; border-radius:5px; padding:5px 10px; cursor:pointer;">Azzera</button>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
            <thead>
                <tr style="text-align:left; color:#6B7280; border-bottom:2px solid #E5E7EB;">
                    <th style="padding:9px 8px; width:60px;">Presente</th><th style="padding:9px 8px;">Discente</th><th style="padding:9px 8px; width:150px;">Ore (opz.)</th>
                </tr>
            </thead>
            <tbody>
                @php $default = round(($session->duration_minutes ?? 0)/60, 2); @endphp
                @foreach($students as $st)
                    @php $rec = $marked->get($st->id); @endphp
                    <tr style="border-bottom:1px solid #F0F0F0;">
                        <td style="padding:9px 8px; text-align:center;">
                            <input type="checkbox" class="pres-chk" name="present[]" value="{{ $st->id }}" @checked($rec) style="width:18px; height:18px;">
                        </td>
                        <td style="padding:9px 8px; color:#1A1F1F;">{{ $st->name }} <span style="color:#9CA3AF; font-size:0.78rem;">{{ $st->email }}</span></td>
                        <td style="padding:9px 8px;">
                            <input type="number" step="0.25" min="0" name="hours[{{ $st->id }}]" value="{{ $rec?->hours_credited ?? $default }}" style="width:90px; padding:5px 8px; border:1px solid #D1D5DB; border-radius:5px; font-size:0.85rem;">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="color:#9CA3AF; font-size:0.78rem; margin:10px 0 16px;">Le ore vuote usano la durata della sessione ({{ $default }}h). Gli assenti (deselezionati) vengono rimossi dal registro di questa sessione.</p>
        <button type="submit" style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">Salva presenze</button>
    </form>
    @endif
</div>
@endsection
