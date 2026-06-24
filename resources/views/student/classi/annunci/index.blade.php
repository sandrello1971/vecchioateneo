@extends('layouts.student')
@section('title', 'Annunci')
@section('breadcrumb', 'Classi / ' . $class->name . ' / Annunci')
@section('content')
<div style="max-width:760px;">
    <div style="margin-bottom:8px;"><a href="{{ route('student.classes.show', $class) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; {{ $class->name }}</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Annunci · {{ $class->name }}</h1>

    @forelse($announcements as $a)
        <a href="{{ route('student.classi.annunci.show', [$class, $a]) }}" style="display:flex; align-items:center; gap:12px; background:white; border:1px solid {{ $a->is_read ? '#C8D0D0' : '#55B1AE' }}; border-radius:10px; padding:14px 18px; margin-bottom:8px; text-decoration:none;">
            <div style="flex:1;">
                <div style="font-weight:700; color:#1A1F1F;">&#128226; {{ $a->subject }}</div>
                <div style="font-size:0.8rem; color:#8A9696;">{{ $a->teacher->name ?? '' }} · {{ $a->created_at?->format('d/m/Y') }} · {{ \Illuminate\Support\Str::limit(strip_tags($a->body), 70) }}</div>
            </div>
            @unless($a->is_read)<span style="font-size:0.68rem; font-weight:700; color:#fff; background:#55B1AE; border-radius:10px; padding:3px 10px;">NUOVO</span>@endunless
        </a>
    @empty
        <p style="color:#8A9696; font-size:0.9rem;">Nessun annuncio dal tuo docente.</p>
    @endforelse
</div>
@endsection
