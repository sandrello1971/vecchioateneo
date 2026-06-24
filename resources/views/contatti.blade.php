@extends('layouts.app')
@section('title', 'Contatti')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">
    <h1 class="text-3xl font-bold mb-4 text-center" style="color:#1A1F1F">Contattaci</h1>
    <p class="text-center mb-12" style="color:#4A5252">Scrivici per informazioni sui corsi, date disponibili e percorsi personalizzati. Ti risponderemo entro 24 ore.</p>

    <div class="grid md:grid-cols-2 gap-12">
        <div>
            <h2 class="text-xl font-bold mb-6" style="color:#1A1F1F">Contatti diretti</h2>
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#E8F5F5">
                        <svg class="w-5 h-5" style="color:#55B1AE" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase" style="color:#55B1AE">Email</div>
                        <div style="color:#1A1F1F">info@noscite.it</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#E8F5F5">
                        <svg class="w-5 h-5" style="color:#55B1AE" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase" style="color:#55B1AE">Telefono</div>
                        <div style="color:#1A1F1F">+39 347 685 9801</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#E8F5F5">
                        <svg class="w-5 h-5" style="color:#55B1AE" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase" style="color:#55B1AE">Sede</div>
                        <div style="color:#1A1F1F">Corsico (MI)</div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            @if(request('msg'))
            <div style="padding:12px 16px; background:rgba(226,138,83,0.08);
                        border:1px solid rgba(226,138,83,0.3); border-radius:8px;
                        margin-bottom:16px; font-size:0.85rem; color:#5A6464;">
                📋 Hai cliccato <strong>"Richiedi info"</strong>. Il tuo messaggio è già pre-compilato —
                aggiungi i tuoi dettagli e invialo.
            </div>
            @endif

            <form action="/contatti" method="POST" class="flex flex-col gap-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#1A1F1F">Nome *</label>
                    <input type="text" name="name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal" style="border-color:#C8D0D0">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#1A1F1F">Email *</label>
                    <input type="email" name="email" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal" style="border-color:#C8D0D0">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#1A1F1F">Azienda</label>
                    <input type="text" name="company" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none" style="border-color:#C8D0D0">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#1A1F1F">Corso di interesse</label>
                    <select name="course" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none" style="border-color:#C8D0D0">
                        <option value="">— Seleziona —</option>
                        <option>CONSILIUM — Strategia AI per PMI</option>
                        <option>INITIUM — Fondamenta AI Operativa</option>
                        <option>STRUCTURA — Second Brain Aziendale</option>
                        <option>AI AGENTS & MCP — Agenti AI in Azienda</option>
                        <option>Percorso personalizzato</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#1A1F1F">Messaggio *</label>
                    <textarea name="message" rows="4" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none" style="border-color:#C8D0D0">{{ old('message', request('msg')) }}</textarea>
                </div>
                <div class="flex items-start gap-2">
                    <input type="checkbox" name="privacy" required class="mt-1">
                    <label class="text-xs" style="color:#4A5252">Accetto la <a href="/privacy-policy" style="color:#55B1AE">Privacy Policy</a> e il trattamento dei dati personali ai sensi del GDPR.</label>
                </div>
                <button type="submit" class="btn-primary text-center">Invia richiesta</button>
            </form>
        </div>
    </div>
</div>
@endsection
