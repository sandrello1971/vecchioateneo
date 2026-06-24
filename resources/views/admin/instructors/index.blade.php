@extends('layouts.admin')
@section('title', 'Formatori')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Gestione Formatori</h2>
    <div style="display:flex; align-items:center; gap:16px;">
        <a href="{{ route('admin.students.index') }}"
           style="font-size:0.8rem; color:#8A9696; text-decoration:none;">→ Vai a Discenti</a>
        <a href="{{ route('admin.instructors.create') }}"
           style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">+ Aggiungi formatore</a>
    </div>
</div>

<form method="GET" style="background:white; border-radius:10px; padding:14px; margin-bottom:16px;
       display:grid; grid-template-columns:1fr auto auto; gap:10px; align-items:end;">
    <div>
        <label style="font-size:0.7rem; color:#8A9696;">Corso insegnato</label>
        <select name="course_id" style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
            <option value="">Tutti</option>
            @foreach($courses as $c)
            <option value="{{ $c->id }}" {{ request('course_id') === $c->id ? 'selected' : '' }}>
                {{ $c->icon }} {{ $c->name }}
            </option>
            @endforeach
        </select>
    </div>
    <button type="submit" style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Filtra</button>
    <a href="{{ route('admin.instructors.index') }}" style="padding:8px 14px; color:#8A9696; text-decoration:none; font-size:0.8rem;">Reset</a>
</form>

<div style="background:white; border-radius:10px; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#F5F7F7;">
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Formatore</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azienda</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Corsi insegnati</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Discenti seguiti</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            @forelse($instructors as $instructor)
            <tr style="border-bottom:1px solid #F5F7F7;">
                <td style="padding:12px 16px;">
                    <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $instructor->name }}</div>
                    <div style="color:#8A9696; font-size:0.75rem;">{{ $instructor->email }}</div>
                </td>
                <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">{{ $instructor->company ?? '—' }}</td>
                <td style="padding:12px 16px;">
                    @if($instructor->taught_courses_count === 0)
                        <span style="color:#8A9696; font-size:0.75rem; font-style:italic;">Nessun corso assegnato</span>
                    @else
                        <div style="display:flex; flex-wrap:wrap; gap:4px;">
                            @foreach($instructor->taughtCourses as $c)
                            <span style="padding:2px 8px; background:rgba(226,138,83,0.15); color:#D87840; border-radius:4px; font-size:0.7rem; font-weight:600;">
                                {{ $c->icon }} {{ $c->name }}
                            </span>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">
                    {{ $mentoredCounts[$instructor->id] ?? 0 }}
                </td>
                <td style="padding:12px 16px;">
                    <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                        background:{{ $instructor->is_active ? '#E8F5F5' : '#F5F7F7' }};
                        color:{{ $instructor->is_active ? '#3A8C89' : '#8A9696' }};">
                        {{ $instructor->is_active ? 'Attivo' : 'Inattivo' }}
                    </span>
                </td>
                <td style="padding:12px 16px;">
                    <div style="display:flex; gap:8px;">
                        <a href="{{ route('admin.instructors.show', $instructor) }}" style="font-size:0.8rem; color:#55B1AE;">Dettaglio</a>
                        <a href="{{ route('admin.instructors.edit', $instructor) }}" style="font-size:0.8rem; color:#8A9696;">Modifica</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="padding:40px; text-align:center; color:#8A9696; font-size:0.875rem;">
                    Nessun formatore.
                    Usa <a href="{{ route('admin.instructors.create') }}" style="color:#55B1AE;">+ Aggiungi formatore</a> (crea un nuovo account o promuove un'email esistente), oppure dai la capacità da <a href="{{ route('admin.students.index') }}" style="color:#55B1AE;">Discenti → Modifica → Permessi sistema</a>.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $instructors->links() }}</div>

@endsection
