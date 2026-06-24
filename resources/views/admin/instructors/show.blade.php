@extends('layouts.admin')
@section('title', $instructor->name . ' — Formatore')
@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('admin.instructors.index') }}" style="color:#8A9696; font-size:0.8rem; text-decoration:none;">&larr; Formatori</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">{{ $instructor->name }}</h2>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Anagrafica --}}
    <div class="p-5 rounded-xl" style="background:white;border:1px solid #C8D0D0">
        <h3 class="font-bold mb-4">Informazioni</h3>

        <div style="margin-bottom:14px;">
            <span style="display:inline-block; padding:4px 12px; border-radius:14px;
                         font-size:0.7rem; font-weight:700;
                         background:rgba(226,138,83,0.15); color:#D87840;">
                FORMATORE
            </span>
        </div>

        <dl class="text-sm space-y-2">
            <div><dt class="text-xs font-bold" style="color:#8A9696">EMAIL</dt><dd>{{ $instructor->email }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">TELEFONO</dt><dd>{{ $instructor->phone ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">AZIENDA</dt><dd>{{ $instructor->company ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">RUOLO</dt><dd>{{ $instructor->job_title ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">ULTIMO ACCESSO</dt><dd>{{ $instructor->last_login_at?->format('d/m/Y H:i') ?? 'Mai' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">STATO</dt><dd>{{ $instructor->is_active ? 'Attivo' : 'Inattivo' }}</dd></div>
        </dl>
        <div class="mt-4 flex gap-2">
            <a href="{{ route('admin.instructors.edit', $instructor) }}"
               class="text-xs px-3 py-1.5 rounded border" style="border-color:#C8D0D0;color:#4A5252">Modifica</a>
            <a href="{{ route('admin.students.edit', $instructor) }}"
               class="text-xs px-3 py-1.5 rounded border" style="border-color:#E28A53;color:#E28A53">Permessi sistema</a>
        </div>
    </div>

    {{-- Corsi insegnati --}}
    <div class="lg:col-span-2 p-5 rounded-xl" style="background:white;border:1px solid #C8D0D0">
        <h3 class="font-bold mb-4">Corsi insegnati</h3>

        @if($instructor->taughtCourses->isEmpty())
        <p class="text-sm" style="color:#8A9696">Nessun corso associato.</p>
        @else
        <div class="space-y-2" style="margin-bottom:18px;">
            @foreach($instructor->taughtCourses as $c)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#F5F7F7; border-radius:8px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span>{{ $c->icon }}</span>
                    <div>
                        <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $c->name }}</div>
                        <div style="font-size:0.7rem; color:#8A9696;">{{ $c->short_description }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.instructors.detach-course', [$instructor, $c]) }}"
                      onsubmit="return confirm('Rimuovere {{ $c->name }} dal formatore? Le iscrizioni dei discenti che lo puntavano per questo corso torneranno senza formatore assegnato.')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            style="padding:6px 12px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                        Rimuovi
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        @endif

        @if($availableCourses->isNotEmpty())
        <form method="POST" action="{{ route('admin.instructors.attach-course', $instructor) }}">
            @csrf
            <div style="display:grid; grid-template-columns:1fr auto; gap:10px;">
                <select name="course_id" required
                        style="padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    <option value="">— Aggiungi un corso —</option>
                    @foreach($availableCourses as $course)
                    <option value="{{ $course->id }}">{{ $course->icon }} {{ $course->name }}</option>
                    @endforeach
                </select>
                <button type="submit"
                        style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Associa
                </button>
            </div>
        </form>
        @endif
    </div>
</div>

{{-- Discenti seguiti --}}
<div class="mt-6 p-5 rounded-xl" style="background:white;border:1px solid #C8D0D0">
    <h3 class="font-bold mb-4">Discenti seguiti</h3>

    @if($mentoredByCourse->isEmpty())
    <p class="text-sm" style="color:#8A9696">Nessun discente assegnato a questo formatore.</p>
    @else
    @foreach($mentoredByCourse as $courseId => $rows)
    <div style="margin-bottom:18px;">
        <div style="font-weight:700; color:#3A8C89; font-size:0.85rem; margin-bottom:8px;">
            {{ $rows->first()->course_name }} <span style="color:#8A9696; font-weight:400;">— {{ $rows->count() }} {{ $rows->count() === 1 ? 'discente' : 'discenti' }}</span>
        </div>
        <div style="display:flex; flex-direction:column; gap:4px;">
            @foreach($rows as $row)
            <a href="{{ route('admin.students.show', $row->student_id) }}"
               style="display:flex; gap:10px; align-items:center; padding:8px 12px; background:#F5F7F7; border-radius:6px; text-decoration:none;">
                <span style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">{{ $row->student_name }}</span>
                <span style="font-size:0.75rem; color:#8A9696;">{{ $row->student_email }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif
</div>

@endsection
