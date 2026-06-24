<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ atheneum_setting('instance_name', 'Atheneum') }} Admin</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Calibri', system-ui, sans-serif; }
        .sidebar { width:240px; min-height:100vh; background:#1A1F1F; position:fixed; left:0; top:0; bottom:0; overflow-y:auto; z-index:40; }
        .main-content { margin-left:240px; min-height:100vh; background:#F5F7F7; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 20px; color:#8A9696; font-size:0.85rem; transition:all 0.2s; border-radius:6px; margin:2px 8px; text-decoration:none; }
        .nav-item:hover, .nav-item.active { background:rgba(85,177,174,0.15); color:#55B1AE; }
    </style>
    @livewireStyles
    @stack('styles')
</head>
<body>
<aside class="sidebar">
    <div style="padding:20px; border-bottom:1px solid rgba(85,177,174,0.2);">
        <img src="{{ asset('images/logo.png') }}" alt="{{ atheneum_setting('instance_name', 'Atheneum') }}" style="height:64px; width:auto; display:block; margin-bottom:8px;">
        <div style="color:#55B1AE; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Admin Panel</div>
    </div>
    <nav style="padding:12px 0;">
        <a href="/admin" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">&#128202; Dashboard</a>
        <a href="/admin/students" class="nav-item {{ request()->routeIs('admin.students.*') ? 'active' : '' }}">&#128101; Discenti</a>
        <a href="{{ route('admin.instructors.index') }}" class="nav-item {{ request()->routeIs('admin.instructors.*') ? 'active' : '' }}">&#127979; Formatori</a>
        <a href="{{ route('admin.scuole.index') }}" class="nav-item {{ request()->routeIs('admin.scuole.*') ? 'active' : '' }}">&#127979; Scuole</a>
        <a href="/admin/certificates/signatures" class="nav-item {{ request()->routeIs('admin.certificates.signatures.*') ? 'active' : '' }}">&#9997; Firma Certificati</a>
        <a href="/admin/courses" class="nav-item {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}">&#128218; Corsi</a>
        <a href="{{ route('admin.freshness.proposals.index') }}" class="nav-item {{ request()->routeIs('admin.freshness.*') ? 'active' : '' }}">&#128260; Aggiornamenti corsi</a>
        @if(config('services.p26.enabled'))
        <a href="{{ route('admin.sources.index') }}" class="nav-item {{ request()->routeIs('admin.sources.*') ? 'active' : '' }}">&#128218; Fonti attendibili</a>
        <a href="{{ route('admin.coverage.index') }}" class="nav-item {{ request()->routeIs('admin.coverage.*') ? 'active' : '' }}">&#129517; Copertura corsi</a>
        @endif
        <a href="/admin/quizzes" class="nav-item {{ request()->routeIs('admin.quizzes.*') ? 'active' : '' }}">&#128221; Quiz</a>
        <a href="/admin/rag" class="nav-item {{ request()->routeIs('admin.rag.*') ? 'active' : '' }}">&#129504; Documenti AI</a>
        <a href="/admin/knowledge-base" class="nav-item {{ request()->routeIs('admin.knowledge-base.*') ? 'active' : '' }}">📓 Knowledge Base</a>
        <a href="/admin/analytics" class="nav-item {{ request()->routeIs('admin.analytics') ? 'active' : '' }}">&#128200; Analytics</a>
        <a href="{{ route('admin.admins.index') }}" class="nav-item {{ request()->routeIs('admin.admins.*') ? 'active' : '' }}">&#128737; Amministratori</a>
        <a href="{{ route('admin.security.2fa.show') }}" class="nav-item {{ request()->routeIs('admin.security.2fa.*') ? 'active' : '' }}">&#128274; Sicurezza 2FA</a>
        <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">&#9881; Impostazioni</a>
    </nav>
    <div style="position:absolute; bottom:0; left:0; right:0; padding:16px 20px; border-top:1px solid rgba(85,177,174,0.1);">
        <div style="color:#8A9696; font-size:0.75rem; margin-bottom:8px;">{{ session('admin_email') }}</div>
        <form method="POST" action="/admin/logout">
            @csrf
            <button type="submit" style="width:100%; padding:7px; background:rgba(226,138,83,0.1); color:#E28A53; border:1px solid rgba(226,138,83,0.3); border-radius:6px; font-size:0.8rem; cursor:pointer;">
                Esci
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:0.9rem; font-weight:600; color:#1A1F1F;">@yield('title', 'Dashboard')</div>
        <div style="font-size:0.8rem; color:#8A9696;">{{ atheneum_setting('instance_name', 'Atheneum') }} — Area Admin</div>
    </div>

    @if(session('success'))
    <div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        &#10003; {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="margin:16px 24px; padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; color:#c97a45; font-size:0.875rem;">
        &#9888; {{ session('error') }}
    </div>
    @endif

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
