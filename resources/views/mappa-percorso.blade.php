@extends('layouts.app')

@section('title', 'Mappa Percorso AI — Scarica la guida gratuita')
@section('description', 'Scarica la mappa che ti aiuta a scegliere il percorso AI più adatto alla tua azienda: PRIMUS, CONSILIUM o INITIUM. Aggiornata all\'EU AI Act.')

@section('content')

{{-- HERO --}}
<section style="background:linear-gradient(135deg,#E8F5F5 0%,white 60%);position:relative;overflow:hidden;" class="px-4 pt-12 pb-8">
    <div style="position:absolute;inset:0;background-image:url('/images/atheneum_new.png');background-size:contain;background-position:center right;background-repeat:no-repeat;opacity:0.18;z-index:0;" aria-hidden="true"></div>

    <div class="max-w-3xl mx-auto text-center" style="position:relative;z-index:1;">
        <span class="badge-orange mb-4 inline-block">Gratis &middot; 5 minuti di lettura &middot; AI Act ready</span>
        <h1 class="text-4xl sm:text-5xl font-bold mb-4" style="color:#1A1F1F;line-height:1.15;">
            Quale percorso AI è giusto<br>per la tua azienda?
        </h1>
        <p class="text-lg sm:text-xl mb-2" style="color:#4A5252;">
            Scarica la mappa gratuita per orientarti tra
            <strong style="color:#3A8C89;">PRIMUS</strong>,
            <strong style="color:#3A8C89;">CONSILIUM</strong> e
            <strong style="color:#3A8C89;">INITIUM</strong>
            — e capire da dove partire.
        </p>
    </div>
</section>

{{-- VALORE: 3 BULLET --}}
<section class="px-4 py-12">
    <div class="max-w-3xl mx-auto">
        <div class="grid sm:grid-cols-3 gap-6">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background:#E8F5F5;">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="#3A8C89" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <p class="text-sm" style="color:#1A1F1F;">
                    <strong>3 percorsi confrontati</strong><br>
                    <span style="color:#4A5252;">per profilo, obiettivi e tempi</span>
                </p>
            </div>
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background:#E8F5F5;">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="#3A8C89" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <p class="text-sm" style="color:#1A1F1F;">
                    <strong>Decision tree</strong><br>
                    <span style="color:#4A5252;">capisci dove posizionarti in 5 min</span>
                </p>
            </div>
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background:#E8F5F5;">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="#3A8C89" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <p class="text-sm" style="color:#1A1F1F;">
                    <strong>EU AI Act + L. 132/2025</strong><br>
                    <span style="color:#4A5252;">aggiornata alle norme vigenti</span>
                </p>
            </div>
        </div>
    </div>
</section>

{{-- FORM --}}
<section class="px-4 pb-20">
    <div class="max-w-xl mx-auto">
        @livewire('lead-magnet-form')
    </div>
</section>

{{-- TRUST/REASSURANCE --}}
<section class="px-4 pb-16">
    <div class="max-w-xl mx-auto text-center text-xs" style="color:#8A9696;">
        🔒 Trattiamo i tuoi dati secondo la nostra
        <a href="{{ route('privacy') }}" class="underline hover:text-teal-dark">privacy policy</a>.
        Niente spam, puoi cancellarti in qualsiasi momento.
    </div>
</section>

@endsection
