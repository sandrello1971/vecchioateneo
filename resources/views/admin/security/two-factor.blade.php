@extends('layouts.admin')
@section('title', 'Autenticazione a 2 fattori')
@section('content')

<div style="max-width:800px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">Autenticazione a 2 fattori (2FA)</h2>
    <p style="font-size:0.875rem; color:#8A9696; margin-bottom:24px;">
        Aggiungi un livello di sicurezza al tuo account admin richiedendo un codice
        temporaneo dalla tua app authenticator (Google Authenticator / Microsoft Authenticator
        / Authy / 1Password) oltre alla password.
    </p>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#C52A2A; font-size:0.875rem;">
        {{ session('error') }}
    </div>
    @endif

    <div style="background:white; border-radius:10px; padding:24px;">
        @if($enabled)
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <span style="background:rgba(85,177,174,0.15); color:#3A8C89; padding:4px 12px; border-radius:12px; font-size:0.75rem; font-weight:700;">✓ 2FA ATTIVO</span>
                <span style="color:#8A9696; font-size:0.8rem;">Attivato il {{ $admin->two_factor_confirmed_at->format('d/m/Y H:i') }}</span>
            </div>
            <p style="color:#4A5252; font-size:0.875rem; margin-bottom:24px;">
                Al prossimo login ti sarà richiesto il codice 6 cifre dalla tua app authenticator.
            </p>

            <div style="border-top:1px solid #F5F7F7; padding-top:20px; margin-bottom:24px;">
                <h3 style="font-size:0.95rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">Disattiva 2FA</h3>
                <p style="font-size:0.8rem; color:#8A9696; margin-bottom:12px;">Inserisci un codice corrente per confermare la disattivazione (anti-takeover).</p>
                <form method="POST" action="{{ route('admin.security.2fa.disable') }}" style="display:flex; gap:10px; align-items:flex-start;">
                    @csrf
                    <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" required autocomplete="off"
                           placeholder="000000"
                           style="width:140px; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-family:monospace; font-size:1rem; text-align:center; letter-spacing:0.2em;">
                    <button type="submit" style="padding:8px 16px; background:white; border:1px solid #C52A2A; color:#C52A2A; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        Disattiva 2FA
                    </button>
                </form>
            </div>

            <div style="border-top:1px solid #F5F7F7; padding-top:20px;">
                <h3 style="font-size:0.95rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">Recovery codes</h3>
                <p style="font-size:0.8rem; color:#8A9696; margin-bottom:12px;">
                    Se perdi accesso alla tua app authenticator, puoi usare uno dei codici di recupero generati all'attivazione.
                    Rigenerali se sospetti che siano compromessi.
                </p>
                <form method="POST" action="{{ route('admin.security.2fa.recovery.regenerate') }}"
                      onsubmit="return confirm('Rigenerare i recovery codes invaliderà quelli precedenti. Continuare?');">
                    @csrf
                    <button type="submit" style="padding:8px 14px; background:white; border:1px solid #C8D0D0; color:#4A5252; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        Rigenera recovery codes
                    </button>
                </form>
            </div>
        @else
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <span style="background:rgba(226,138,83,0.15); color:#D87840; padding:4px 12px; border-radius:12px; font-size:0.75rem; font-weight:700;">⚠ 2FA NON ATTIVO</span>
            </div>
            <p style="color:#4A5252; font-size:0.875rem; margin-bottom:24px;">
                Il tuo account è protetto solo dalla password. Attivare 2FA aggiunge un codice
                temporaneo dalla tua app authenticator al login, proteggendo da phishing e
                furto credenziali.
            </p>
            <form method="POST" action="{{ route('admin.security.2fa.enable') }}">
                @csrf
                <button type="submit" style="padding:10px 22px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Attiva 2FA
                </button>
            </form>
        @endif
    </div>
</div>

@endsection
