@extends('layouts.admin')
@section('title', $course->name)
@section('content')

<div style="max-width:900px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="/admin/courses" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Corsi</a>
        <span style="color:#C8D0D0;">|</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">{{ $course->name }}</h2>
    </div>

    {{-- Header info --}}
    <div style="background:{{ $course->color }}; border-radius:10px; padding:24px; margin-bottom:20px; color:white;">
        <div style="display:flex; align-items:center; gap:16px;">
            <span style="font-size:2.5rem;">{{ $course->icon }}</span>
            <div style="flex:1;">
                <div style="font-size:1.5rem; font-weight:700;">{{ $course->name }}</div>
                <div style="opacity:0.9; font-size:0.9rem;">{{ $course->short_description }}</div>
                <div style="opacity:0.75; font-size:0.8rem; margin-top:4px;">
                    {{ $course->duration_hours }}h &middot; {{ $course->modules->count() }} moduli &middot; {{ $course->certification_name ?? '—' }}
                </div>
            </div>
            <a href="/admin/courses/{{ $course->id }}/edit" style="padding:8px 16px; background:rgba(255,255,255,0.2); color:white; border-radius:8px; font-size:0.85rem; text-decoration:none; font-weight:600;">Modifica</a>
        </div>
    </div>

    {{-- Descrizione --}}
    @if($course->description)
    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:20px;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:#8A9696; margin-bottom:6px;">Descrizione</div>
        <p style="color:#4A5252; font-size:0.9rem; line-height:1.6;">{{ $course->description }}</p>
    </div>
    @endif

    {{-- Mappe concettuali (quick access) --}}
    <div style="background:white; border-radius:10px; padding:16px 20px; margin-bottom:20px;
                display:flex; align-items:center; justify-content:space-between; gap:14px;
                border-left:4px solid #55B1AE;">
        <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="font-size:1.2rem;">🧭</div>
                <h3 style="font-weight:700; color:#1A1F1F;">Mappe concettuali del corso</h3>
                <span style="padding:2px 8px; background:#E8F5F5; color:#3D8B88; border-radius:4px; font-size:0.7rem; font-weight:700;">
                    {{ $course->conceptMaps()->count() }} {{ $course->conceptMaps()->count() === 1 ? 'mappa' : 'mappe' }}
                </span>
            </div>
            <div style="font-size:0.78rem; color:#8A9696; margin-top:4px;">
                Grafi di concetti con relazioni esplicite (à la Novak/Cmap). Generabili con AI o create manualmente.
                Gli studenti possono forkare la versione personale.
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="/admin/courses/{{ $course->id }}/concept-maps"
               style="padding:8px 16px; background:#55B1AE; color:white;
                      border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                Gestisci mappe
            </a>
            <a href="/admin/courses/{{ $course->id }}/concept-maps/create"
               style="padding:8px 16px; background:white; color:#55B1AE;
                      border:1px solid #55B1AE; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                + Nuova
            </a>
        </div>
    </div>

    {{-- Moduli --}}
    <div style="background:white; border-radius:10px; overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #F5F7F7; display:flex; align-items:center; justify-content:space-between;">
            <h3 style="font-weight:700; color:#1A1F1F;">Moduli ({{ $course->modules->count() }})</h3>
            <a href="/admin/courses/{{ $course->id }}/modules/create" style="padding:6px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">+ Nuovo modulo</a>
        </div>

        @if($course->modules->isEmpty())
        <div style="padding:24px; text-align:center; color:#8A9696; font-size:0.875rem;">Nessun modulo ancora.</div>
        @else
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#F5F7F7;">
                    <th style="padding:10px 16px; text-align:left; font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">#</th>
                    <th style="padding:10px 16px; text-align:left; font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Titolo</th>
                    <th style="padding:10px 16px; text-align:left; font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Durata</th>
                    <th style="padding:10px 16px; text-align:left; font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</th>
                    <th style="padding:10px 16px; text-align:right; font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($course->modules as $module)
                <tr style="border-bottom:1px solid #F5F7F7;">
                    <td style="padding:10px 16px; color:#8A9696; font-size:0.8rem;">{{ $module->sort_order }}</td>
                    <td style="padding:10px 16px;">
                        <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $module->title }}</div>
                        @if($module->description)
                        <div style="font-size:0.75rem; color:#8A9696;">{{ \Illuminate\Support\Str::limit($module->description, 80) }}</div>
                        @endif
                    </td>
                    <td style="padding:10px 16px; font-size:0.8rem; color:#4A5252;">
                        @if($module->duration_minutes)
                            {{ $module->duration_minutes }} min
                        @else — @endif
                    </td>
                    <td style="padding:10px 16px;">
                        <span style="padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:600;
                            background:{{ $module->is_active ? '#E8F5F5' : '#F5F7F7' }};
                            color:{{ $module->is_active ? '#3A8C89' : '#8A9696' }};">
                            {{ $module->is_active ? 'Attivo' : 'Inattivo' }}
                        </span>
                    </td>
                    <td style="padding:10px 16px; text-align:right;">
                        <a href="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}/edit" style="font-size:0.8rem; color:#55B1AE;">Modifica</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@endsection
