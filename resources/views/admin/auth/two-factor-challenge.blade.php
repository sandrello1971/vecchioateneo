<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verifica 2FA — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body style="background:#1A1F1F; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Calibri', system-ui, sans-serif; margin:0;">

<div style="background:white; border-radius:12px; padding:32px; max-width:420px; width:90%; box-shadow:0 4px 20px rgba(0,0,0,0.2);" x-data="{ recovery: false }">

    <div style="text-align:center; margin-bottom:24px;">
        <div style="font-size:2.5rem; margin-bottom:10px;">🔒</div>
        <h1 style="font-size:1.3rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Verifica 2FA</h1>
        <p style="font-size:0.85rem; color:#8A9696;" x-show="!recovery">
            Inserisci il codice 6 cifre dalla tua app authenticator.
        </p>
        <p style="font-size:0.85rem; color:#8A9696;" x-show="recovery" x-cloak>
            Inserisci uno dei tuoi recovery code (formato <code>XXXX-XXXX</code>).
        </p>
    </div>

    @if(session('error'))
    <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:10px 14px; border-radius:6px; margin-bottom:16px; color:#C52A2A; font-size:0.85rem;">
        {{ session('error') }}
    </div>
    @endif

    <form method="POST" action="{{ route('admin.2fa.verify') }}">
        @csrf
        <input type="hidden" name="recovery" :value="recovery ? '1' : ''">

        <div style="margin-bottom:18px;">
            <input type="text" name="code" required autocomplete="off" autofocus
                   :maxlength="recovery ? 9 : 6"
                   :placeholder="recovery ? 'XXXX-XXXX' : '000000'"
                   style="width:100%; padding:14px; border:1px solid #C8D0D0; border-radius:8px; font-family:monospace; font-size:1.8rem; text-align:center; letter-spacing:0.2em; box-sizing:border-box;">
        </div>

        <button type="submit" style="width:100%; padding:12px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.95rem; font-weight:700; cursor:pointer;">
            Verifica e accedi
        </button>

        <div style="text-align:center; margin-top:16px;">
            <button type="button" @click="recovery = !recovery; setTimeout(() => document.querySelector('input[name=code]').focus(), 10);"
                    style="background:none; border:none; color:#55B1AE; font-size:0.8rem; cursor:pointer; text-decoration:underline;">
                <span x-show="!recovery">Usa un recovery code</span>
                <span x-show="recovery" x-cloak>Torna al codice authenticator</span>
            </button>
        </div>
    </form>

    <div style="text-align:center; margin-top:24px; padding-top:18px; border-top:1px solid #F5F7F7;">
        <a href="{{ route('admin.login') }}" style="color:#8A9696; font-size:0.75rem; text-decoration:none;">← Torna al login</a>
    </div>
</div>

</body>
</html>
