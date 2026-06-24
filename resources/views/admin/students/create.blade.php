@extends('layouts.admin')
@section('title', 'Nuovo Studente')
@section('content')

<div style="max-width:600px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:20px;">Crea nuovo studente</h2>

    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="/admin/students">
            @csrf
            <div style="display:grid; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome completo *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('name')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('email')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Azienda</label>
                        <input type="text" name="company" value="{{ old('company') }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ruolo aziendale</label>
                        <input type="text" name="job_title" value="{{ old('job_title') }}"
                               placeholder="es. CEO, Marketing Manager"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <div x-data="{ selected: @js(old('course_ids', [])) }">
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:10px;">Corsi da assegnare</label>
                    @error('instructor_id')<p style="color:#E28A53; font-size:0.75rem; margin-bottom:6px;">{{ $message }}</p>@enderror
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        @foreach($courses as $course)
                        @php $instructors = $course->instructors; @endphp
                        <div style="border:1px solid #C8D0D0; border-radius:8px; padding:10px;">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" name="course_ids[]" value="{{ $course->id }}"
                                       x-model="selected">
                                <span>{{ $course->icon }}</span>
                                <div style="flex:1;">
                                    <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $course->name }}</div>
                                    <div style="font-size:0.75rem; color:#8A9696;">{{ $course->short_description }}</div>
                                </div>
                            </label>

                            <div x-show="selected.includes('{{ $course->id }}')" x-cloak
                                 style="margin-top:10px; padding-top:10px; border-top:1px dashed #E8F5F5;">
                                @if($instructors->count() === 0)
                                    <div style="font-size:0.75rem; color:#8A9696; font-style:italic;">
                                        ⚠ Nessun formatore associato a questo corso. L'iscrizione resterà senza formatore assegnato.
                                    </div>
                                @elseif($instructors->count() === 1)
                                    @php $only = $instructors->first(); @endphp
                                    <div style="font-size:0.75rem; color:#3A8C89;">
                                        Formatore: <strong>{{ $only->name }}</strong>{{ $only->company ? ' ('.$only->company.')' : '' }}
                                    </div>
                                @else
                                    <label style="font-size:0.7rem; color:#8A9696; display:block; margin-bottom:4px;">Formatore *</label>
                                    <select name="instructor_ids[{{ $course->id }}]"
                                            style="width:100%; padding:7px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                                        <option value="">— Seleziona formatore —</option>
                                        @foreach($instructors as $ins)
                                        <option value="{{ $ins->id }}" {{ old('instructor_ids.'.$course->id) === $ins->id ? 'selected' : '' }}>
                                            {{ $ins->name }}{{ $ins->company ? ' ('.$ins->company.')' : '' }}
                                        </option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div style="background:#E8F5F5; border-radius:8px; padding:12px;">
                    <div style="font-size:0.8rem; font-weight:600; color:#3A8C89; margin-bottom:4px;">&#128231; Email automatica</div>
                    <div style="font-size:0.75rem; color:#4A5252;">Lo studente ricevera automaticamente un'email con le credenziali di accesso e la password temporanea.</div>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <a href="/admin/students" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Crea studente
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
