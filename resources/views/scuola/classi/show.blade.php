@extends('layouts.scuola')
@section('title', $class->name)
@section('breadcrumb', 'Classi / ' . $class->name)
@section('content')
<div style="max-width:920px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.classi.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Classi</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:2px;">{{ $class->name }}</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">{{ $class->school_year }} · Coordinatore: {{ $class->coordinator?->name ?? '—' }}</p>

    @if(session('warning'))<div style="margin-bottom:14px; padding:10px 14px; background:#FBF3E2; border-left:4px solid #E2A653; border-radius:6px; color:#9A7B2E; font-size:0.85rem;">{{ session('warning') }}</div>@endif

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        {{-- Roster --}}
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Roster ({{ $class->classStudents->count() }})</div>
            @forelse($class->classStudents as $cs)
                <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F0F2F2; font-size:0.84rem;">
                    <span>{{ $cs->student?->name }} <span style="color:#8A9696;">· {{ $cs->student?->email ?? $cs->student?->username }}</span></span>
                    <form method="POST" action="{{ route('scuola.classi.students', $class) }}">@csrf
                        <input type="hidden" name="action" value="remove"><input type="hidden" name="student_id" value="{{ $cs->student_id }}">
                        <button title="Rimuovi" style="padding:2px 8px; background:white; color:#A8521F; border:1px solid #E2A653; border-radius:6px; font-size:0.72rem; cursor:pointer;">&times;</button>
                    </form>
                </div>
            @empty
                <div style="color:#8A9696; font-size:0.83rem; margin-bottom:10px;">Nessuno studente.</div>
            @endforelse

            <form method="POST" action="{{ route('scuola.classi.students', $class) }}" style="display:flex; gap:8px; margin-top:12px;">@csrf
                <input type="hidden" name="action" value="add">
                <select name="student_id" required style="flex:1; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
                    <option value="">— aggiungi studente —</option>
                    @foreach($availableStudents as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
                <button style="padding:7px 12px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Aggiungi</button>
            </form>
        </div>

        {{-- Cattedre --}}
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Cattedre ({{ $class->teachingAssignments->count() }})</div>
            @forelse($class->teachingAssignments as $a)
                <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F0F2F2; font-size:0.84rem;">
                    <span><strong>{{ $a->subject?->name }}</strong> <span style="color:#8A9696;">· {{ $a->teacher?->name }}</span></span>
                    <form method="POST" action="{{ route('scuola.cattedre.destroy', $a) }}">@csrf @method('DELETE')
                        <button title="Rimuovi cattedra" style="padding:2px 8px; background:white; color:#A8521F; border:1px solid #E2A653; border-radius:6px; font-size:0.72rem; cursor:pointer;">&times;</button>
                    </form>
                </div>
            @empty
                <div style="color:#8A9696; font-size:0.83rem; margin-bottom:10px;">Nessuna cattedra assegnata.</div>
            @endforelse

            <form method="POST" action="{{ route('scuola.classi.cattedre.store', $class) }}" style="margin-top:12px;">@csrf
                <select name="teacher_id" required style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem; margin-bottom:6px;">
                    <option value="">— docente —</option>
                    @foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                </select>
                <select name="subject_id" required style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem; margin-bottom:8px;">
                    <option value="">— materia —</option>
                    @foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
                <button style="padding:7px 12px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Assegna cattedra</button>
            </form>
        </div>
    </div>

    {{-- Modifica classe --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-top:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Impostazioni classe</div>
        <form method="POST" action="{{ route('scuola.classi.update', $class) }}" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; align-items:end;">@csrf @method('PATCH')
            <div><label style="display:block; font-size:0.78rem; color:#4A5252; margin-bottom:4px;">Nome</label><input type="text" name="name" value="{{ $class->name }}" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;"></div>
            <div><label style="display:block; font-size:0.78rem; color:#4A5252; margin-bottom:4px;">Anno</label><input type="text" name="school_year" value="{{ $class->school_year }}" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;"></div>
            <div><label style="display:block; font-size:0.78rem; color:#4A5252; margin-bottom:4px;">Coordinatore</label>
                <select name="coordinator_id" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
                    <option value="">— nessuno —</option>
                    @foreach($teachers as $t)<option value="{{ $t->id }}" @selected($class->teacher_id===$t->id)>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:14px;">
                <label style="font-size:0.82rem; color:#4A5252;"><input type="checkbox" name="is_archived" value="1" @checked($class->is_archived)> Archiviata</label>
                <button style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Salva</button>
            </div>
        </form>
    </div>
</div>
@endsection
