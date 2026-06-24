@extends('layouts.student')
@section('title', 'I miei documenti')
@section('breadcrumb', 'I miei documenti')

@section('content')
<div style="max-width:1200px; margin:0 auto;"
     x-data="studentDocsForm({
        modulesByCourse: @js($modulesByCourse->map(fn($mods) => $mods->map(fn($m) => ['id' => $m->id, 'label' => '['.$m->sort_order.'] '.$m->title]))),
        initialCourseId: ''
     })">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="font-size:1.5rem; margin:0;">📎 I miei documenti</h1>
    </div>

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12); border:1px solid rgba(85,177,174,0.4);
                border-radius:8px; color:#3A8C89; margin-bottom:14px; font-size:0.85rem;">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="padding:10px 14px; background:rgba(226,138,83,0.12); border:1px solid rgba(226,138,83,0.4);
                border-radius:8px; color:#B85A1E; margin-bottom:14px; font-size:0.85rem;">
        @foreach($errors->all() as $err)
            <div>⚠ {{ $err }}</div>
        @endforeach
    </div>
    @endif

    {{-- FORM UPLOAD --}}
    <div style="background:white; border-radius:12px; padding:18px; margin-bottom:18px;">
        <div style="font-weight:700; color:#1A1F1F; font-size:1rem; margin-bottom:12px;">Carica un nuovo documento</div>

        @if($isDemo)
        <div style="padding:10px 14px; background:rgba(226,138,83,0.12); border:1px solid rgba(226,138,83,0.4);
                    border-radius:8px; color:#B85A1E; font-size:0.85rem;">
            La modalità demo non consente il caricamento di file. Accedi con un account standard per usare questa funzione.
        </div>
        @else
        <form method="POST" action="{{ route('student.documents.store') }}" enctype="multipart/form-data"
              style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            @csrf

            <div style="grid-column:1/3;">
                <label style="font-size:0.7rem; color:#8A9696;">Titolo *</label>
                <input type="text" name="title" required maxlength="200" value="{{ old('title') }}"
                       style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">
            </div>

            <div style="grid-column:1/3;">
                <label style="font-size:0.7rem; color:#8A9696;">Descrizione</label>
                <textarea name="description" rows="2" maxlength="1000"
                          style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">{{ old('description') }}</textarea>
            </div>

            <div>
                <label style="font-size:0.7rem; color:#8A9696;">Corso (facoltativo)</label>
                <select name="course_id" x-model="courseId"
                        style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">
                    <option value="">— Nessun corso —</option>
                    @foreach($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size:0.7rem; color:#8A9696;">Modulo (facoltativo)</label>
                <select name="module_id" :disabled="!courseId || availableModules.length === 0"
                        style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">
                    <option value="">— Nessun modulo —</option>
                    <template x-for="m in availableModules" :key="m.id">
                        <option :value="m.id" x-text="m.label"></option>
                    </template>
                </select>
            </div>

            <div>
                <label style="font-size:0.7rem; color:#8A9696;">Visibilità *</label>
                <select name="visibility" required
                        style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">
                    @foreach($visibilities as $val => $label)
                    <option value="{{ $val }}" {{ old('visibility') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size:0.7rem; color:#8A9696;">File * (max 20 MB)</label>
                <input type="file" name="file" required
                       accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.md,.png,.jpg,.jpeg,.webp,.zip"
                       style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">
            </div>

            <div style="grid-column:1/3; display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
                <button type="submit"
                        style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                    Carica documento
                </button>
            </div>
        </form>
        @endif
    </div>

    {{-- FILTRI --}}
    <form method="GET"
          style="background:white; border-radius:12px; padding:14px; margin-bottom:18px;
                 display:grid; grid-template-columns:1fr 1fr auto auto; gap:10px; align-items:end;">
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Corso</label>
            <select name="course_id" style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.8rem;">
                <option value="">Tutti</option>
                @foreach($courses as $c)
                <option value="{{ $c->id }}" {{ ($filters['course_id'] ?? null) === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Visibilità</label>
            <select name="visibility" style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.8rem;">
                <option value="">Tutte</option>
                @foreach($visibilities as $val => $label)
                <option value="{{ $val }}" {{ ($filters['visibility'] ?? null) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:5px; cursor:pointer; font-size:0.8rem;">Filtra</button>
        <a href="{{ route('student.documents.index') }}"
           style="padding:8px 14px; color:#8A9696; text-decoration:none; font-size:0.8rem;">Reset</a>
    </form>

    {{-- LISTA --}}
    @if($documents->isEmpty())
    <div style="background:white; border-radius:12px; padding:40px; text-align:center; color:#8A9696;">
        Nessun documento caricato.
    </div>
    @else
    <div style="display:flex; flex-direction:column; gap:10px;">
        @foreach($documents as $doc)
        <div style="background:white; border-radius:10px; padding:16px;
                    border-left:4px solid {{ $doc->visibility === 'instructors' ? '#E28A53' : '#55B1AE' }};">
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <div style="font-size:1.4rem;">📄</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem; margin-bottom:3px;">
                        {{ $doc->title }}
                    </div>
                    <div style="font-size:0.7rem; color:#8A9696; display:flex; gap:8px; flex-wrap:wrap; margin-bottom:6px;">
                        <span style="background:{{ $doc->visibility === 'instructors' ? 'rgba(226,138,83,0.15)' : 'rgba(85,177,174,0.15)' }};
                                     color:{{ $doc->visibility === 'instructors' ? '#B85A1E' : '#3A8C89' }};
                                     padding:2px 8px; border-radius:8px; font-weight:600;">
                            {{ $doc->visibility_label }}
                        </span>
                        @if($doc->course)
                        <span>·</span>
                        <span>📚 {{ $doc->course->name }}</span>
                        @endif
                        @if($doc->module)
                        <span>·</span>
                        <span>📍 {{ $doc->module->title }}</span>
                        @endif
                        <span>·</span>
                        <span>{{ strtoupper($doc->file_type ?? '') }} · {{ $doc->human_size }}</span>
                        <span>·</span>
                        <span>{{ $doc->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    @if($doc->description)
                    <div style="color:#5A6464; font-size:0.85rem; line-height:1.5;">
                        {{ $doc->description }}
                    </div>
                    @endif
                </div>
                <div style="display:flex; flex-direction:column; gap:5px; flex-shrink:0;">
                    <a href="{{ route('student.documents.download', $doc->id) }}"
                       style="padding:6px 12px; background:#55B1AE; color:white; border-radius:5px; text-decoration:none; font-size:0.75rem; text-align:center;">
                        ⬇ Scarica
                    </a>

                    @if(!$isDemo)
                    <details style="position:relative;">
                        <summary style="padding:6px 12px; background:#F5F7F7; color:#3A8C89; border-radius:5px; cursor:pointer; font-size:0.75rem; text-align:center; list-style:none;">
                            Modifica
                        </summary>
                        <div style="position:absolute; right:0; top:100%; margin-top:4px; background:white; border:1px solid #E8F5F5; border-radius:8px; padding:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:10; width:280px;">
                            <form method="POST" action="{{ route('student.documents.update', $doc->id) }}">
                                @csrf
                                @method('PUT')
                                <label style="font-size:0.7rem; color:#8A9696;">Titolo</label>
                                <input type="text" name="title" value="{{ $doc->title }}" required maxlength="200"
                                       style="width:100%; padding:6px; border:1px solid #E8F5F5; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">

                                <label style="font-size:0.7rem; color:#8A9696;">Descrizione</label>
                                <textarea name="description" rows="2" maxlength="1000"
                                          style="width:100%; padding:6px; border:1px solid #E8F5F5; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">{{ $doc->description }}</textarea>

                                <label style="font-size:0.7rem; color:#8A9696;">Corso</label>
                                <select name="course_id"
                                        style="width:100%; padding:6px; border:1px solid #E8F5F5; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">
                                    <option value="">— Nessuno —</option>
                                    @foreach($courses as $c)
                                    <option value="{{ $c->id }}" {{ $doc->course_id === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                    @endforeach
                                </select>

                                <label style="font-size:0.7rem; color:#8A9696;">Modulo</label>
                                <select name="module_id"
                                        style="width:100%; padding:6px; border:1px solid #E8F5F5; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">
                                    <option value="">— Nessuno —</option>
                                    @if($doc->course_id && isset($modulesByCourse[$doc->course_id]))
                                        @foreach($modulesByCourse[$doc->course_id] as $m)
                                        <option value="{{ $m->id }}" {{ $doc->module_id === $m->id ? 'selected' : '' }}>
                                            [{{ $m->sort_order }}] {{ $m->title }}
                                        </option>
                                        @endforeach
                                    @endif
                                </select>

                                <label style="font-size:0.7rem; color:#8A9696;">Visibilità</label>
                                <select name="visibility" required
                                        style="width:100%; padding:6px; border:1px solid #E8F5F5; border-radius:4px; font-size:0.8rem; margin-bottom:10px;">
                                    @foreach($visibilities as $val => $label)
                                    <option value="{{ $val }}" {{ $doc->visibility === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>

                                <button type="submit"
                                        style="width:100%; padding:7px; background:#55B1AE; color:white; border:none; border-radius:5px; font-size:0.8rem; cursor:pointer;">
                                    Salva modifiche
                                </button>
                            </form>
                        </div>
                    </details>

                    <form method="POST" action="{{ route('student.documents.destroy', $doc->id) }}"
                          onsubmit="return confirm('Eliminare questo documento?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                style="padding:6px 12px; background:rgba(226,138,83,0.1); color:#B85A1E; border:1px solid rgba(226,138,83,0.3); border-radius:5px; font-size:0.75rem; cursor:pointer; width:100%;">
                            Elimina
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div style="margin-top:18px;">
        {{ $documents->links() }}
    </div>
    @endif
</div>

<script>
function studentDocsForm({ modulesByCourse, initialCourseId }) {
    return {
        courseId: initialCourseId || '',
        modulesByCourse,
        get availableModules() {
            if (!this.courseId) return [];
            return this.modulesByCourse[this.courseId] || [];
        },
    };
}
</script>
@endsection
