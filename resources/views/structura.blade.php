@extends('layouts.app')
@section('title', 'STRUCTURA — Second Brain Aziendale')
@section('description', 'STRUCTURA — 24 ore per costruire il Second Brain aziendale con Obsidian. Knowledge management AI-driven per PMI. Certified Second Brain Implementer.')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">
    <span class="badge-teal mb-4 inline-block">Per Manager &middot; Knowledge Worker &middot; PM</span>
    <h1 class="text-4xl font-bold mb-2" style="color:#1A1F1F">STRUCTURA — Second Brain Aziendale</h1>
    <p class="text-lg mb-2" style="color:#4A5252">Percorso avanzato per implementare sistemi di knowledge management con approccio AI-driven.</p>
    <p class="text-sm mb-6 italic" style="color:#E28A53">Prerequisito consigliato: INITIUM</p>
    <div class="flex flex-wrap gap-3 mb-8">
        <span class="badge-orange">24 ore &middot; 6 moduli</span>
        <span class="badge-teal">Certified Second Brain Implementer</span>
    </div>

    <div class="corso-card mb-8">
        <h2 class="text-xl font-bold mb-4" style="color:#1A1F1F">Moduli</h2>
        <div class="grid md:grid-cols-2 gap-2">
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M1</strong> Metodo CODE e fondamenti del Second Brain <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M2</strong> Setup Obsidian e Vault Aziendale <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M3</strong> Template e Organizzazione Avanzata <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M4</strong> AI e Automazioni nel Vault <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M5</strong> Collaborazione e Governance del Vault <span style="color:#8A9696">(4h)</span></div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M6</strong> Certificazione e Piano d'Azione <span style="color:#8A9696">(4h)</span></div>
        </div>
    </div>

    <div class="py-8 px-6 rounded-xl mb-8" style="background:#E8F5F5">
        <p class="text-xs font-bold uppercase mb-4 tracking-widest" style="color:#8A9696">Radicato nell'Umanesimo Digitale Noscite</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Conoscenza condivisa</div>
                    <p class="text-xs" style="color:#4A5252">Il vault non e di un consulente: e dell'azienda. Costruiamo perche il team lo gestisca in autonomia.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Struttura prima dell'automazione</div>
                    <p class="text-xs" style="color:#4A5252">Prima organizziamo l'informazione, poi automatizziamo. Un vault disordinato con l'AI diventa caos veloce.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Documentazione audit-ready</div>
                    <p class="text-xs" style="color:#4A5252">Ogni output e documentato e tracciabile. Playbook, governance, roadmap: pronti per audit e certificazioni.</p>
                </div>
            </div>
        </div>
    </div>

    <a href="/contatti" class="btn-orange">Richiedi informazioni su STRUCTURA</a>
</div>
@endsection
