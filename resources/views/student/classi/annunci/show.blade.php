@extends('layouts.student')
@section('title', $announcement->subject)
@section('breadcrumb', 'Classi / ' . $class->name . ' / Annunci')
@section('content')
<div style="max-width:720px;">
    <div style="margin-bottom:8px;"><a href="{{ route('student.classi.annunci.index', $class) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Annunci</a></div>
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        <h1 style="font-size:1.3rem; font-weight:700; color:#1A1F1F; margin:0 0 4px;">&#128226; {{ $announcement->subject }}</h1>
        <div style="font-size:0.8rem; color:#8A9696; margin-bottom:14px;">{{ $announcement->teacher->name ?? '' }} · {{ $announcement->created_at?->format('d/m/Y H:i') }}</div>
        <div style="font-size:0.92rem; line-height:1.6; color:#1A1F1F; white-space:pre-wrap;">{{ $announcement->body }}</div>
    </div>
</div>
@endsection
