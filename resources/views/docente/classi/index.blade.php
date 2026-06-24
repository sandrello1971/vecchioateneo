@extends('layouts.docente')
@section('title', 'Classi')
@section('breadcrumb', 'Classi')
@section('content')
<div style="max-width:960px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:20px;">Le tue classi</h1>

    @if ($errors->any())
        <div style="background:#FDECE2; border:1px solid #E28A53; color:#A8521F; border-radius:8px; padding:14px 16px; margin-bottom:16px; font-size:0.85rem;">
            <ul style="margin:0 0 0 18px; padding:0;">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Nuova classe: nascosta ai docenti di scuola senza deroga (modello puro, P15) --}}
    @if($canCreate ?? true)
    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:24px; border:1px solid #C8D0D0;">
        <h2 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:14px;">Nuova classe</h2>
        <form method="POST" action="{{ route('docente.classes.store') }}">
            @csrf
            <div style="display:grid; grid-template-columns:2fr 2fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Nome *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="3ªB"
                           style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Materia *</label>
                    <select name="subject_id" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                        <option value="">— seleziona —</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(old('subject_id') === $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Anno *</label>
                    <input type="text" name="school_year" value="{{ old('school_year', '2026/2027') }}" required placeholder="2026/2027"
                           style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                </div>
            </div>
            <label style="display:flex; align-items:center; gap:8px; margin-top:12px; font-size:0.85rem; color:#4A5252;">
                <input type="checkbox" name="requires_approval" value="1" {{ old('requires_approval', true) ? 'checked' : '' }}>
                Richiedi approvazione del docente per i nuovi ingressi
            </label>
            <button type="submit" style="margin-top:14px; padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">
                Crea classe
            </button>
        </form>
    </div>
    @endif

    {{-- Elenco classi --}}
    @forelse ($classes as $class)
        <a href="{{ route('docente.classes.show', $class) }}"
           style="display:block; background:white; border-radius:10px; padding:16px 20px; margin-bottom:10px; border:1px solid #C8D0D0; text-decoration:none;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="flex:1;">
                    <div style="font-weight:700; color:#1A1F1F;">{{ $class->name }}
                        @if($class->is_archived)<span style="font-size:0.65rem; color:#8A9696; text-transform:uppercase; margin-left:6px;">archiviata</span>@endif
                    </div>
                    <div style="font-size:0.8rem; color:#8A9696;">{{ $class->subject->name ?? '—' }} · {{ $class->school_year }}</div>
                </div>
                <div style="font-size:0.8rem; color:#4A5252;">
                    {{ $class->active_count }} attivi
                    @if($class->pending_count > 0)<span style="color:#E28A53; font-weight:700;"> · {{ $class->pending_count }} in attesa</span>@endif
                </div>
                <code style="background:#F5F7F7; padding:4px 10px; border-radius:6px; font-size:0.85rem; letter-spacing:0.08em;">{{ $class->invite_code }}</code>
            </div>
        </a>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessuna classe ancora. Creane una qui sopra.</p>
    @endforelse
</div>
@endsection
