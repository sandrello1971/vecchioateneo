@extends('layouts.app')
@section('title', 'PRIMUS — Prima di tutto il perche')
@section('description', 'PRIMUS — Il punto zero del percorso Noscite. 4 ore per imprenditori e dirigenti PMI. Mappa di Maturita Digitale personalizzata. Propedeutico a CONSILIUM e INITIUM.')

@section('content')
<!-- HERO -->
<section style="background:linear-gradient(135deg,#1A1F1F 0%,#3A8C89 100%);position:relative;overflow:hidden;color:white" class="py-20 px-4">
    <div style="position:absolute;inset:0;background-image:url('/images/atheneum_new.png');background-size:cover;background-position:center;opacity:0.15;z-index:0;" aria-hidden="true"></div>
    <div class="max-w-4xl mx-auto" style="position:relative;z-index:1">
        <span style="background:rgba(255,255,255,0.15);color:white;padding:0.4rem 1rem;border-radius:9999px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;display:inline-block" class="mb-4">Propedeutico &middot; 4 ore &middot; Nessun prerequisito</span>
        <h1 class="text-5xl md:text-6xl font-bold mb-2">PRIMUS</h1>
        <p class="text-xl font-semibold mb-6" style="color:#E28A53">Prima di tutto il perche</p>
        <p class="text-base max-w-2xl" style="color:rgba(255,255,255,0.9)">Il punto zero del percorso Noscite. Per imprenditori e dirigenti che vogliono capire se e perche l'AI riguarda davvero la loro PMI — prima di investire tempo, energia e denaro in formazione.</p>
    </div>
</section>

<!-- OBIETTIVI -->
<section class="py-16 px-4" style="background:white">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-2xl font-bold mb-8" style="color:#1A1F1F">Obiettivi di PRIMUS</h2>
        <div class="space-y-4">
            @foreach([
                "Capire cos'e l'AI generativa senza gergo tecnico, con esempi del proprio settore",
                "Misurare il divario tra la propria situazione e le PMI che gia usano l'AI",
                "Calcolare il costo mensile di non agire con i propri numeri reali",
                "Identificare 2-3 processi dove l'AI genera valore nei prossimi 90 giorni",
                "Ricevere la Mappa di Maturita Digitale personalizzata + percorso Noscite consigliato",
            ] as $obj)
            <div class="flex items-start gap-4 p-4 rounded-lg" style="background:#F5F7F7">
                <div style="color:#55B1AE;font-size:1.3rem;flex-shrink:0">&rarr;</div>
                <p style="color:#1A1F1F">{{ $obj }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- TARGET -->
<section class="py-12 px-4" style="background:#E8F5F5">
    <div class="max-w-4xl mx-auto">
        <div style="background:#F5F7F7; border-radius:12px; padding:20px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:12px;">👔 A chi è rivolto</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div style="display:flex; align-items:center; gap:8px; font-size:0.875rem; color:#4A5252;">✓ Imprenditori e titolari di PMI (5-50 dipendenti)</div>
                <div style="display:flex; align-items:center; gap:8px; font-size:0.875rem; color:#4A5252;">✓ Dirigenti che vogliono capire l'AI prima di investire</div>
                <div style="display:flex; align-items:center; gap:8px; font-size:0.875rem; color:#4A5252;">✓ Chi non ha esperienza AI pregressa</div>
                <div style="display:flex; align-items:center; gap:8px; font-size:0.875rem; color:#4A5252;">✓ Board aziendali che devono allinearsi sul perché</div>
            </div>
            <div style="margin-top:12px; padding:10px 14px; background:white; border-radius:8px; font-size:0.8rem; color:#8A9696;">
                ✦ Prerequisiti: <strong style="color:#55B1AE;">Nessuno.</strong> Accesso libero anche senza esperienza AI pregressa.
            </div>
        </div>
    </div>
</section>

<!-- 4 MODULI -->
<section class="py-16 px-4" style="background:white">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-2xl font-bold mb-8" style="color:#1A1F1F">I 4 moduli</h2>
        <div class="space-y-5">
            <div class="corso-card">
                <div class="flex items-center gap-3 mb-3">
                    <span style="background:#8A9696;color:white;width:2.5rem;height:2.5rem;border-radius:0.5rem;display:flex;align-items:center;justify-content:center;font-weight:700">M1</span>
                    <h3 class="text-xl font-bold" style="color:#1A1F1F">Il mondo che non aspetta</h3>
                    <span class="ml-auto text-sm" style="color:#8A9696">45'</span>
                </div>
                <p style="color:#4A5252">Fotografia della trasformazione AI nelle PMI italiane. Dati ISTAT 2025, casi reali, demo dal vivo. Non uno scenario astratto: cosa sta gia succedendo nel tuo settore.</p>
            </div>

            <div class="corso-card">
                <div class="flex items-center gap-3 mb-3">
                    <span style="background:#8A9696;color:white;width:2.5rem;height:2.5rem;border-radius:0.5rem;display:flex;align-items:center;justify-content:center;font-weight:700">M2</span>
                    <h3 class="text-xl font-bold" style="color:#1A1F1F">Il prezzo dell'invisibilita</h3>
                    <span class="ml-auto text-sm" style="color:#8A9696">60'</span>
                </div>
                <p style="color:#4A5252">Calcolo personalizzato del costo mensile di non agire. Canvas interattivo: ogni partecipante stima il tempo perso settimanalmente in attivita che l'AI potrebbe automatizzare. Numeri concreti, non ipotesi.</p>
            </div>

            <div class="corso-card">
                <div class="flex items-center gap-3 mb-3">
                    <span style="background:#8A9696;color:white;width:2.5rem;height:2.5rem;border-radius:0.5rem;display:flex;align-items:center;justify-content:center;font-weight:700">M3</span>
                    <h3 class="text-xl font-bold" style="color:#1A1F1F">La tua azienda nell'AI</h3>
                    <span class="ml-auto text-sm" style="color:#8A9696">60'</span>
                </div>
                <p style="color:#4A5252">Identificazione dei processi ad alto potenziale AI nei prossimi 90 giorni. Mini-assessment guidato per funzione aziendale (vendite, marketing, operations, amministrazione).</p>
            </div>

            <div class="corso-card">
                <div class="flex items-center gap-3 mb-3">
                    <span style="background:#8A9696;color:white;width:2.5rem;height:2.5rem;border-radius:0.5rem;display:flex;align-items:center;justify-content:center;font-weight:700">M4</span>
                    <h3 class="text-xl font-bold" style="color:#1A1F1F">La tua mappa e il tuo percorso</h3>
                    <span class="ml-auto text-sm" style="color:#8A9696">35'</span>
                </div>
                <p style="color:#4A5252">Consegna della Mappa di Maturita Digitale personalizzata. Presentazione del percorso Noscite consigliato: CONSILIUM (strategia), INITIUM (operativita) o entrambi.</p>
            </div>
        </div>
    </div>
</section>

<!-- OUTPUT FINALE -->
<section class="py-12 px-4">
    <div class="max-w-3xl mx-auto p-6 rounded-xl" style="background:linear-gradient(135deg,#E8F5F5,#55B1AE);color:white">
        <p class="text-xs font-bold uppercase mb-2" style="color:white;opacity:0.8;letter-spacing:0.1em">Al termine ricevi</p>
        <div class="flex flex-col gap-2 text-base font-semibold">
            <div class="flex items-center gap-2">&#10003; Mappa di Maturita Digitale personalizzata</div>
            <div class="flex items-center gap-2">&#10003; Percorso Noscite consigliato (CONSILIUM, INITIUM o entrambi)</div>
            <div class="flex items-center gap-2">&#10003; Attestato di partecipazione Noscite</div>
        </div>
    </div>
</section>

<!-- NOTE PRATICHE -->
<section class="py-12 px-4" style="background:#F5F7F7">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-xl font-bold mb-5" style="color:#1A1F1F">Note pratiche</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="p-4 rounded-lg" style="background:white;border:1px solid #C8D0D0">
                <p class="text-xs font-bold uppercase mb-1" style="color:#55B1AE">Eventi pubblici</p>
                <p style="color:#1A1F1F">Da 8 a 30 partecipanti</p>
            </div>
            <div class="p-4 rounded-lg" style="background:white;border:1px solid #C8D0D0">
                <p class="text-xs font-bold uppercase mb-1" style="color:#55B1AE">Sessioni aziendali dedicate</p>
                <p style="color:#1A1F1F">Da 4 a 12 partecipanti</p>
            </div>
            <div class="p-4 rounded-lg" style="background:white;border:1px solid #C8D0D0">
                <p class="text-xs font-bold uppercase mb-1" style="color:#55B1AE">Modalita</p>
                <p style="color:#1A1F1F">In presenza o online sincrono</p>
            </div>
            <div class="p-4 rounded-lg" style="background:white;border:1px solid #C8D0D0">
                <p class="text-xs font-bold uppercase mb-1" style="color:#55B1AE">Propedeutico a</p>
                <p style="color:#1A1F1F">CONSILIUM e INITIUM</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA FINALE -->
<section class="py-16 px-4 text-center" style="background:#55B1AE;color:white">
    <h2 class="text-3xl font-bold mb-4">Partecipa al prossimo PRIMUS</h2>
    <p class="mb-8 max-w-xl mx-auto" style="opacity:0.9">Scrivici per sapere le prossime date disponibili, oppure richiedi una sessione aziendale dedicata.</p>
    <a href="/contatti" class="btn-primary" style="background:white;color:#55B1AE">Contattaci &rarr;</a>
</section>
@endsection
