@extends('layouts.admin')
@section('title', $student->name)
@section('page-title', $student->name)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Info studente --}}
    <div class="p-5 rounded-xl" style="background:white;border:1px solid #C8D0D0">
        <h3 class="font-bold mb-4">Informazioni</h3>

        @if($student->role || $student->auto_enroll_all_courses)
        <div style="margin-bottom:14px;">
            @if($student->role)
            <span style="display:inline-block; padding:4px 12px; border-radius:14px;
                         font-size:0.7rem; font-weight:700;
                         background:{{ $student->role === 'admin' ? 'rgba(197,42,42,0.15)' : 'rgba(226,138,83,0.15)' }};
                         color:{{ $student->role === 'admin' ? '#C52A2A' : '#D87840' }};">
                {{ \App\Models\Student::SYSTEM_ROLES[$student->role] ?? $student->role }}
            </span>
            @endif

            @if($student->auto_enroll_all_courses)
            <span style="display:inline-block; padding:4px 10px; border-radius:14px;
                         font-size:0.7rem; font-weight:600; margin-left:6px;
                         background:rgba(85,177,174,0.15); color:#3A8C89;">
                🔓 Auto-iscritto a tutti i corsi
            </span>
            @endif
        </div>
        @endif

        <dl class="text-sm space-y-2">
            <div><dt class="text-xs font-bold" style="color:#8A9696">EMAIL</dt><dd>{{ $student->email }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">TELEFONO</dt><dd>{{ $student->phone ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">AZIENDA</dt><dd>{{ $student->company ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">RUOLO AZIENDALE</dt><dd>{{ $student->job_title ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">ULTIMO ACCESSO</dt><dd>{{ $student->last_login_at?->format('d/m/Y H:i') ?? 'Mai' }}</dd></div>
            <div><dt class="text-xs font-bold" style="color:#8A9696">STATO</dt><dd>{{ $student->is_active ? 'Attivo' : 'Inattivo' }}</dd></div>
        </dl>
        <div class="mt-4 flex gap-2">
            <a href="{{ route('admin.students.edit', $student) }}" class="text-xs px-3 py-1.5 rounded border" style="border-color:#C8D0D0;color:#4A5252">Modifica</a>
            <form method="POST" action="{{ route('admin.students.send-credentials', $student) }}" onsubmit="return confirm('Inviare nuove credenziali?')">
                @csrf
                <button type="submit" class="text-xs px-3 py-1.5 rounded border" style="border-color:#E28A53;color:#E28A53">Reinvia credenziali</button>
            </form>
        </div>
    </div>

    {{-- Corsi --}}
    <div class="lg:col-span-2 p-5 rounded-xl" style="background:white;border:1px solid #C8D0D0">
        <h3 class="font-bold mb-4">Corsi assegnati</h3>
        @if($student->courses->isEmpty())
        <p class="text-sm" style="color:#8A9696">Nessun corso assegnato.</p>
        @else
        <div class="space-y-3">
        @foreach($student->courses as $c)
        @php
            $totalModules = $c->modules()->where('is_active', true)->count();
            $completed = $student->moduleProgress()->whereHas('module', fn($q)=>$q->where('course_id',$c->id))->where('status','completed')->count();
            $pct = $totalModules > 0 ? round(($completed / $totalModules) * 100) : 0;
            $assignedInstructorId = $c->pivot->instructor_id;
            $assignedInstructor = $assignedInstructorId
                ? ($assignedInstructors[$assignedInstructorId] ?? null)
                : null;
        @endphp
        <div class="p-3 rounded-lg" style="background:#F5F7F7">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2"><span>{{ $c->icon }}</span><strong>{{ $c->name }}</strong></div>
                <span class="text-xs" style="color:#8A9696">{{ $completed }}/{{ $totalModules }} moduli</span>
            </div>
            <div class="h-2 rounded-full overflow-hidden" style="background:#C8D0D0">
                <div class="h-full rounded-full" style="width:{{ $pct }}%;background:{{ $c->color }}"></div>
            </div>
            <div class="flex items-center justify-between text-xs mt-2" style="color:#5A6464;">
                <span>
                    @if($assignedInstructor)
                        Formatore: <strong style="color:#3A8C89;">{{ $assignedInstructor->name }}</strong>
                    @else
                        <span style="color:#8A9696;">Formatore: —</span>
                    @endif
                    @if($c->instructors->count() >= 1)
                        <a href="{{ route('admin.students.edit', $student) }}" style="color:#55B1AE; margin-left:6px;">{{ $assignedInstructor ? 'Cambia' : 'Assegna' }}</a>
                    @endif
                </span>
                <span style="color:#8A9696;">{{ $pct }}%</span>
            </div>
            <div class="flex justify-end text-xs mt-1">
                <form method="POST" action="{{ route('admin.students.remove-course', [$student, $c]) }}" onsubmit="return confirm('Rimuovere questo corso?')">
                    @csrf @method('DELETE')
                    <button type="submit" style="color:#E28A53">Rimuovi</button>
                </form>
            </div>
        </div>
        @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Quiz attempts --}}
<div class="mt-6 p-5 rounded-xl" style="background:white;border:1px solid #C8D0D0">
    <h3 class="font-bold mb-4">Tentativi quiz</h3>
    @if($student->quizAttempts->isEmpty())
    <p class="text-sm" style="color:#8A9696">Nessun tentativo ancora.</p>
    @else
    <table class="w-full text-sm">
        <thead style="color:#8A9696"><tr>
            <th class="text-left py-2">Quiz</th>
            <th class="text-left py-2">Tentativo</th>
            <th class="text-left py-2">Punteggio</th>
            <th class="text-left py-2">Esito</th>
            <th class="text-left py-2">Data</th>
        </tr></thead>
        <tbody>
        @foreach($student->quizAttempts as $a)
        <tr style="border-top:1px solid #F5F7F7">
            <td class="py-2 font-medium">{{ $a->quiz->title ?? '—' }}</td>
            <td class="py-2">#{{ $a->attempt_number }}</td>
            <td class="py-2">{{ $a->score ?? '—' }}</td>
            <td class="py-2">
                @if($a->passed === true)
                <span class="text-xs px-2 py-0.5 rounded-full" style="background:#E8F5F5;color:#3A8C89">Superato</span>
                @elseif($a->passed === false)
                <span class="text-xs px-2 py-0.5 rounded-full" style="background:#fee;color:#900">Non superato</span>
                @else
                <span class="text-xs px-2 py-0.5 rounded-full" style="background:#F5F7F7;color:#8A9696">In corso</span>
                @endif
            </td>
            <td class="py-2 text-xs" style="color:#8A9696">{{ $a->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
