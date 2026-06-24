@extends('layouts.admin')
@section('title', 'Impostazioni')
@section('content')

<div style="max-width:800px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:20px;">Impostazioni piattaforma</h2>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fff3ec; border-left:4px solid #E28A53; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#c97a45; font-size:0.875rem;">
        ⚠ {{ session('error') }}
    </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')

        <div style="background:white; border-radius:10px; padding:20px; margin-bottom:20px;">
            <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:14px;">Branding e identità</h3>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Nome della piattaforma</label>
                    <input type="text" name="instance_name" value="{{ old('instance_name', $settings['instance_name']) }}" maxlength="120" placeholder="Officina"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                    <p style="font-size:0.7rem; color:#8A9696; margin-top:4px;">
                        Es. "Officina Acme S.r.l.". Usato in header, title, email.
                    </p>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Sottotitolo / motto</label>
                    <input type="text" name="platform_tagline" value="{{ old('platform_tagline', $settings['platform_tagline']) }}" maxlength="200" placeholder="Il Rumore Che Serve"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Organizzazione proprietaria</label>
                    <input type="text" name="platform_owner" value="{{ old('platform_owner', $settings['platform_owner']) }}" maxlength="120" placeholder="Noscite Srl"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">URL organizzazione (opzionale)</label>
                    <input type="url" name="platform_owner_url" value="{{ old('platform_owner_url', $settings['platform_owner_url']) }}" maxlength="255" placeholder="https://esempio.it"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Nome dell'assistente AI</label>
                    <input type="text" name="assistant_name" value="{{ old('assistant_name', $settings['assistant_name']) }}" maxlength="60" placeholder="Minerva"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Ruolo dell'assistente (frase)</label>
                    <input type="text" name="assistant_role_label" value="{{ old('assistant_role_label', $settings['assistant_role_label']) }}" maxlength="200" placeholder="l'assistente AI di formazione"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                </div>
                <div style="grid-column:1/3;">
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Messaggio di benvenuto in chat (opzionale)</label>
                    <textarea name="assistant_intro_message" maxlength="500" rows="2" placeholder="Ciao! Sono qui per aiutarti con i contenuti del corso."
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">{{ old('assistant_intro_message', $settings['assistant_intro_message']) }}</textarea>
                </div>

                <div style="grid-column:1/3;">
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Contesto di dominio della piattaforma (opzionale)</label>
                    <textarea name="assistant_domain_context" maxlength="1000" rows="3"
                              placeholder="Esempio: professionisti e PMI italiane che vogliono adottare strumenti di AI generativa in azienda"
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">{{ old('assistant_domain_context', $settings['assistant_domain_context'] ?? '') }}</textarea>
                    <p style="font-size:0.7rem; color:#8A9696; margin-top:4px; font-style:italic;">
                        Descrivi in linguaggio naturale il pubblico/dominio della piattaforma. Viene iniettato
                        nel system prompt dell'assistente per generare esempi pratici pertinenti al settore.
                        Lasciare vuoto per esempi generici. Max 1000 caratteri.
                    </p>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Email di contatto generale</label>
                    <input type="email" name="contact_email" maxlength="255"
                           value="{{ old('contact_email', $settings['contact_email'] ?? '') }}"
                           placeholder="info@noscite.it"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                    <p style="font-size:0.7rem; color:#8A9696; margin-top:4px; font-style:italic;">
                        Riceve i messaggi del form contatti pubblico e appare nei mailto del footer.
                        Vuoto = usa l'indirizzo SMTP From come fallback.
                    </p>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Email utente demo</label>
                    <input type="email" name="demo_user_email" maxlength="255"
                           value="{{ old('demo_user_email', $settings['demo_user_email'] ?? '') }}"
                           placeholder="demo@atheneum.noscite.it"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; margin-top:4px;">
                    <p style="font-size:0.7rem; color:#8A9696; margin-top:4px; font-style:italic;">
                        Email dell'account demo mostrato nelle istruzioni della pagina di login.
                        Vuoto = nessun account demo evidenziato (il pulsante "demo" resta funzionante col fallback).
                    </p>
                </div>
            </div>

            <p style="font-size:0.75rem; color:#8A9696; margin-top:12px; font-style:italic;">
                Lasciare un campo vuoto ripristina il valore di default.
                Il nome dell'assistente compare anche nel suo system prompt
                (la sua identità). Il comportamento (scope sui corsi, citazione
                fonti, rifiuto fuori-tema) resta in codice e non è
                personalizzabile, per garantire affidabilità.
            </p>
        </div>

        <div style="background:white; border-radius:10px; padding:20px; margin-bottom:20px;">
            <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:4px;">SMTP / Mail</h3>
            <p style="font-size:0.78rem; color:#8A9696; margin-bottom:14px;">
                Se questi campi sono vuoti, viene usata la configurazione del file <code style="background:#F5F7F7; padding:1px 5px; border-radius:3px;">.env</code> (comportamento attuale).
            </p>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:0.75rem; color:#8A9696;">Host SMTP</label>
                    <input type="text" name="mail_host" value="{{ old('mail_host', $settings['mail_host']) }}" placeholder="smtp.esempio.it"
                           style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#8A9696;">Porta</label>
                    <input type="number" name="mail_port" value="{{ old('mail_port', $settings['mail_port']) }}" min="1" max="65535" placeholder="587"
                           style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:0.75rem; color:#8A9696;">Username</label>
                    <input type="text" name="mail_username" value="{{ old('mail_username', $settings['mail_username']) }}"
                           style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#8A9696;">Crittografia</label>
                    <select name="mail_encryption"
                            style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                        @foreach(['tls','ssl','starttls','none'] as $opt)
                        <option value="{{ $opt }}" {{ (string) old('mail_encryption', $settings['mail_encryption']) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:0.75rem; color:#8A9696;">
                    Password
                    @if($settings['mail_password_set'])
                        <span style="color:#3A8C89; font-weight:600;">— attualmente impostata</span>
                    @endif
                </label>
                <input type="password" name="mail_password" placeholder="{{ $settings['mail_password_set'] ? '(lascia vuoto per mantenere quella esistente)' : 'inserisci la password' }}" maxlength="500"
                       style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                @if($settings['mail_password_set'])
                <label style="display:flex; align-items:center; gap:6px; margin-top:6px; font-size:0.75rem; color:#c97a45; cursor:pointer;">
                    <input type="checkbox" name="clear_mail_password" value="1">
                    Rimuovi password salvata (ritorna a .env)
                </label>
                @endif
                <p style="font-size:0.7rem; color:#8A9696; margin-top:4px;">
                    La password viene cifrata a riposo.
                </p>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:0.75rem; color:#8A9696;">From — email</label>
                    <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $settings['mail_from_address']) }}"
                           style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#8A9696;">From — nome</label>
                    <input type="text" name="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name']) }}" maxlength="120"
                           style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
                </div>
            </div>
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end; margin-bottom:24px;">
            <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Salva impostazioni
            </button>
        </div>
    </form>

    <div style="background:white; border-radius:10px; padding:20px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">Test mail</h3>
        <p style="font-size:0.78rem; color:#8A9696; margin-bottom:12px;">
            Invia una mail di prova all'email del tuo account admin loggato.
            Verifica la config (settings DB se valorizzata, altrimenti .env).
        </p>
        <form method="POST" action="{{ route('admin.settings.test-mail') }}">
            @csrf
            <button type="submit" style="padding:10px 20px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                Invia mail di prova
            </button>
        </form>
    </div>
</div>

@endsection
