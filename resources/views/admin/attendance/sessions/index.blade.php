@extends('layouts.admin')
@section('title', 'Sessioni — ' . $course->name)
@section('content')
<div style="max-width:1000px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
        <div>
            <a href="{{ route('admin.courses.show', $course) }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none;">&larr; {{ $course->name }}</a>
            <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">Sessioni sincrone</h2>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('admin.courses.register', $course) }}" style="padding:8px 14px; border:1px solid #55B1AE; color:#55B1AE; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">Registro completo</a>
            <a href="{{ route('admin.courses.sessions.create', $course) }}" style="padding:8px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">+ Nuova sessione</a>
        </div>
    </div>
    <p style="color:#6B7280; font-size:0.85rem; margin-bottom:18px;">Lezioni in aula o live online: la presenza dei discenti è segnata qui dal docente. La FAD asincrona è tracciata automaticamente.</p>

    @if($sessions->isEmpty())
        <div style="padding:32px; text-align:center; background:#F7F9F9; border-radius:8px; color:#6B7280;">
            Nessuna sessione. Crea la prima per registrare le presenze in aula/online.
        </div>
    @else
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="text-align:left; color:#6B7280; border-bottom:2px solid #E5E7EB;">
                    <th style="padding:10px 8px;">Sessione</th>
                    <th style="padding:10px 8px;">Data</th>
                    <th style="padding:10px 8px;">Durata</th>
                    <th style="padding:10px 8px;">Modalità</th>
                    <th style="padding:10px 8px;">Presenti</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($sessions as $s)
                <tr style="border-bottom:1px solid #F0F0F0;">
                    <td style="padding:10px 8px; font-weight:600; color:#1A1F1F;">{{ $s->title }}</td>
                    <td style="padding:10px 8px;">{{ $s->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td style="padding:10px 8px;">{{ $s->duration_minutes }} min</td>
                    <td style="padding:10px 8px;">{{ $s->modality === 'in_person' ? 'In aula' : 'Live online' }}</td>
                    <td style="padding:10px 8px;">{{ $s->present_count }}</td>
                    <td style="padding:10px 8px; text-align:right;">
                        <a href="{{ route('admin.courses.sessions.show', [$course, $s]) }}" style="color:#55B1AE; font-weight:600; text-decoration:none;">Presenze</a>
                        <form action="{{ route('admin.courses.sessions.destroy', [$course, $s]) }}" method="POST" style="display:inline; margin-left:12px;" onsubmit="return confirm('Eliminare la sessione e le sue presenze?');">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#C0392B; cursor:pointer; font-size:0.85rem;">Elimina</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
