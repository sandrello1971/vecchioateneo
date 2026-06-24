@extends('layouts.app')
@section('title', '$(echo $page | sed "s/-/ /g" | sed "s/\b\(.\)/\u\1/g")')
@section('content')
<div class="py-20 text-center max-w-2xl mx-auto px-4">
    <h1 class="text-3xl font-bold mb-4" style="color:#1A1F1F">Pagina in costruzione</h1>
    <p style="color:#4A5252">Questa pagina sarà disponibile a breve.</p>
    <a href="/" class="btn-primary mt-8 inline-block">Torna alla home</a>
</div>
@endsection
