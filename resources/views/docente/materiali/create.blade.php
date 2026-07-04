@extends('layouts.docente')
@section('title', 'Nuovo materiale')
@section('breadcrumb', 'Materiali / Nuovo')
@section('content')
<div style="max-width:760px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.materials.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Materiali</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:18px;">Nuovo materiale</h1>

    <x-material-upload-form :subjects="$subjects" :video-ai-dpa-missing="$videoAiDpaMissing" :external-types="$externalTypes" />
</div>
@endsection
