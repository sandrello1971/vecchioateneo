@extends('layouts.admin')
@section('title', 'Modifica studente — ' . $student->name)
@section('content')

<div style="max-width:700px;">
    <div style="margin-bottom:20px;">
        <a href="/admin/students/{{ $student->id }}" style="color:#8A9696; font-size:0.8rem; text-decoration:none;">&larr; Dettaglio studente</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">Modifica: {{ $student->name }}</h2>
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        {{ session('success') }}
    </div>
    @endif

    <div style="background:white; border-radius:10px; padding:24px; margin-bottom:16px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Dati anagrafici</h3>
        <form method="POST" action="/admin/students/{{ $student->id }}">
            @csrf @method('PUT')
            <div style="display:grid; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome completo *</label>
                    <input type="text" name="name" value="{{ old('name', $student->name) }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('name')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $student->email) }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('email')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Telefono</label>
                        <input type="text" name="phone" value="{{ old('phone', $student->phone) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Azienda</label>
                        <input type="text" name="company" value="{{ old('company', $student->company) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ruolo aziendale</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $student->job_title) }}"
                           placeholder="es. CEO, Marketing Manager"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>

                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px; background:#F5F7F7; border-radius:8px;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $student->is_active) ? 'checked' : '' }}>
                    <div>
                        <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Studente attivo</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Se disattivato, non potrà accedere alla piattaforma</div>
                    </div>
                </label>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <a href="/admin/students/{{ $student->id }}" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Salva modifiche
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Sezione separata: Permessi sistema --}}
    <div style="background:white; border:2px solid #E28A53; border-radius:12px;
                padding:20px; margin-top:24px; margin-bottom:24px;">

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
            <div style="font-size:1.4rem;">🔑</div>
            <div>
                <div style="font-weight:700; color:#1A1F1F; font-size:1rem;">
                    Permessi sistema
                </div>
                <div style="font-size:0.75rem; color:#8A9696;">
                    Cambia il ruolo dell'utente nel sistema (separato dal job title)
                </div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('admin.students.update-system-role', $student->id) }}">
            @csrf
            @method('PATCH')

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; align-items:end; margin-bottom:14px;">
                <div>
                    <label style="font-size:0.75rem; color:#8A9696; font-weight:600;">
                        Ruolo sistema
                    </label>
                    <select name="role"
                            style="width:100%; padding:10px; border:1px solid #E8F5F5;
                                   border-radius:6px; font-size:0.9rem;">
                        <option value="" {{ is_null($student->role) ? 'selected' : '' }}>
                            — Studente normale (default) —
                        </option>
                        @foreach(\App\Models\Student::SYSTEM_ROLES as $key => $label)
                        <option value="{{ $key }}" {{ $student->role === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem; padding-top:14px;">
                        <input type="checkbox" name="auto_enroll_all_courses" value="1"
                               {{ $student->auto_enroll_all_courses ? 'checked' : '' }}>
                        Auto-iscrizione a tutti i corsi
                    </label>
                    <div style="font-size:0.7rem; color:#8A9696; margin-top:2px;">
                        Tipico per formatori interni {{ atheneum_setting('platform_owner', 'Noscite') }}
                    </div>
                </div>
            </div>

            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem; margin-bottom:14px;
                          background:#FFF4E8; border:1px solid rgba(226,138,83,0.3); border-radius:6px; padding:10px 14px;">
                <input type="checkbox" name="is_instructor" value="1"
                       {{ $student->is_instructor ? 'checked' : '' }}>
                <span><strong>Formatore corsi</strong> — capacità <em>cumulabile</em> col ruolo qui sopra (es. Docente Schola <em>e</em> Formatore insieme). Dà accesso all'area formatore e all'insegnamento dei corsi Atheneum.</span>
            </label>

            <div style="font-size:0.75rem; color:#5A6464; background:#FFFBEB;
                        padding:10px 14px; border-radius:6px; margin-bottom:14px;
                        border:1px solid rgba(226,138,83,0.3);">
                <strong>⚠️ Attenzione:</strong> assegnare il ruolo "Formatore" dà accesso ai
                manuali formatore, alle annotazioni private e alla sezione "Note per il formatore"
                di {{ atheneum_setting('assistant_name', 'Minerva') }}. "Amministratore" dà
                controllo totale. Promuovi solo membri fidati del team {{ atheneum_setting('platform_owner', 'Noscite') }}.
            </div>

            <div style="display:flex; justify-content:flex-end;">
                <button type="submit"
                        style="padding:10px 20px; background:#E28A53; color:white;
                               border:none; border-radius:6px; font-size:0.85rem;
                               font-weight:600; cursor:pointer;">
                    💾 Salva permessi
                </button>
            </div>
        </form>
    </div>

    <div style="background:white; border-radius:10px; padding:24px; margin-bottom:16px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Corsi assegnati</h3>

        @php
            $assignedIds = $student->courses->pluck('id')->toArray();
            $instructorsByCourse = $courses->mapWithKeys(fn($c) => [$c->id => $c->instructors->map(fn($i) => [
                'id'    => $i->id,
                'label' => $i->company ? "{$i->name} ({$i->company})" : $i->name,
            ])->values()->all()]);
        @endphp

        @if(count($assignedIds) > 0)
        <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
            @foreach($student->courses as $c)
            @php
                $courseInstructors = $instructorsByCourse[$c->id] ?? [];
                $assignedInstructorId = $c->pivot->instructor_id;
                $assignedInstructor = $assignedInstructorId
                    ? \App\Models\Student::find($assignedInstructorId)
                    : null;
            @endphp
            <div style="padding:10px 14px; background:#F5F7F7; border-radius:8px;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span>{{ $c->icon }}</span>
                        <div>
                            <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $c->name }}</div>
                            <div style="font-size:0.75rem; color:#8A9696;">
                                Iscritto il {{ $c->pivot->enrolled_at ? \Carbon\Carbon::parse($c->pivot->enrolled_at)->format('d/m/Y') : '—' }}
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.students.remove-course', [$student, $c]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" onclick="return confirm('Rimuovere {{ $c->name }}?')"
                                style="padding:6px 12px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                            Rimuovi
                        </button>
                    </form>
                </div>

                <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #E8F5F5;">
                    @if(count($courseInstructors) === 0)
                        <div style="font-size:0.75rem; color:#8A9696; font-style:italic;">
                            ⚠ Nessun formatore associato al corso.
                        </div>
                    @else
                        @if(!$assignedInstructorId)
                        <div style="font-size:0.72rem; color:#E28A53; margin-bottom:4px;">
                            ⚠ Nessun formatore assegnato a questa iscrizione: selezionalo e salva.
                        </div>
                        @endif
                        <form method="POST" action="{{ route('admin.students.update-course-instructor', [$student, $c]) }}"
                              style="display:flex; gap:8px; align-items:center;">
                            @csrf @method('PATCH')
                            <label style="font-size:0.7rem; color:#8A9696;">Formatore *</label>
                            <select name="instructor_id" required
                                    style="flex:1; padding:6px; border:1px solid #C8D0D0; border-radius:5px; font-size:0.78rem;">
                                @foreach($courseInstructors as $opt)
                                <option value="{{ $opt['id'] }}" {{ $assignedInstructorId === $opt['id'] ? 'selected' : '' }}>
                                    {{ $opt['label'] }}
                                </option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    style="padding:6px 12px; background:#55B1AE; color:white; border:none; border-radius:5px; font-size:0.75rem; cursor:pointer;">
                                Salva
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">Nessun corso assegnato.</p>
        @endif

        <form method="POST" action="{{ route('admin.students.assign-course', $student) }}"
              x-data="{ courseId: '', instructorsByCourse: @js($instructorsByCourse) }">
            @csrf
            @error('instructor_id')<p style="color:#E28A53; font-size:0.75rem; margin-bottom:6px;">{{ $message }}</p>@enderror
            <div style="display:grid; grid-template-columns:1fr auto; gap:10px; align-items:start;">
                <div>
                    <select name="course_id" required x-model="courseId"
                            style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                        <option value="">— Seleziona corso da assegnare —</option>
                        @foreach($courses as $course)
                            @if(!in_array($course->id, $assignedIds))
                            <option value="{{ $course->id }}">{{ $course->icon }} {{ $course->name }}</option>
                            @endif
                        @endforeach
                    </select>

                    <template x-if="courseId">
                        <div style="margin-top:8px;">
                            <template x-if="(instructorsByCourse[courseId] || []).length === 0">
                                <div style="font-size:0.75rem; color:#8A9696; font-style:italic;">
                                    ⚠ Nessun formatore associato — il discente resterà senza formatore.
                                </div>
                            </template>
                            <template x-if="(instructorsByCourse[courseId] || []).length === 1">
                                <div style="font-size:0.75rem; color:#3A8C89;">
                                    Formatore: <strong x-text="instructorsByCourse[courseId][0].label"></strong>
                                </div>
                            </template>
                            <template x-if="(instructorsByCourse[courseId] || []).length > 1">
                                <div>
                                    <label style="font-size:0.7rem; color:#8A9696;">Formatore *</label>
                                    <select name="instructor_id"
                                            style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                                        <option value="">— Seleziona formatore —</option>
                                        <template x-for="ins in instructorsByCourse[courseId]" :key="ins.id">
                                            <option :value="ins.id" x-text="ins.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <button type="submit"
                        style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer; align-self:start;">
                    Assegna
                </button>
            </div>
        </form>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:12px;">Credenziali</h3>
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
            Invia nuove credenziali temporanee via email. La vecchia password sarà invalidata.
        </p>
        <form method="POST" action="/admin/students/{{ $student->id }}/send-credentials">
            @csrf
            <button type="submit" onclick="return confirm('Inviare nuove credenziali a {{ $student->email }}?')"
                    style="padding:10px 20px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                &#128231; Invia nuove credenziali
            </button>
        </form>
    </div>
</div>

@endsection
