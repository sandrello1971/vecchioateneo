@extends('layouts.admin')
@section('title', 'Configurazione 2FA')
@section('content')

<div style="max-width:700px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <a href="{{ route('admin.security.2fa.show') }}" style="color:#8A9696; text-decoration:none; font-size:0.875rem;">← Sicurezza 2FA</a>
        <span style="color:#C8D0D0;">/</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Configura 2FA — Step 1 di 2</h2>
    </div>

    @if($errors->any() || session('error'))
    <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#C52A2A; font-size:0.875rem;">
        {{ session('error') ?? $errors->first() }}
    </div>
    @endif

    <div style="background:white; border-radius:10px; padding:24px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">1. Scansiona il QR code</h3>
        <p style="font-size:0.875rem; color:#4A5252; margin-bottom:14px;">
            Apri Google Authenticator (o Microsoft Authenticator / Authy / 1Password) sul telefono,
            premi <strong>+</strong> e scansiona questo QR code.
        </p>
        <div style="display:flex; justify-content:center; padding:18px; background:#FAFBFB; border-radius:8px; margin-bottom:20px;">
            <div style="width:280px; height:280px;">{!! $qrSvg !!}</div>
        </div>

        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">In alternativa — inserimento manuale</h3>
        <p style="font-size:0.85rem; color:#4A5252; margin-bottom:8px;">Se non puoi scansionare, inserisci nell'app:</p>
        <ul style="font-size:0.85rem; color:#4A5252; line-height:1.8; margin:0 0 20px 18px; list-style:disc;">
            <li><strong>Account</strong>: {{ $admin->email }}</li>
            <li><strong>Chiave</strong>: <code style="background:#F5F7F7; padding:2px 8px; border-radius:4px; font-family:monospace; font-size:0.85rem;">{{ $secret }}</code></li>
            <li><strong>Tipo</strong>: TOTP (time-based)</li>
        </ul>

        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:8px; padding-top:14px; border-top:1px solid #F5F7F7;">2. Verifica</h3>
        <p style="font-size:0.85rem; color:#4A5252; margin-bottom:12px;">Inserisci il codice 6 cifre dall'app per confermare l'attivazione:</p>
        <form method="POST" action="{{ route('admin.security.2fa.confirm') }}" style="display:flex; gap:10px; align-items:center;">
            @csrf
            <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" required autocomplete="off" autofocus
                   placeholder="000000"
                   style="width:180px; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-family:monospace; font-size:1.5rem; text-align:center; letter-spacing:0.25em;">
            <button type="submit" style="padding:11px 22px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Verifica e attiva
            </button>
        </form>
    </div>
</div>

@endsection
