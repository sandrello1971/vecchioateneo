<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Unisciti a una classe — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body style="font-family:Calibri,system-ui,sans-serif; background:#1A1F1F; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;">
    <div style="width:100%; max-width:440px; background:white; border-radius:14px; padding:32px;">
        <div style="text-align:center; margin-bottom:24px;">
            <img src="{{ asset('images/logo.png') }}" alt="{{ atheneum_setting('instance_name', 'Atheneum') }}" style="height:72px; width:auto; display:block; margin:0 auto 10px;">
            <h1 style="font-size:1.2rem; font-weight:700; color:#1A1F1F;">Unisciti a una classe</h1>
            <p style="color:#8A9696; font-size:0.85rem;">
                @if($loggedIn) Inserisci il codice invito ricevuto dal docente. @else Inserisci il codice e crea il tuo account studente. @endif
            </p>
        </div>

        @if ($errors->any())
            <div style="background:#FDECE2; border:1px solid #E28A53; color:#A8521F; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:0.82rem;">
                <ul style="margin:0 0 0 18px; padding:0;">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.classes.join.store') }}">
            @csrf
            <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Codice invito *</label>
            <input type="text" name="invite_code" value="{{ old('invite_code') }}" required autofocus
                   style="width:100%; padding:11px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:1.1rem; letter-spacing:0.18em; text-transform:uppercase; text-align:center; margin-bottom:16px;">

            @unless($loggedIn)
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Nome e cognome *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:12px;">

                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Email *</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:12px;">

                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Data di nascita *</label>
                <input type="date" name="birth_date" value="{{ old('birth_date') }}" required
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:12px;">

                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Password *</label>
                <input type="password" name="password" required
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:12px;">

                <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Conferma password *</label>
                <input type="password" name="password_confirmation" required
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:16px;">
            @endunless

            <button type="submit" style="width:100%; padding:12px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.95rem; font-weight:700; cursor:pointer;">
                {{ $loggedIn ? 'Unisciti' : 'Crea account e unisciti' }}
            </button>
        </form>

        <div style="text-align:center; margin-top:18px; font-size:0.82rem;">
            @if($loggedIn)
                <a href="{{ route('student.classes.index') }}" style="color:#55B1AE; text-decoration:none;">Le mie classi</a>
            @else
                <a href="{{ route('student.login') }}" style="color:#55B1AE; text-decoration:none;">Hai già un account? Accedi</a>
            @endif
        </div>
    </div>
</body>
</html>
