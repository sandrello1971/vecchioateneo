@extends('layouts.app')
@section('title', 'CONSILIUM — Strategia AI per PMI')
@section('description', 'CONSILIUM — Laboratorio direzionale 7 ore per CEO e board PMI. Strategia AI, AI Usage Policy e roadmap 90 giorni. Certified AI Strategist. Conforme EU AI Act.')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">
    <span class="badge-teal mb-4 inline-block">Per Board &middot; Direzione &middot; Imprenditori</span>
    <h1 class="text-4xl font-bold mb-2" style="color:#1A1F1F">CONSILIUM — Strategia AI per PMI</h1>
    <p class="text-lg mb-6" style="color:#4A5252">Laboratorio direzionale di 7 ore. Formato 25% teoria, 75% lavoro laboratoriale su canvas.</p>
    <div class="flex flex-wrap gap-3 mb-8">
        <span class="badge-orange">7 ore &middot; 1 giornata</span>
        <span class="badge-teal">Certified AI Strategist</span>
    </div>

    <div class="corso-card mb-8">
        <h2 class="text-xl font-bold mb-4" style="color:#1A1F1F">Moduli</h2>
        <div class="grid md:grid-cols-2 gap-2">
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M1</strong> Scenario AI per PMI — opportunita, rischi e casi d'uso <span style="color:#8A9696">(1h 30')</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M2</strong> Mappatura processi e identificazione casi d'uso AI <span style="color:#8A9696">(2h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M3</strong> Selezione 3 progetti prioritari e definizione owner <span style="color:#8A9696">(1h 30')</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M4</strong> AI Usage Policy essenziale e Roadmap 90 giorni <span style="color:#8A9696">(2h)</span></div>
        </div>
    </div>

    <!-- FUNDAMENTA -->
    <div class="py-8 px-6 rounded-xl mb-8" style="background:#E8F5F5">
        <p class="text-xs font-bold uppercase mb-4 tracking-widest" style="color:#8A9696">Radicato nell'Umanesimo Digitale Noscite</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">La persona al centro</div>
                    <p class="text-xs" style="color:#4A5252">Ogni deliverable del corso e progettato per restare nelle mani delle persone, non dei consulenti.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Comprensione prima dell'azione</div>
                    <p class="text-xs" style="color:#4A5252">Prima mappiamo, poi decidiamo. Nessuna roadmap calata dall'alto senza aver capito i processi reali.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Innovazione concreta</div>
                    <p class="text-xs" style="color:#4A5252">Non vendiamo visioni. Ogni modulo produce almeno un deliverable operativo che l'azienda usa dal giorno dopo.</p>
                </div>
            </div>
        </div>
    </div>

    <a href="/contatti" class="btn-orange">Richiedi informazioni su CONSILIUM</a>
</div>
@endsection
