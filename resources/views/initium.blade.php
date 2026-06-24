@extends('layouts.app')
@section('title', 'INITIUM — Fondamenta AI Operativa')
@section('description', 'INITIUM — 20 ore di formazione AI operativa per manager e team. ChatGPT, Claude, Copilot 365, prompt engineering, data governance. Certified AI Productivity User. EU AI Act Art. 4.')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">
    <span class="badge-teal mb-4 inline-block">Per Manager &middot; Professionisti &middot; Team operativi</span>
    <h1 class="text-4xl font-bold mb-2" style="color:#1A1F1F">INITIUM — Fondamenta AI Operativa</h1>
    <p class="text-lg mb-6" style="color:#4A5252">Il punto di partenza per chi vuole capire davvero l'AI generativa. Formato 70% pratico, 30% teoria.</p>
    <div class="flex flex-wrap gap-3 mb-8">
        <span class="badge-orange">20h + 3h esame</span>
        <span class="badge-teal">Certified AI Productivity User</span>
        <span class="badge-teal">Compliance AI Act Art. 4</span>
    </div>

    <div class="corso-card mb-8">
        <h2 class="text-xl font-bold mb-4" style="color:#1A1F1F">Moduli</h2>
        <div class="grid md:grid-cols-2 gap-2">
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M1</strong> Capire l'AI — logica, dati e limiti <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M2</strong> Prompt Engineering e Perplexity in Azione <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M3</strong> Claude e ChatGPT — analisi, contenuti e automazioni <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M4</strong> Vibe Coding e Microsoft Copilot 365 <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M5</strong> Second Brain, Data Governance e Private AI <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#fff3ec; border:1px solid #E28A53"><strong>Esame</strong> Certified AI Productivity User — soglia 70/100 <span style="color:#8A9696">(3h)</span></div>
        </div>
    </div>

    <div class="py-8 px-6 rounded-xl mb-8" style="background:#E8F5F5">
        <p class="text-xs font-bold uppercase mb-4 tracking-widest" style="color:#8A9696">Radicato nell'Umanesimo Digitale Noscite</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Competenza, non dipendenza</div>
                    <p class="text-xs" style="color:#4A5252">L'obiettivo e che il team governi gli strumenti AI, non che ne dipenda. Autonomia operativa dal giorno dopo.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Pratica prima della teoria</div>
                    <p class="text-xs" style="color:#4A5252">Ogni modulo include esercitazioni su casi reali. Il 70% del tempo e lavoro pratico sugli strumenti.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Governance integrata</div>
                    <p class="text-xs" style="color:#4A5252">Data governance e Private AI non sono un modulo a parte: sono il modo in cui insegniamo a usare ogni strumento.</p>
                </div>
            </div>
        </div>
    </div>

    <a href="/contatti" class="btn-orange">Richiedi informazioni su INITIUM</a>
</div>
@endsection
