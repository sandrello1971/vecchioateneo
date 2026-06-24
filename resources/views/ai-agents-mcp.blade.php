@extends('layouts.app')
@section('title', 'AI AGENTS & MCP — Agenti AI in Azienda')
@section('description', 'AI AGENTS & MCP — Governance degli agenti AI per PMI. Livello 1 asincrono + workshop in presenza. Protocollo MCP, canvas architetto agente. Certified AI Agent Governance Practitioner.')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">
    <span class="badge-teal mb-4 inline-block">Per Manager &middot; PM &middot; Responsabili Innovazione &middot; IT</span>
    <h1 class="text-4xl font-bold mb-2" style="color:#1A1F1F">AI AGENTS & MCP — Agenti AI in Azienda</h1>
    <p class="text-lg mb-2" style="color:#4A5252">L'unico corso del portfolio dedicato alla governance degli agenti AI e del protocollo MCP.</p>
    <p class="text-sm mb-6 italic" style="color:#E28A53">Prerequisito: INITIUM o equivalente &middot; Max 12 partecipanti L2</p>
    <div class="flex flex-wrap gap-3 mb-8">
        <span class="badge-orange">~9 ore &middot; L1 asincrono + L2 workshop</span>
        <span class="badge-teal">Certified AI Agent Governance Practitioner</span>
    </div>

    <div class="corso-card mb-8">
        <h2 class="text-xl font-bold mb-4" style="color:#1A1F1F">Struttura</h2>
        <div class="grid md:grid-cols-2 gap-2">
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>L1</strong> Asincrono ~3h: paradigma agenti, MCP, casi PMI, governance</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>L2 A</strong> Fondamenta MCP e demo live MCPHub <span style="color:#8A9696">(2h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>L2 B</strong> Canvas Architetto dell'Agente su caso reale <span style="color:#8A9696">(2h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>L2 C+D</strong> Demo produzione + Piano d'Azione 90 giorni <span style="color:#8A9696">(2h)</span></div>
        </div>
    </div>

    <div class="py-8 px-6 rounded-xl mb-8" style="background:#E8F5F5">
        <p class="text-xs font-bold uppercase mb-4 tracking-widest" style="color:#8A9696">Radicato nell'Umanesimo Digitale Noscite</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Governance prima dell'automazione</div>
                    <p class="text-xs" style="color:#4A5252">Un agente AI senza governance e un rischio. Prima costruiamo le regole, poi attiviamo l'automazione.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Trasparenza dei processi</div>
                    <p class="text-xs" style="color:#4A5252">Ogni agente viene progettato con il Canvas Architetto: input, output, permessi e limiti sono documentati.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Controllo umano garantito</div>
                    <p class="text-xs" style="color:#4A5252">Gli agenti operano sotto supervisione umana definita. Nessuna delega cieca: il team decide cosa automatizzare e cosa no.</p>
                </div>
            </div>
        </div>
    </div>

    <a href="/contatti" class="btn-orange">Richiedi informazioni su AI AGENTS & MCP</a>
</div>
@endsection
