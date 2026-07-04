@extends('layouts.scuola')
@section('title', 'Materiali della scuola')
@section('content')
<div style="max-width:1000px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1;">Materiali della scuola</h1>
        <a href="{{ route('scuola.materiali.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">+ Carica materiale</a>
    </div>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">Tutti i materiali dei docenti della scuola. I materiali che carichi qui finiscono in Biblioteca (utilizzabili dai docenti nelle lezioni).</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif

    <form method="GET" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px; margin-bottom:16px; display:grid; grid-template-columns:repeat(4,1fr); gap:10px;">
        <select name="teacher_id" style="padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <option value="">Tutti i docenti</option>
            @foreach($teachers as $t)<option value="{{ $t->id }}" @selected(request('teacher_id')===$t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <select name="source_type" style="padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <option value="">Tutti i tipi</option>
            @foreach(['audio','youtube','photos','pdf','docx','text'] as $t)<option value="{{ $t }}" @selected(request('source_type')===$t)>{{ $t }}</option>@endforeach
        </select>
        <select name="status" style="padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
            <option value="">Tutti gli stati</option>
            @foreach(['pending','processing','ready','failed'] as $st)<option value="{{ $st }}" @selected(request('status')===$st)>{{ $st }}</option>@endforeach
        </select>
        <div style="display:flex; gap:8px; align-items:center;">
            <label style="font-size:0.78rem; color:#4A5252; display:flex; gap:5px; align-items:center;"><input type="checkbox" name="school_only" value="1" @checked(request('school_only'))> Solo di scuola</label>
            <button style="padding:8px 14px; background:#1A1F1F; color:white; border:none; border-radius:6px; font-size:0.82rem; cursor:pointer;">Filtra</button>
        </div>
    </form>

    @php $badge = ['pending'=>['#8A9696','In coda'],'processing'=>['#E28A53','In elaborazione'],'ready'=>['#3A8C89','Pronto'],'failed'=>['#A8521F','Fallito']]; @endphp
    @forelse($documents as $doc)
        <div style="display:flex; align-items:center; gap:12px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:8px;">
            <div style="flex:1;">
                <div style="font-weight:700; color:#1A1F1F;">{{ $doc->title }}
                    @if($doc->is_school_material)<span style="font-size:0.68rem; font-weight:700; color:#3A8C89; border:1px solid #3A8C89; border-radius:4px; padding:1px 7px; margin-left:6px;">di scuola</span>@endif
                </div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $doc->source_type }} · {{ $doc->subject->name ?? '—' }} · {{ $doc->is_school_material ? 'segreteria' : ($doc->teacher->name ?? 'docente') }}</div>
            </div>
            @php [$col,$lab] = $badge[$doc->status] ?? ['#8A9696',$doc->status]; @endphp
            <span style="font-size:0.72rem; font-weight:700; color:{{ $col }}; border:1px solid {{ $col }}; border-radius:4px; padding:2px 10px;">{{ $lab }}</span>
            <form method="POST" action="{{ route('scuola.materiali.destroy', $doc) }}" onsubmit="return confirm('Eliminare definitivamente questo materiale?');">
                @csrf @method('DELETE')
                <button type="submit" style="padding:6px 12px; background:white; color:#A8521F; border:1px solid #A8521F; border-radius:7px; font-size:0.78rem; font-weight:600; cursor:pointer;">Elimina</button>
            </form>
        </div>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessun materiale nella scuola.</p>
    @endforelse
</div>
@endsection
