@extends('layouts.app')
@section('title', 'Cookie Policy')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-16">
    <h1 class="text-3xl font-bold mb-4" style="color:#1A1F1F">Cookie Policy</h1>
    <p class="text-sm mb-8" style="color:#8A9696">Ultimo aggiornamento: aprile 2025</p>

    <div style="color:#4A5252; line-height:1.8">
        <h2 class="text-xl font-bold mt-8 mb-3" style="color:#1A1F1F">1. Cosa sono i cookie</h2>
        <p>I cookie sono piccoli file di testo che i siti web visitati dall'utente inviano al suo terminale (computer, tablet, smartphone), dove vengono memorizzati per essere poi ritrasmessi agli stessi siti alla visita successiva.</p>

        <h2 class="text-xl font-bold mt-8 mb-3" style="color:#1A1F1F">2. Cookie utilizzati da questo sito</h2>
        <p class="mb-4">Il sito atheneum.noscite.it utilizza esclusivamente <strong>cookie tecnici necessari</strong> al funzionamento del sito:</p>
        <table class="w-full text-sm border-collapse mb-4">
            <thead>
                <tr style="background:#E8F5F5">
                    <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#1A1F1F">Nome</th>
                    <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#1A1F1F">Finalita</th>
                    <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#1A1F1F">Durata</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="p-3 border" style="border-color:#C8D0D0">atheneum_cookie_consent</td>
                    <td class="p-3 border" style="border-color:#C8D0D0">Memorizza la preferenza cookie dell'utente</td>
                    <td class="p-3 border" style="border-color:#C8D0D0">12 mesi</td>
                </tr>
                <tr style="background:#F5F7F7">
                    <td class="p-3 border" style="border-color:#C8D0D0">laravel_session</td>
                    <td class="p-3 border" style="border-color:#C8D0D0">Gestione sessione utente (tecnico)</td>
                    <td class="p-3 border" style="border-color:#C8D0D0">Sessione</td>
                </tr>
                <tr>
                    <td class="p-3 border" style="border-color:#C8D0D0">XSRF-TOKEN</td>
                    <td class="p-3 border" style="border-color:#C8D0D0">Protezione sicurezza form (tecnico)</td>
                    <td class="p-3 border" style="border-color:#C8D0D0">Sessione</td>
                </tr>
            </tbody>
        </table>
        <p>Non utilizziamo cookie di profilazione, di tracciamento o pubblicitari.</p>

        <h2 class="text-xl font-bold mt-8 mb-3" style="color:#1A1F1F">3. Cookie di terze parti</h2>
        <p>Il sito non utilizza cookie di terze parti a fini pubblicitari o di profilazione.</p>

        <h2 class="text-xl font-bold mt-8 mb-3" style="color:#1A1F1F">4. Gestione dei cookie</h2>
        <p>L'utente puo gestire le preferenze relative ai cookie attraverso il banner presente alla prima visita del sito, oppure tramite le impostazioni del proprio browser. La disabilitazione dei cookie tecnici potrebbe compromettere alcune funzionalita del sito.</p>

        <h2 class="text-xl font-bold mt-8 mb-3" style="color:#1A1F1F">5. Contatti</h2>
        <p>Per qualsiasi informazione: <a href="mailto:info@noscite.it" style="color:#55B1AE">info@noscite.it</a></p>
    </div>
</div>
@endsection
