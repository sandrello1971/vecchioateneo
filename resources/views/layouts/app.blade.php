<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <title>@yield('title', atheneum_setting('instance_name', 'Atheneum')) — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <meta name="description" content="@yield('description', 'Officina The Glitch World: formazione AI certificata per PMI italiane. 4 corsi conformi EU AI Act Art. 4: INTERFERENZA, SEGNALE, CIRCUITO, FREQUENZA.')">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ atheneum_setting('instance_name', 'Atheneum') }}">
    <meta property="og:locale" content="it_IT">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('og_title', View::getSection('title') ?? 'Officina The Glitch World — Formazione AI per PMI')">
    <meta property="og:description" content="@yield('og_description', View::getSection('description') ?? 'Officina The Glitch World: formazione AI certificata per PMI italiane. 4 corsi conformi EU AI Act Art. 4: INTERFERENZA, SEGNALE, CIRCUITO, FREQUENZA.')">
    <meta property="og:image" content="@yield('og_image', url('/images/atheneum_new.png'))">
    <meta property="og:image:width" content="1536">
    <meta property="og:image:height" content="1024">
    <meta property="og:image:alt" content="Officina The Glitch World — Formazione AI per PMI">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', View::getSection('title') ?? 'Officina The Glitch World — Formazione AI per PMI')">
    <meta name="twitter:description" content="@yield('og_description', View::getSection('description') ?? 'Officina The Glitch World: formazione AI certificata per PMI italiane.')">
    <meta name="twitter:image" content="@yield('og_image', url('/images/atheneum_new.png'))">

    {{-- Meta extra per pagine che ne hanno bisogno (es. noindex sulle thank you) --}}
    @stack('meta')

    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preload" as="image" href="/images/logo.png">

    <!-- Schema.org -->
    @verbatim
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalOrganization",
      "name": "Officina The Glitch World",
      "url": "https://atheneum.noscite.it",
      "logo": "https://atheneum.noscite.it/images/logo.png",
      "description": "Divisione formativa di The Glitch World. Corsi certificati su AI, Second Brain e governance agenti AI per PMI italiane. Conformi EU AI Act.",
      "parentOrganization": {
        "@type": "Organization",
        "name": "Noscite S.r.l.s.",
        "url": "https://noscite.it"
      },
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Corsico",
        "addressRegion": "MI",
        "addressCountry": "IT"
      },
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+39-347-685-9801",
        "email": "info@noscite.it",
        "contactType": "customer service",
        "availableLanguage": "Italian"
      },
      "sameAs": [
        "https://www.facebook.com/Noscite",
        "https://www.linkedin.com/company/noscite",
        "https://www.instagram.com/noscite"
      ]
    }
    </script>
    @endverbatim

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: { DEFAULT: '#55B1AE', dark: '#3A8C89', light: '#E8F5F5' },
                        orange: { DEFAULT: '#E28A53' },
                        dark: { DEFAULT: '#1A1F1F' },
                        mid: { DEFAULT: '#4A5252' },
                        muted: { DEFAULT: '#8A9696' },
                    },
                    fontFamily: { sans: ['Calibri', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Calibri', system-ui, sans-serif; color: #1A1F1F; }
        .nav-link { color: #1A1F1F; transition: color 0.2s; }
        .nav-link:hover { color: #55B1AE; }
        .nav-link.active { color: #55B1AE; border-bottom: 2px solid #55B1AE; }
        .btn-primary { background: #55B1AE; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; transition: background 0.2s; display: inline-block; }
        .btn-primary:hover { background: #3A8C89; }
        .btn-orange { background: #E28A53; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; transition: background 0.2s; display: inline-block; }
        .btn-orange:hover { background: #c97a45; }
        .btn-outline { border: 2px solid #55B1AE; color: #55B1AE; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; transition: all 0.2s; display: inline-block; }
        .btn-outline:hover { background: #55B1AE; color: white; }
        .badge-teal { background: #E8F5F5; color: #3A8C89; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .badge-orange { background: #E28A53; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .corso-card { border: 1px solid #C8D0D0; border-radius: 12px; padding: 2rem; background: white; }
        .corso-card:hover { border-color: #55B1AE; box-shadow: 0 4px 24px rgba(85,177,174,0.12); }
    </style>
    @livewireStyles
</head>
<body class="bg-white">

<!-- TOP BAR CTA -->
<div style="background:#E28A53;padding:0.5rem 1rem;text-align:center;">
    <p class="text-sm" style="color:white;margin:0">
        Costruisci il piano formativo AI Act della tua PMI —
        <a href="/contatti" style="color:white;font-weight:700;text-decoration:underline;margin-left:4px">Parla con noi &rarr;</a>
    </p>
</div>

<!-- HEADER -->
<header class="fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-100 shadow-sm" style="top:36px">
    <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-16">
        <a href="/" class="flex items-center gap-3">
            <img src="/images/logo.png" alt="The Glitch World" class="h-10 w-auto" onerror="this.style.display='none'">
            <div>
                <div class="font-bold text-teal text-lg leading-none">OFFICINA</div>
                <div class="text-xs italic" style="color:#E28A53">Il Rumore Che Serve</div>
            </div>
        </a>
        <nav class="hidden md:flex items-center gap-6">
            <a href="/" class="nav-link text-sm font-medium {{ request()->is('/') ? 'active' : '' }}">Home</a>
            <a href="/primus" class="nav-link text-sm font-medium {{ request()->is('primus') ? 'active' : '' }}">RUMORE DI FONDO</a>
            <a href="/consilium" class="nav-link text-sm font-medium {{ request()->is('consilium') ? 'active' : '' }}">Interferenza</a>
            <a href="/initium" class="nav-link text-sm font-medium {{ request()->is('initium') ? 'active' : '' }}">Segnale</a>
            <a href="/structura" class="nav-link text-sm font-medium {{ request()->is('structura') ? 'active' : '' }}">Circuito</a>
            <a href="/ai-agents-mcp" class="nav-link text-sm font-medium {{ request()->is('ai-agents-mcp') ? 'active' : '' }}">Frequenza</a>
            <a href="/conformita-ai-act" class="nav-link text-sm font-medium {{ request()->is('conformita-ai-act') ? 'active' : '' }}">Conformità AI Act</a>
            <a href="/learn/login"
               style="display:flex;align-items:center;gap:6px;padding:6px 14px;border:1px solid #55B1AE;color:#55B1AE;border-radius:6px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.2s;"
               onmouseover="this.style.background='#55B1AE';this.style.color='white'"
               onmouseout="this.style.background='transparent';this.style.color='#55B1AE'">
                <span>&#127891;</span> Area studenti
            </a>
            <a href="/contatti" class="btn-primary text-sm">Contattaci</a>
        </nav>
        <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="md:hidden p-2" style="color:#55B1AE">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <div id="mobile-menu" class="hidden md:hidden border-t border-gray-100 bg-white">
        <div class="px-4 py-3 flex flex-col gap-3">
            <a href="/" class="nav-link text-sm font-medium py-1">Home</a>
            <a href="/primus" class="nav-link text-sm font-medium py-1">RUMORE DI FONDO</a>
            <a href="/consilium" class="nav-link text-sm font-medium py-1">Interferenza</a>
            <a href="/initium" class="nav-link text-sm font-medium py-1">Segnale</a>
            <a href="/structura" class="nav-link text-sm font-medium py-1">Circuito</a>
            <a href="/ai-agents-mcp" class="nav-link text-sm font-medium py-1">Frequenza</a>
            <a href="/conformita-ai-act" class="nav-link text-sm font-medium py-1">Conformità AI Act</a>
            <a href="/learn/login"
               style="display:flex;align-items:center;justify-content:center;gap:6px;padding:6px 14px;border:1px solid #55B1AE;color:#55B1AE;border-radius:6px;font-size:0.8rem;font-weight:600;text-decoration:none;"
               onmouseover="this.style.background='#55B1AE';this.style.color='white'"
               onmouseout="this.style.background='transparent';this.style.color='#55B1AE'">
                <span>&#127891;</span> Area studenti
            </a>
            <a href="/contatti" class="btn-primary text-sm text-center">Contattaci</a>
        </div>
    </div>
</header>

<main style="padding-top:100px">
    @yield('content')
</main>

<!-- FOOTER -->
<footer style="background:#1A1F1F; color:white;" class="mt-16">
    <div class="max-w-6xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-8">
        <div>
            <div class="font-bold text-xl mb-1" style="color:#55B1AE">OFFICINA THE GLITCH WORLD</div>
            <div class="text-sm italic mb-3" style="color:#E28A53">Il Rumore Che Serve</div>
            <p class="text-sm" style="color:#8A9696">Formazione AI per le PMI italiane. Metodo, pratica e cultura per un'innovazione sostenibile.</p>
        </div>
        <div>
            <div class="font-bold mb-3" style="color:#55B1AE">Corsi</div>
            <div class="flex flex-col gap-2 text-sm" style="color:#8A9696">
                <a href="/primus" class="hover:text-white transition-colors">Rumore di fondo — Prima di tutto il perche</a>
                <a href="/consilium" class="hover:text-white transition-colors">Interferenza — Strategia AI</a>
                <a href="/initium" class="hover:text-white transition-colors">Segnale — Fondamenta AI</a>
                <a href="/structura" class="hover:text-white transition-colors">Circuito — Second Brain</a>
                <a href="/ai-agents-mcp" class="hover:text-white transition-colors">Frequenza</a>
            </div>
        </div>
        <div>
            <div class="font-bold mb-3" style="color:#55B1AE">Contatti</div>
            <div class="flex flex-col gap-2 text-sm" style="color:#8A9696">
                <span>info@noscite.it</span>
                <span>+39 347 685 9801</span>
                <span>Corsico (MI)</span>
                <a href="https://noscite.it" class="hover:text-white transition-colors">noscite.it</a>
            </div>
            <div class="mt-4">
                <div class="text-xs font-bold uppercase mb-2" style="color:#55B1AE">Seguici</div>
                <div class="flex gap-3">
                    <a href="https://www.facebook.com/Noscite" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded flex items-center justify-center transition-colors" style="background:#4A5252" onmouseover="this.style.background='#55B1AE'" onmouseout="this.style.background='#4A5252'" aria-label="Facebook"><svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                    <a href="https://www.linkedin.com/company/noscite" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded flex items-center justify-center transition-colors" style="background:#4A5252" onmouseover="this.style.background='#55B1AE'" onmouseout="this.style.background='#4A5252'" aria-label="LinkedIn"><svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                    <a href="https://www.instagram.com/noscite" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded flex items-center justify-center transition-colors" style="background:#4A5252" onmouseover="this.style.background='#55B1AE'" onmouseout="this.style.background='#4A5252'" aria-label="Instagram"><svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
                </div>
            </div>
        </div>
    </div>
    <div class="border-t max-w-6xl mx-auto px-4 py-4 flex justify-between text-xs" style="border-color:#4A5252; color:#8A9696">
        <span>&copy; 2025 Noscite S.r.l.s. — Corsico (MI)</span>
        <div class="flex gap-4">
            <a href="/privacy-policy" class="hover:text-white transition-colors">Privacy Policy</a>
        </div>
    </div>
</footer>

<livewire:cookie-banner />

@livewireScripts
@stack('scripts')
</body>
</html>
