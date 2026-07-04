@extends('layouts.scuola')
@section('title', 'Carica materiale di scuola')
@section('content')
<div style="max-width:760px;">
    <div style="margin-bottom:8px;"><a href="{{ route('scuola.materiali.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Materiali della scuola</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Carica materiale di scuola</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:18px;">Il materiale finisce in Biblioteca ed è utilizzabile da tutti i docenti della scuola per creare lezioni.</p>

    <x-material-upload-form :subjects="$subjects" :video-ai-dpa-missing="$videoAiDpaMissing" :external-types="$externalTypes" :action="route('scuola.materiali.store')" />
</div>
@endsection
