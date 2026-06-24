@extends('layouts.admin')
@section('title', $school->name)
@section('content')
@php $typeLabels = ['liceo'=>'Liceo','istituto_tecnico'=>'Istituto tecnico','altro'=>'Altro']; @endphp
<div style="max-width:820px;">
    <div style="margin-bottom:8px;"><a href="{{ route('admin.scuole.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Scuole</a></div>
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">{{ $school->name }}</h1>
        <span style="font-size:0.72rem; font-weight:700; color:{{ $school->status==='active' ? '#3A8C89' : '#A8521F' }};">{{ $school->status==='active' ? 'ATTIVA' : 'SOSPESA' }}</span>
    </div>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">{{ $typeLabels[$school->type] ?? $school->type }}@if($school->city) · {{ $school->city }}@endif · slug <code>{{ $school->slug }}</code></p>

    @if(session('success'))<div style="margin-bottom:14px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('temp_password'))
    <div style="margin-bottom:14px; padding:14px 16px; background:#FBF6E2; border:1px solid #E2A653; border-radius:8px; color:#7A5B0E; font-size:0.88rem;">
        <strong>&#9888; Annotala ora — mostrata una sola volta, non sarà più recuperabile.</strong><br>
        Password temporanea @if(session('temp_password_for'))per <strong>{{ session('temp_password_for') }}</strong>@endif (cambio obbligatorio al primo accesso):
        <div style="font-family:monospace; font-size:1.1rem; font-weight:700; margin-top:6px; color:#1A1F1F;">{{ session('temp_password') }}</div>
    </div>
    @endif
    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    {{-- Conteggi --}}
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px;">
        @foreach(['Segreteria'=>$school->school_admins_count, 'Docenti'=>$school->teachers_count, 'Studenti'=>$school->students_count, 'Classi'=>$school->school_classes_count] as $lab=>$n)
            <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px;">
                <div style="font-size:1.5rem; font-weight:800; color:#1A1F1F;">{{ $n }}</div>
                <div style="font-size:0.78rem; color:#8A9696;">{{ $lab }}</div>
            </div>
        @endforeach
    </div>

    {{-- Segreteria: elenco + azioni di recupero --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Segreteria (school_admin)</div>
        @forelse($admins as $a)
            <div style="padding:10px 0; border-bottom:1px solid #F0F2F2;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                    <div style="font-size:0.85rem;">
                        {{ $a->name }} <span style="color:#8A9696;">· {{ $a->email }}</span>
                        <span style="margin-left:6px; font-size:0.72rem; font-weight:700; color:{{ !$a->is_active ? '#A8521F' : ($a->must_change_password ? '#E28A53' : '#3A8C89') }};">
                            {{ !$a->is_active ? 'disattivato' : ($a->must_change_password ? 'invito in sospeso / cambio pw' : 'attivo') }}
                        </span>
                        <span style="font-size:0.72rem; color:#8A9696;">· ultimo accesso: {{ $a->last_login_at?->format('d/m/Y H:i') ?? 'mai' }}</span>
                    </div>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <form method="POST" action="{{ route('admin.scuole.segreteria.reset', [$school, $a]) }}">@csrf
                            <label style="font-size:0.68rem; color:#8A9696;"><input type="checkbox" name="send_email" value="1"> email</label>
                            <button style="padding:4px 9px; background:#E8F5F5; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.72rem; cursor:pointer;">Reset password</button>
                        </form>
                        <form method="POST" action="{{ route('admin.scuole.segreteria.resend', [$school, $a]) }}">@csrf
                            <button style="padding:4px 9px; background:white; color:#3A8C89; border:1px solid #C8D0D0; border-radius:6px; font-size:0.72rem; cursor:pointer;">Reinvia invito</button>
                        </form>
                        <form method="POST" action="{{ route('admin.scuole.segreteria.toggle', [$school, $a]) }}">@csrf @method('PATCH')
                            <button style="padding:4px 9px; background:white; color:{{ $a->is_active ? '#A8521F' : '#3A8C89' }}; border:1px solid {{ $a->is_active ? '#E2A653' : '#55B1AE' }}; border-radius:6px; font-size:0.72rem; cursor:pointer;">{{ $a->is_active ? 'Disattiva' : 'Riattiva' }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <p style="color:#8A9696; font-size:0.85rem; margin:0 0 12px;">Nessuna segreteria nominata.</p>
        @endforelse

        <div style="font-size:0.72rem; font-weight:700; color:#4A5252; text-transform:uppercase; margin:14px 0 8px;">Aggiungi segreteria</div>
        <form method="POST" action="{{ route('admin.scuole.nominate', $school) }}" data-async style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            @csrf
            <input type="text" name="name" placeholder="Nome e cognome" required style="flex:1; min-width:160px; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
            <input type="email" name="email" placeholder="email@scuola.it" required style="flex:1; min-width:180px; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
            <label style="font-size:0.72rem; color:#8A9696;"><input type="checkbox" name="send_email" value="1"> invia email</label>
            <button data-busy-label="Aggiungo…" style="padding:8px 16px; background:#1A1F1F; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">Aggiungi segreteria</button>
        </form>
    </div>

    {{-- Modifica / stato --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Impostazioni</div>
        <form method="POST" action="{{ route('admin.scuole.update', $school) }}">
            @csrf @method('PATCH')
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div><label style="display:block; font-size:0.78rem; color:#4A5252; margin-bottom:4px;">Nome</label><input type="text" name="name" value="{{ $school->name }}" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;"></div>
                <div><label style="display:block; font-size:0.78rem; color:#4A5252; margin-bottom:4px;">Città</label><input type="text" name="city" value="{{ $school->city }}" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;"></div>
                <div><label style="display:block; font-size:0.78rem; color:#4A5252; margin-bottom:4px;">Tipo</label>
                    <select name="type" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
                        @foreach($typeLabels as $k=>$lab)<option value="{{ $k }}" @selected($school->type===$k)>{{ $lab }}</option>@endforeach
                    </select>
                </div>
                <div><label style="display:block; font-size:0.78rem; color:#4A5252; margin-bottom:4px;">Stato</label>
                    <select name="status" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
                        <option value="active" @selected($school->status==='active')>Attiva</option>
                        <option value="suspended" @selected($school->status==='suspended')>Sospesa</option>
                    </select>
                </div>
            </div>
            <label style="display:flex; gap:8px; align-items:center; font-size:0.82rem; color:#4A5252; margin:14px 0 8px;">
                <input type="checkbox" name="allow_professor_create_classes" value="1" @checked($school->allow_professor_create_classes)>
                Consenti ai docenti di creare classi
            </label>
            <label style="display:flex; gap:8px; align-items:center; font-size:0.82rem; color:#4A5252; margin-bottom:16px;">
                <input type="checkbox" name="dpa_signed" value="1" @checked($school->dpa_signed_at)>
                DPA firmato (accordo titolare/responsabile, art. 28){{ $school->dpa_signed_at ? ' — '.$school->dpa_signed_at->format('d/m/Y') : '' }}
            </label>
            <button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Salva</button>
        </form>
    </div>
</div>
@endsection
