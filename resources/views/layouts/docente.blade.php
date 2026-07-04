<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Area docente') — {{ $branding->instanceName() }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <style>
        body { font-family: 'Calibri', system-ui, sans-serif; }
        .sidebar { width: 260px; height: 100vh; background: #1A1F1F; position: fixed; left: 0; top: 0; bottom: 0; z-index: 40; display: flex; flex-direction: column; }
        .sidebar-scroll { flex: 1; overflow-y: auto; min-height: 0; }
        .sidebar-footer { flex-shrink: 0; padding: 16px 20px; border-top: 1px solid rgba(85,177,174,0.1); background: #1A1F1F; }
        .main-content { margin-left: 260px; min-height: 100vh; background: #F5F7F7; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; color: #8A9696; font-size: 0.875rem; transition: all 0.2s; border-radius: 6px; margin: 2px 8px; text-decoration:none; }
        .nav-item:hover { background: rgba(85,177,174,0.1); color: #55B1AE; }
        .nav-item.active { background: rgba(85,177,174,0.15); color: #55B1AE; font-weight: 600; }
        /* Voce non ancora implementata: visibile ma non cliccabile */
        .nav-item.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: inline-flex !important; }
        }
    </style>
    <style>
        /* Feedback UX: spinner per le operazioni async (vedi CLAUDE.md). */
        @keyframes nosc-spin { to { transform: rotate(360deg); } }
        .nosc-spin { display:inline-block; width:13px; height:13px; border:2px solid rgba(255,255,255,0.5); border-top-color:#fff; border-radius:50%; animation: nosc-spin 0.7s linear infinite; vertical-align:-2px; margin-right:6px; }
        button[disabled] { opacity:0.65; cursor:progress; }
    </style>
    @stack('styles')
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-scroll">
    <div style="padding: 24px 20px; border-bottom: 1px solid rgba(85,177,174,0.2);">
        <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->ownerLabel() }}" style="height:36px; filter:brightness(0) invert(1); margin-bottom:8px;">
        <div style="color:#55B1AE; font-size:0.75rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;">{{ $branding->instanceName() }}</div>
        <div style="color:#8A9696; font-size:0.7rem; font-style:italic;">Area docente</div>
    </div>

    <div style="padding: 16px 20px; border-bottom: 1px solid rgba(85,177,174,0.1);">
        <div style="width:36px; height:36px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.875rem; margin-bottom:8px;">
            {{ strtoupper(substr(session('student_name', 'D'), 0, 1)) }}
        </div>
        <div style="color:#E8EDED; font-size:0.8rem; font-weight:600;">{{ session('student_name') }}</div>
        <div style="color:#8A9696; font-size:0.7rem;">{{ session('student_email') }}</div>
        <div style="margin-top:6px; padding:3px 8px; background:rgba(85,177,174,0.15); border:1px solid #55B1AE; border-radius:4px; display:inline-block;">
            <span style="color:#55B1AE; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Docente</span>
        </div>
    </div>

    <nav style="padding: 12px 0;">
        <a href="{{ route('docente.dashboard') }}"
           class="nav-item {{ request()->routeIs('docente.dashboard') ? 'active' : '' }}">
            <span>&#9632;</span> Dashboard
        </a>
        <a href="{{ route('docente.classes.index') }}"
           class="nav-item {{ request()->routeIs('docente.classes.*') ? 'active' : '' }}">
            <span>&#128218;</span> Classi
        </a>
        <a href="{{ route('docente.topics.index') }}"
           class="nav-item {{ request()->routeIs('docente.topics.*') || request()->routeIs('docente.lessons.*') ? 'active' : '' }}">
            <span>&#128214;</span> Argomenti
        </a>
        <a href="{{ route('docente.materials.index') }}"
           class="nav-item {{ request()->routeIs('docente.materials.*') && !request()->routeIs('docente.materials.shared.*') ? 'active' : '' }}">
            <span>&#128196;</span> Materiali
        </a>
        <a href="{{ route('docente.materials.shared.index') }}"
           class="nav-item {{ request()->routeIs('docente.materials.shared.*') ? 'active' : '' }}">
            <span>&#128101;</span> Materiali condivisi
        </a>
        {{-- Voce del prossimo pacchetto: visibile ma disabilitata --}}
        <a href="{{ route('docente.biblioteca.index') }}"
           class="nav-item {{ request()->routeIs('docente.biblioteca.*') ? 'active' : '' }}">
            <span>&#127963;</span> Biblioteca
        </a>
    </nav>
    </div>{{-- /.sidebar-scroll --}}

    <div class="sidebar-footer">
        @if(($identity['courses'] ?? false) || ($identity['secretary'] ?? false))
        <div style="margin-bottom:8px;">
            <div style="color:#8A9696; font-size:0.65rem; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:4px;">Cambia contesto</div>
            @if($identity['courses'] ?? false)
                <a href="{{ route('student.dashboard') }}" style="display:block; text-align:center; padding:7px; margin-bottom:4px; background:rgba(85,177,174,0.1); color:#55B1AE; border:1px solid rgba(85,177,174,0.3); border-radius:6px; font-size:0.78rem; text-decoration:none;">&#128218; I miei corsi</a>
            @endif
            @if($identity['secretary'] ?? false)
                <a href="{{ route('scuola.dashboard') }}" style="display:block; text-align:center; padding:7px; background:rgba(85,177,174,0.1); color:#55B1AE; border:1px solid rgba(85,177,174,0.3); border-radius:6px; font-size:0.78rem; text-decoration:none;">&#128188; Segreteria</a>
            @endif
        </div>
        @endif
        <form method="POST" action="/learn/logout">
            @csrf
            <button type="submit" style="width:100%; padding:8px; background:rgba(226,138,83,0.1); color:#E28A53; border:1px solid rgba(226,138,83,0.3); border-radius:6px; font-size:0.8rem; cursor:pointer;">
                Esci
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; gap:12px;">
        <button onclick="document.querySelector('.sidebar').classList.toggle('open')" class="mobile-toggle" style="display:none; background:none; border:none; cursor:pointer; color:#55B1AE; font-size:1.2rem;">&#9776;</button>
        <div style="font-size:0.875rem; color:#8A9696;">@yield('breadcrumb', 'Area docente')</div>
    </div>

    @if(session('success'))
    <div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        &#10003; {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="margin:16px 24px; padding:12px 16px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.875rem;">
        {{ session('error') }}
    </div>
    @endif
    @if(session('warning'))
    <div style="margin:16px 24px; padding:12px 16px; background:#FBF3E2; border-left:4px solid #E2A653; border-radius:6px; color:#9A7B2E; font-size:0.875rem;">
        &#9888; {{ session('warning') }}
    </div>
    @endif

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>
<script>
// Feedback UX globale (CLAUDE.md "Feedback UX — NON negoziabile"):
// ogni form con data-async, al submit, disabilita il bottone, mostra lo
// spinner con l'etichetta "in corso" e previene il doppio submit.
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-async')) return;
    if (form.dataset.submitting === '1') { e.preventDefault(); return; }
    form.dataset.submitting = '1';
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) {
        const label = btn.getAttribute('data-busy-label') || 'Attendere…';
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="nosc-spin"></span>' + label;
        // Disabilita dopo il tick così il form invia regolarmente i suoi dati.
        setTimeout(function () { btn.disabled = true; }, 0);
    }
}, true);
</script>
@stack('scripts')
</body>
</html>
