@extends('layouts.app')

@section('title', 'Grazie — Mappa in arrivo via email')
@section('description', 'Grazie per aver scaricato la Mappa Percorso AI. Controlla la tua casella email.')

{{-- Push noindex meta nello head: la thank you NON deve essere indicizzata
     (URL che chiunque può visitare bypassando il funnel altererebbe i dati). --}}
@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')

<section class="px-4 py-20 sm:py-28" style="background:linear-gradient(135deg,#E8F5F5 0%,white 60%);">
    <div class="max-w-2xl mx-auto text-center">

        {{-- Icona conferma --}}
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-6"
             style="background:#E8F5F5;">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="#3A8C89" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>

        <h1 class="text-3xl sm:text-4xl font-bold mb-4" style="color:#1A1F1F;line-height:1.2;">
            Grazie! Controlla la tua email.
        </h1>

        <p class="text-base sm:text-lg mb-10" style="color:#4A5252;">
            Ti abbiamo appena inviato la <strong>Mappa Percorso AI</strong> in PDF.
            Se non la trovi nella casella principale, dai un'occhiata in
            <strong>spam</strong> o <strong>promozioni</strong>.
        </p>

        {{-- Card CTA secondaria al contatto --}}
        <div class="bg-white rounded-2xl shadow-lg p-8 sm:p-10 border border-gray-100 text-left">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-full"
                     style="background:#FDEEE3;">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="#E28A53" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold mb-1" style="color:#1A1F1F;">
                        Vuoi capire quale percorso fa per te?
                    </h2>
                    <p class="text-sm" style="color:#4A5252;">
                        Prenota una chiamata di 20 minuti — nessun impegno, solo per orientarti.
                    </p>
                </div>
            </div>

            <a href="{{ route('contatti') }}" class="btn-primary w-full text-center block">
                Parliamone insieme &rarr;
            </a>
        </div>

        {{-- Link sussidiari --}}
        <div class="mt-10 text-sm" style="color:#8A9696;">
            <p class="mb-2">Nel frattempo, dai un'occhiata ai percorsi:</p>
            <div class="flex flex-wrap justify-center gap-x-4 gap-y-2">
                <a href="{{ route('primus') }}" class="hover:text-teal-dark underline">PRIMUS</a>
                <a href="{{ route('consilium') }}" class="hover:text-teal-dark underline">CONSILIUM</a>
                <a href="{{ route('initium') }}" class="hover:text-teal-dark underline">INITIUM</a>
                <a href="{{ route('structura') }}" class="hover:text-teal-dark underline">STRUCTURA</a>
                <a href="{{ route('ai-agents-mcp') }}" class="hover:text-teal-dark underline">AI Agents & MCP</a>
            </div>
        </div>

    </div>
</section>

@endsection
