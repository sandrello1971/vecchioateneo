@extends('layouts.student')
@section('title', 'Messaggi')
@section('breadcrumb', 'Classi / ' . $class->name . ' / Messaggi')
@section('content')
<div style="max-width:760px;" x-data="{showNew: {{ $conversations->isEmpty() ? 'true' : 'false' }}}">
    <div style="margin-bottom:8px;"><a href="{{ route('student.classes.show', $class) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; {{ $class->name }}</a></div>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; flex:1; margin:0;">Messaggi col docente</h1>
        @if($teachers->isNotEmpty())<button @click="showNew=!showNew" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">+ Nuovo</button>@endif
    </div>

    @if(session('success'))<div style="background:#E8F5F5; border-left:4px solid #55B1AE; color:#3A8C89; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if($errors->any())<div style="background:#FDECE2; border-left:4px solid #E28A53; color:#A8521F; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Nuovo messaggio (form inline) --}}
    <div x-show="showNew" x-cloak style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px; margin-bottom:16px;">
        @if($teachers->isEmpty())
            <p style="color:#8A9696; font-size:0.85rem; margin:0;">Nessun docente disponibile per questa classe.</p>
        @else
        <form method="POST" action="{{ route('student.classi.messaggi.store', $class) }}" style="display:flex; flex-direction:column; gap:10px;">
            @csrf
            <select name="teacher_id" required style="padding:9px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem;">
                <option value="">Scegli il docente…</option>
                @foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
            <input type="text" name="subject" required minlength="3" maxlength="200" placeholder="Oggetto" style="padding:9px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem;">
            <textarea name="body" required rows="4" maxlength="5000" placeholder="Scrivi al docente…" style="padding:10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.88rem; font-family:inherit;"></textarea>
            <div><button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Invia</button></div>
        </form>
        @endif
    </div>

    @forelse($conversations as $c)
        <a href="{{ route('student.classi.messaggi.show', [$class, $c]) }}" style="display:flex; align-items:center; gap:12px; background:white; border:1px solid #C8D0D0; border-radius:10px; padding:14px 18px; margin-bottom:8px; text-decoration:none;">
            <div style="flex:1;">
                <div style="font-weight:700; color:#1A1F1F;">{{ $c->teacher->name ?? '—' }}</div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $c->subject }} · {{ \Illuminate\Support\Str::limit($c->latest_body, 70) }}</div>
            </div>
            @if(($unread[$c->id] ?? 0) > 0)<span style="font-size:0.68rem; font-weight:700; color:#fff; background:#E28A53; border-radius:10px; padding:3px 10px;">{{ $unread[$c->id] }}</span>@endif
        </a>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessuna conversazione. Scrivi al tuo docente con "Nuovo".</p>
    @endforelse
</div>
@endsection
