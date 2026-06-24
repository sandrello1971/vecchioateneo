<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Imposta password — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body style="font-family:Calibri,system-ui,sans-serif;background:#1A1F1F;color:white;min-height:100vh;position:relative;overflow:hidden">

    <div style="position:relative;z-index:1" class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <img src="{{ asset('images/logo.png') }}" alt="{{ atheneum_setting('instance_name', 'Atheneum') }}" class="mb-4" style="height:130px; width:auto; display:block; margin-left:auto; margin-right:auto;">
                <h1 class="text-2xl font-bold" style="color:#55B1AE">Imposta la tua password</h1>
                <p class="text-sm mt-2" style="color:#8A9696">Primo accesso: scegli una password sicura di almeno 8 caratteri.</p>
            </div>

            @if($errors->any())
            <div class="mb-4 p-3 rounded-lg text-sm" style="background:rgba(255,100,100,0.1);border:1px solid rgba(255,100,100,0.3);color:#fca5a5">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
            @endif

            @if(session('success'))
            <div class="mb-4 p-3 rounded-lg text-sm" style="background:rgba(85,177,174,0.15);border:1px solid #55B1AE;color:#E8F5F5">
                {{ session('success') }}
            </div>
            @endif

            <form method="POST" action="{{ route('student.change-password.post') }}" class="rounded-2xl p-6 space-y-5" style="background:rgba(42,47,47,0.95);border:1px solid #3a3f3f;backdrop-filter:blur(10px)">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#ccc">Nuova password</label>
                    <input type="password" name="password" required minlength="8" autofocus class="w-full px-4 py-2.5 rounded-lg text-sm" style="background:#1A1F1F;border:1px solid #3a3f3f;color:white">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#ccc">Conferma password</label>
                    <input type="password" name="password_confirmation" required minlength="8" class="w-full px-4 py-2.5 rounded-lg text-sm" style="background:#1A1F1F;border:1px solid #3a3f3f;color:white">
                </div>
                <button type="submit" class="w-full px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors" style="background:#55B1AE;color:white" onmouseover="this.style.background='#3A8C89'" onmouseout="this.style.background='#55B1AE'">
                    Imposta password
                </button>
            </form>
        </div>
    </div>
</body>
</html>
