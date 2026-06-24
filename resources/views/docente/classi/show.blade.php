@extends('layouts.docente')
@section('title', $class->name)
@section('breadcrumb', 'Classi / ' . $class->name)
@section('content')
<div style="max-width:960px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.classes.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Classi</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $class->name }}
        @if($class->is_archived)<span style="font-size:0.7rem; color:#A8521F; background:#FDECE2; border:1px solid #E28A53; border-radius:4px; padding:2px 8px; margin-left:8px;">Archiviata</span>@endif
    </h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:12px;">{{ $class->subject->name ?? '—' }} · {{ $class->school_year }}</p>
    <div style="margin-bottom:20px;">
        <a href="{{ route('docente.classes.minerva', $class) }}" style="display:inline-block; padding:8px 14px; background:#1A1F1F; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none;">&#9788; Apri Minerva (tuoi materiali + classe)</a>
        <a href="{{ route('docente.classes.activity', $class) }}" style="display:inline-block; padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none; margin-left:6px;">&#128202; Attività</a>
        <a href="{{ route('docente.classes.questions', $class) }}" style="display:inline-block; padding:8px 14px; background:white; color:#A8521F; border:1px solid #E28A53; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none; margin-left:6px;">&#10067; Domande scoperte ({{ $openQuestionsCount ?? 0 }})</a>
        <a href="{{ route('docente.classi.messaggi.index', $class) }}" style="display:inline-block; padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none; margin-left:6px;">&#9993; Messaggi</a>
        <a href="{{ route('docente.classi.annunci.index', $class) }}" style="display:inline-block; padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none; margin-left:6px;">&#128226; Annunci</a>
    </div>

    @if ($errors->any())
        <div style="background:#FDECE2; border:1px solid #E28A53; color:#A8521F; border-radius:8px; padding:14px 16px; margin-bottom:16px; font-size:0.85rem;">
            <ul style="margin:0 0 0 18px; padding:0;">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Classe di SCUOLA: gestita dalla segreteria, il docente la vede in sola lettura (§3, P15) --}}
    @if($class->school_id !== null)
    <div style="background:#F0F6F6; border:1px solid #C8E0E0; border-radius:10px; padding:14px 18px; margin-bottom:16px; font-size:0.85rem; color:#3A8C89;">
        &#128274; Classe gestita dalla <strong>segreteria scolastica</strong>: roster, codice e impostazioni si modificano dall'area Scuola. Qui prepari e pubblichi i materiali per le tue cattedre.
    </div>
    @endif

    @if($class->school_id === null)
    {{-- Codice invito --}}
    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:16px; border:1px solid #C8D0D0;">
        <div style="font-size:0.8rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Codice invito</div>
        <div style="display:flex; align-items:center; gap:12px;">
            <code id="invite-code" style="font-size:1.6rem; font-weight:700; letter-spacing:0.2em; color:#1A1F1F; background:#F5F7F7; padding:8px 18px; border-radius:8px;">{{ $class->invite_code }}</code>
            <button onclick="navigator.clipboard.writeText('{{ $class->invite_code }}'); this.textContent='Copiato!';"
                    style="padding:8px 14px; background:#E8F5F5; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; cursor:pointer;">Copia</button>
            <span style="font-size:0.8rem; color:{{ $class->invite_enabled ? '#3A8C89' : '#A8521F' }};">
                {{ $class->invite_enabled ? 'attivo' : 'disattivato' }}
            </span>
            <form method="POST" action="{{ route('docente.classes.regenerate-code', $class) }}" style="margin-left:auto;"
                  onsubmit="return confirm('Rigenerare il codice? Il precedente smetterà di funzionare.');">
                @csrf
                <button type="submit" style="padding:8px 14px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.8rem; cursor:pointer;">Rigenera</button>
            </form>
        </div>
    </div>

    {{-- Impostazioni classe --}}
    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:16px; border:1px solid #C8D0D0;">
        <form method="POST" action="{{ route('docente.classes.update', $class) }}">
            @csrf @method('PATCH')
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; align-items:end;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Nome</label>
                    <input type="text" name="name" value="{{ old('name', $class->name) }}" required
                           style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                </div>
            </div>
            <label style="display:flex; align-items:center; gap:8px; margin-top:12px; font-size:0.85rem; color:#4A5252;">
                <input type="checkbox" name="requires_approval" value="1" {{ $class->requires_approval ? 'checked' : '' }}> Richiedi approvazione nuovi ingressi
            </label>
            <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:0.85rem; color:#4A5252;">
                <input type="checkbox" name="invite_enabled" value="1" {{ $class->invite_enabled ? 'checked' : '' }}> Codice invito attivo
            </label>
            <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:0.85rem; color:#4A5252;">
                <input type="checkbox" name="is_archived" value="1" {{ $class->is_archived ? 'checked' : '' }}> Archivia classe (blocca nuovi ingressi e attività studente)
            </label>
            <button type="submit" style="margin-top:14px; padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">Salva</button>
        </form>
    </div>
    @endif

    {{-- Roster --}}
    <div style="background:white; border-radius:10px; padding:20px; border:1px solid #C8D0D0;">
        <h2 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:14px;">Iscritti</h2>

        @php $pending = $roster->get('pending', collect()); $active = $roster->get('active', collect()); $removed = $roster->get('removed', collect()); @endphp

        @if($class->school_id === null && $pending->isNotEmpty())
            <div style="font-size:0.75rem; font-weight:700; color:#E28A53; text-transform:uppercase; margin:10px 0 6px;">In attesa di approvazione ({{ $pending->count() }})</div>
            @foreach($pending as $e)
                <div style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #F5F7F7;">
                    <div style="flex:1; font-size:0.875rem; color:#1A1F1F;">{{ $e->student->name }} <span style="color:#8A9696;">· {{ $e->student->email }}</span></div>
                    <form method="POST" action="{{ route('docente.classes.roster.update', [$class, $e]) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="action" value="approve">
                        <button style="padding:6px 12px; background:#E8F5F5; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.78rem; cursor:pointer;">Approva</button>
                    </form>
                    <form method="POST" action="{{ route('docente.classes.roster.update', [$class, $e]) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="action" value="remove">
                        <button style="padding:6px 12px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.78rem; cursor:pointer;">Rifiuta</button>
                    </form>
                </div>
            @endforeach
        @endif

        <div style="font-size:0.75rem; font-weight:700; color:#3A8C89; text-transform:uppercase; margin:14px 0 6px;">Attivi ({{ $active->count() }})</div>
        @forelse($active as $e)
            <div style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #F5F7F7;">
                <div style="flex:1; font-size:0.875rem; color:#1A1F1F;">{{ $e->student->name }} <span style="color:#8A9696;">· {{ $e->student->email ?? $e->student->username }}</span></div>
                @if($class->school_id === null)
                <form method="POST" action="{{ route('docente.classes.roster.update', [$class, $e]) }}"
                      onsubmit="return confirm('Rimuovere {{ $e->student->name }} dalla classe?');">
                    @csrf @method('PATCH')
                    <input type="hidden" name="action" value="remove">
                    <button style="padding:6px 12px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.78rem; cursor:pointer;">Rimuovi</button>
                </form>
                @endif
            </div>
        @empty
            <p style="color:#8A9696; font-size:0.85rem;">Nessuno studente attivo.</p>
        @endforelse

        @if($removed->isNotEmpty())
            <div style="font-size:0.75rem; font-weight:700; color:#8A9696; text-transform:uppercase; margin:14px 0 6px;">Rimossi ({{ $removed->count() }})</div>
            @foreach($removed as $e)
                <div style="padding:6px 0; font-size:0.82rem; color:#8A9696;">{{ $e->student->name }}</div>
            @endforeach
        @endif
    </div>
</div>
@endsection
