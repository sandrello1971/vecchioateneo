<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
</head>
<body style="background:#1A1F1F; min-height:100vh; display:flex; align-items:center; justify-content:center;">
    <div style="background:#252B2B; border-radius:12px; padding:40px; width:100%; max-width:400px; border:1px solid rgba(85,177,174,0.2);">
        <div style="text-align:center; margin-bottom:32px;">
            <img src="{{ asset('images/logo.png') }}" alt="{{ atheneum_setting('instance_name', 'Atheneum') }}" style="height:120px; width:auto; display:block; margin:0 auto 16px;">
            <div style="color:#55B1AE; font-weight:700; font-size:1.1rem;">{{ atheneum_setting('instance_name', 'Atheneum') }} Admin</div>
            <div style="color:#8A9696; font-size:0.8rem;">Accesso riservato</div>
        </div>

        @if(session('error'))
        <div style="background:rgba(226,82,82,0.12); border:1px solid rgba(226,82,82,0.5); border-radius:8px; padding:12px; margin-bottom:20px; color:#fca5a5; font-size:0.875rem;">
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div style="background:rgba(226,138,83,0.15); border:1px solid #E28A53; border-radius:8px; padding:12px; margin-bottom:20px; color:#E28A53; font-size:0.875rem;">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="/admin/login">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="color:#8A9696; font-size:0.8rem; display:block; margin-bottom:6px;">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       style="width:100%; padding:10px 14px; background:#1A1F1F; border:1px solid rgba(85,177,174,0.3); border-radius:8px; color:#E8EDED; font-size:0.875rem; outline:none;">
            </div>
            <div style="margin-bottom:24px;">
                <label style="color:#8A9696; font-size:0.8rem; display:block; margin-bottom:6px;">Password</label>
                <input type="password" name="password" required
                       style="width:100%; padding:10px 14px; background:#1A1F1F; border:1px solid rgba(85,177,174,0.3); border-radius:8px; color:#E8EDED; font-size:0.875rem; outline:none;">
            </div>
            <button type="submit" style="width:100%; padding:12px; background:#55B1AE; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.9rem;">
                Accedi
            </button>
        </form>
    </div>
</body>
</html>
