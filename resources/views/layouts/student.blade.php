<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', atheneum_setting('instance_name', 'Atheneum')) — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    {{-- Reverb real-time (Fase C messaggistica). Echo wrapper + Pusher protocol client.
         Init parametri da config.broadcasting.connections.reverb.options + key.
         CSRF token preso dal meta tag in head per il /broadcasting/auth POST. --}}
    @if(session('student_id'))
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ config('broadcasting.connections.reverb.key') }}',
            wsHost: '{{ config('broadcasting.connections.reverb.options.client.host', request()->getHost()) }}',
            wsPort: {{ (int) config('broadcasting.connections.reverb.options.client.port', 443) }},
            wssPort: {{ (int) config('broadcasting.connections.reverb.options.client.port', 443) }},
            forceTLS: ('{{ config('broadcasting.connections.reverb.options.client.scheme', 'https') }}' === 'https'),
            enabledTransports: ['ws', 'wss'],
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
            },
        });
        window.currentUserId = '{{ session('student_id') }}';
    </script>
    @endif
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Calibri', system-ui, sans-serif; }
        /* Sidebar: flexbox column. Header + user-card + nav scrollabili
           (overflow-y:auto sul .sidebar-scroll), footer logout fisso in
           basso (flex-shrink:0). Senza questo, con molte voci nav la
           nav esce dal viewport e il bottone "Esci" (in absolute) le copre. */
        .sidebar { width: 260px; height: 100vh; background: #1A1F1F; position: fixed; left: 0; top: 0; bottom: 0; z-index: 40; display: flex; flex-direction: column; }
        .sidebar-scroll { flex: 1; overflow-y: auto; min-height: 0; }
        .sidebar-footer { flex-shrink: 0; padding: 16px 20px; border-top: 1px solid rgba(85,177,174,0.1); background: #1A1F1F; }
        .main-content { margin-left: 260px; min-height: 100vh; background: #F5F7F7; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; color: #8A9696; font-size: 0.875rem; transition: all 0.2s; border-radius: 6px; margin: 2px 8px; text-decoration:none; }
        .nav-item:hover { background: rgba(85,177,174,0.1); color: #55B1AE; }
        .nav-item.active { background: rgba(85,177,174,0.15); color: #55B1AE; font-weight: 600; }
        .progress-bar { height: 6px; background: #C8D0D0; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: #55B1AE; border-radius: 3px; transition: width 0.3s; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: inline-flex !important; }
        }
    </style>
    @livewireStyles
    @stack('styles')
</head>
<body>

<aside class="sidebar">
    {{-- Scrollabile: prende tutta l'altezza disponibile meno il footer.
         Senza questo wrapper, con molte voci nav (es. instructor: KB +
         Documenti discenti) le ultime finiscono coperte dal bottone Esci. --}}
    <div class="sidebar-scroll">
    <div style="padding: 24px 20px; border-bottom: 1px solid rgba(85,177,174,0.2);">
        <img src="{{ asset('images/logo.png') }}" alt="{{ atheneum_setting('instance_name', 'Atheneum') }}" style="height:64px; width:auto; display:block; margin-bottom:8px;">
        <div style="color:#55B1AE; font-size:0.75rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;">{{ atheneum_setting('instance_name', 'Atheneum') }}</div>
        <div style="color:#8A9696; font-size:0.7rem; font-style:italic;">{{ atheneum_setting('platform_tagline', 'Il Rumore Che Serve') }}</div>
    </div>

    <div style="padding: 16px 20px; border-bottom: 1px solid rgba(85,177,174,0.1);">
        <div style="width:36px; height:36px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.875rem; margin-bottom:8px;">
            {{ strtoupper(substr(session('student_name', 'S'), 0, 1)) }}
        </div>
        <div style="color:#E8EDED; font-size:0.8rem; font-weight:600;">{{ session('student_name') }}</div>
        <div style="color:#8A9696; font-size:0.7rem;">{{ session('student_email') }}</div>
        @if($sidebarStudent && $sidebarStudent->is_demo)
        <div style="margin-top:6px; padding:3px 8px; background:rgba(226,138,83,0.2); border:1px solid #E28A53; border-radius:4px; display:inline-block;">
            <span style="color:#E28A53; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Versione Demo</span>
        </div>
        @endif
    </div>

    <nav style="padding: 12px 0;">
        <a href="/learn/dashboard" class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
            <span>&#9632;</span> Dashboard
        </a>

        @if($sidebarCourses->isNotEmpty())
            @foreach($sidebarCourses as $sidebarCourse)
            <a href="/learn/course/{{ $sidebarCourse->slug }}"
               class="nav-item {{ request()->is('learn/course/'.$sidebarCourse->slug.'*') ? 'active' : '' }}">
                <span>{{ $sidebarCourse->icon }}</span>
                <span>{{ $sidebarCourse->name }}</span>
                @if(($sidebarCourse->access_kind ?? 'enrolled') === 'teaching')
                <span style="margin-left:auto; font-size:0.6rem; font-weight:700;
                             color:#E28A53; text-transform:uppercase; letter-spacing:0.05em;">
                    insegni
                </span>
                @endif
            </a>
            @endforeach
        @endif

        <div style="margin: 16px 8px 4px; padding: 0 12px;">
            <div style="color:#4A5252; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Supporto</div>
        </div>
        @if(!empty($examLock))
        <a href="#"
           title="{{ atheneum_setting('assistant_name', 'Minerva') }} non è disponibile durante un esame"
           class="nav-item"
           style="opacity:0.4; cursor:not-allowed; pointer-events:none;">
            <span>&#10022;</span> Assistente AI
            <span style="margin-left:auto; font-size:0.6rem; color:#E28A53; text-transform:uppercase;">esame</span>
        </a>
        @else
        <a href="#" x-data @click.prevent="$dispatch('minerva-toggle')" class="nav-item">
            <span>&#10022;</span> Assistente AI
        </a>
        @endif

        <a href="{{ route('student.documents.index') }}"
           class="nav-item {{ request()->routeIs('student.documents.*') ? 'active' : '' }}">
            <span>📎</span> I miei documenti
        </a>

        <a href="{{ route('student.messages.index') }}"
           class="nav-item {{ request()->routeIs('student.messages.*') ? 'active' : '' }}">
            <span>✉️</span> Messaggi
            <span id="sidebar-unread-badge"
                  style="margin-left:auto; background:#E28A53; color:#FFF; font-size:0.65rem; font-weight:700; padding:1px 7px; border-radius:10px; min-width:18px; text-align:center; display:{{ !empty($unreadMessages) ? 'inline-block' : 'none' }};">{{ $unreadMessages ?? 0 }}</span>
        </a>

        <a href="{{ route('student.announcements.index') }}"
           class="nav-item {{ request()->routeIs('student.announcements.*') ? 'active' : '' }}">
            <span>📢</span> Annunci
            <span id="sidebar-announcements-badge"
                  style="margin-left:auto; background:#E28A53; color:#FFF; font-size:0.65rem; font-weight:700; padding:1px 7px; border-radius:10px; min-width:18px; text-align:center; display:{{ !empty($unreadAnnouncements) ? 'inline-block' : 'none' }};">{{ $unreadAnnouncements ?? 0 }}</span>
        </a>

        @php
            // Mostra "Impostazioni" a chiunque insegni almeno un corso (DB-based,
            // non role-based: copre il caso admin che insegna senza role=instructor)
            $isAnyCourseInstructor = $sidebarStudent
                && \DB::table('course_instructor')->where('instructor_id', $sidebarStudent->id)->exists();
        @endphp
        @if($isAnyCourseInstructor)
        <a href="{{ route('student.instructor_settings.index') }}"
           class="nav-item {{ request()->routeIs('student.instructor_settings.*') ? 'active' : '' }}">
            <span>⚙️</span> Impostazioni formatore
        </a>
        @endif

        @if($sidebarStudent && $sidebarStudent->isInstructor())
        <a href="{{ route('student.knowledge_base.index') }}"
           class="nav-item {{ request()->routeIs('student.knowledge_base.*') ? 'active' : '' }}">
            <span>📓</span> Knowledge Base
        </a>
        <a href="{{ route('student.instructor_documents.index') }}"
           class="nav-item {{ request()->routeIs('student.instructor_documents.*') ? 'active' : '' }}">
            <span>📂</span> Documenti discenti
        </a>
        @endif
    </nav>
    </div>{{-- /.sidebar-scroll --}}

    <div class="sidebar-footer">
        @if(($identity['professor'] ?? false) || ($identity['secretary'] ?? false))
        <div style="margin-bottom:8px;">
            <div style="color:#8A9696; font-size:0.65rem; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:4px;">Cambia contesto</div>
            @if($identity['professor'] ?? false)
                <a href="{{ route('docente.dashboard') }}" style="display:block; text-align:center; padding:7px; margin-bottom:4px; background:rgba(85,177,174,0.1); color:#55B1AE; border:1px solid rgba(85,177,174,0.3); border-radius:6px; font-size:0.78rem; text-decoration:none;">&#9788; Area docente</a>
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
        <div style="font-size:0.875rem; color:#8A9696;">
            @yield('breadcrumb', 'Dashboard')
        </div>
    </div>

    @if(session('success'))
    <div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        &#10003; {{ session('success') }}
    </div>
    @endif

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>

{{-- MINERVA BUBBLE — inibito server-side durante l'esame --}}
@if(empty($examLock))
<div x-data="minervaBubble()" x-init="init()"
     @minerva-toggle.window="toggle()"
     style="position:fixed; bottom:20px; right:20px; z-index:100;">

    <button x-show="!open" @click="toggle()"
            style="width:58px; height:58px; border-radius:50%; background:linear-gradient(135deg,#55B1AE,#3A8C89); color:white; border:none; cursor:pointer; box-shadow:0 4px 14px rgba(85,177,174,0.45); font-size:1.4rem; display:flex; align-items:center; justify-content:center;"
            title="Chiedi a {{ atheneum_setting('assistant_name', 'Minerva') }}">
        ✦
    </button>

    <div x-show="open" x-cloak x-transition class="minerva-panel">

        <div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); padding:14px 18px; display:flex; align-items:center; gap:10px;">
            <div style="width:34px; height:34px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:1rem;">✦</div>
            <div style="flex:1;">
                <div style="color:white; font-weight:700; font-size:0.9rem;">{{ atheneum_setting('assistant_name', 'Minerva') }}</div>
                <div style="color:rgba(255,255,255,0.7); font-size:0.7rem;">Assistente AI — {{ atheneum_setting('instance_name', 'Atheneum') }}</div>
            </div>
            <button @click="reset()" title="Nuova conversazione"
                    style="background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; font-size:0.9rem;">↺</button>
            <button @click="toggle()" title="Chiudi"
                    style="background:none; border:none; color:rgba(255,255,255,0.85); cursor:pointer; font-size:1.2rem; line-height:1;">×</button>
        </div>

        <div x-ref="msgs" class="minerva-msgs">
            <template x-if="messages.length === 0">
                <div style="padding:18px; background:white; border-radius:10px; color:#4A5252; font-size:0.85rem; line-height:1.6;">
                    @php $assistantName = atheneum_setting('assistant_name', 'Minerva'); $intro = atheneum_setting('assistant_intro_message', ''); @endphp
                    @if($intro)
                        {{ $intro }}
                    @else
                        Ciao! Sono <strong>{{ $assistantName }}</strong>. Fammi una domanda sui contenuti dei tuoi corsi.
                    @endif
                </div>
            </template>
            <template x-for="(msg, idx) in messages" :key="idx">
                <div>
                    <div x-show="msg.role === 'user'"
                         style="display:flex; justify-content:flex-end;">
                        <div style="max-width:85%; padding:10px 14px; background:#55B1AE; color:white; border-radius:12px 0 12px 12px; font-size:0.85rem; line-height:1.5;"
                             x-text="msg.content"></div>
                    </div>
                    <div x-show="msg.role === 'assistant'"
                         style="display:flex; gap:8px; align-items:flex-start;">
                        <div style="width:26px; height:26px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:0.7rem; flex-shrink:0;">✦</div>
                        <div style="max-width:85%; padding:10px 14px; background:white; color:#1A1F1F; border-radius:0 12px 12px 12px; font-size:0.85rem; line-height:1.6;">
                            <div class="minerva-md" x-html="renderMd(msg.content)"></div>
                            <button x-show="msg.mode === 'summary' && !msg.expanded"
                                    @click="expand(idx)"
                                    style="margin-top:10px; padding:5px 12px; background:#E8F5F5; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                                Vuoi maggiori info? →
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="typing" style="color:#8A9696; font-size:0.8rem; font-style:italic; padding:6px 10px;">{{ atheneum_setting('assistant_name', 'Minerva') }} sta pensando...</div>
        </div>

        <div style="background:white; border-top:1px solid #E8F5F5; padding:10px; display:flex; gap:8px;">
            <input type="text" x-model="draft" @keydown.enter="send()"
                   :disabled="typing"
                   placeholder="Scrivi una domanda..."
                   style="flex:1; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            <button @click="send()" :disabled="typing || !draft.trim()"
                    :style="typing || !draft.trim() ? 'opacity:0.5;cursor:not-allowed;' : 'cursor:pointer;'"
                    style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600;">
                Invia
            </button>
        </div>
    </div>
</div>
@endif

<style>
.minerva-md h1, .minerva-md h2, .minerva-md h3 { font-weight:700; color:#1A1F1F; margin:8px 0 4px; }
.minerva-md h2 { font-size:0.95rem; color:#3A8C89; }
.minerva-md h3 { font-size:0.88rem; }
.minerva-md p { margin:4px 0; }
.minerva-md ul, .minerva-md ol { margin:4px 0 4px 18px; }
.minerva-md li { margin:2px 0; }
.minerva-md strong { font-weight:700; color:#1A1F1F; }
.minerva-md em { font-style:italic; color:#4A5252; }
.minerva-md blockquote { margin:6px 0; padding:6px 10px; border-left:3px solid #55B1AE; background:#E8F5F5; color:#3A8C89; font-size:0.8rem; border-radius:0 6px 6px 0; }
.minerva-md code { background:#F5F7F7; padding:1px 5px; border-radius:3px; font-family:monospace; font-size:0.78rem; color:#E28A53; }
.minerva-panel {
    width: 380px;
    max-width: calc(100vw - 40px);
    height: 560px;
    max-height: calc(100vh - 80px);
    background: white;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.minerva-msgs {
    flex: 1 1 0 !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #F5F7F7;
}
</style>

<script>
function minervaBubble() {
    return {
        open: false,
        draft: '',
        messages: [],
        typing: false,

        init() {
            try {
                // Scoping per utente: localStorage è globale per origin,
                // se A esce e B entra sullo stesso browser, B erediterebbe
                // la chat di A. Confronto user-id corrente vs cached:
                // diverso → wipe + ri-set; uguale → restore come prima.
                const currentUserId = @json(session('student_id') ?? '');
                const cachedUserId = localStorage.getItem('minerva-user-id');
                if (cachedUserId !== currentUserId) {
                    localStorage.removeItem('minerva-chat');
                    localStorage.removeItem('minerva-open');
                    localStorage.setItem('minerva-user-id', currentUserId);
                    this.messages = [];
                    this.open = false;
                    return;
                }
                const saved = localStorage.getItem('minerva-chat');
                if (saved) this.messages = JSON.parse(saved);
                this.open = localStorage.getItem('minerva-open') === '1';
            } catch(e) {}
        },

        toggle() {
            this.open = !this.open;
            localStorage.setItem('minerva-open', this.open ? '1' : '0');
            if (this.open) this.$nextTick(() => this.scrollBottom());
        },

        reset() {
            this.messages = [];
            localStorage.removeItem('minerva-chat');
        },

        persist() {
            try { localStorage.setItem('minerva-chat', JSON.stringify(this.messages)); } catch(e) {}
        },

        scrollBottom() {
            if (this.$refs.msgs) this.$refs.msgs.scrollTop = this.$refs.msgs.scrollHeight;
        },

        renderMd(text) {
            if (!text) return '';
            try {
                const html = window.marked ? window.marked.parse(text) : text;
                return window.DOMPurify ? window.DOMPurify.sanitize(html) : html;
            } catch(e) {
                return text.replace(/[<>]/g, c => c === '<' ? '&lt;' : '&gt;');
            }
        },

        buildHistory() {
            return this.messages
                .filter(m => m.role === 'user' || (m.role === 'assistant' && !m.placeholder))
                .map(m => ({ role: m.role, content: m.content }));
        },

        async send() {
            const q = this.draft.trim();
            if (!q || this.typing) return;
            this.draft = '';
            this.messages.push({ role: 'user', content: q });
            this.typing = true;
            this.persist();
            this.$nextTick(() => this.scrollBottom());

            try {
                const res = await fetch('/learn/minerva/ask', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        question: q,
                        history: this.buildHistory().slice(-10, -1),
                        mode: 'summary',
                    }),
                });
                const data = await res.json();
                this.messages.push({ role: 'assistant', content: data.answer, mode: 'summary', expanded: false });
            } catch(e) {
                this.messages.push({ role: 'assistant', content: 'Errore di connessione. Riprova.' });
            }

            this.typing = false;
            this.persist();
            this.$nextTick(() => this.scrollBottom());
        },

        async expand(idx) {
            const msg = this.messages[idx];
            if (!msg || msg.mode !== 'summary' || msg.expanded || this.typing) return;

            const userQ = [...this.messages].slice(0, idx).reverse().find(m => m.role === 'user');
            if (!userQ) return;

            this.typing = true;
            try {
                const res = await fetch('/learn/minerva/ask', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        question: userQ.content,
                        history: this.buildHistory().slice(0, idx).slice(-10),
                        mode: 'expand',
                    }),
                });
                const data = await res.json();
                this.messages.push({ role: 'assistant', content: data.answer, mode: 'expand', expanded: true });
                this.messages[idx].expanded = true;
            } catch(e) {
                this.messages.push({ role: 'assistant', content: 'Errore nell\'approfondimento. Riprova.' });
            }
            this.typing = false;
            this.persist();
            this.$nextTick(() => this.scrollBottom());
        },
    };
}
</script>

@livewireScripts
@stack('scripts')

{{-- Sidebar live: badge unread + apparizione thread nuovi via Reverb.
     Subscriber al private channel user.{id}. Indipendente dal channel
     conversation.{id} che e' attivo solo nella vista show del thread. --}}
@if(session('student_id'))
<script>
(function() {
    if (!window.Echo) return;
    const userId = window.currentUserId;
    const badge = document.getElementById('sidebar-unread-badge');

    function bumpBadge(delta) {
        if (!badge) return;
        const current = parseInt(badge.textContent || '0', 10);
        const next = Math.max(0, current + delta);
        badge.textContent = next;
        badge.style.display = next > 0 ? 'inline-block' : 'none';
    }

    const annBadge = document.getElementById('sidebar-announcements-badge');
    function bumpAnnBadge(delta) {
        if (!annBadge) return;
        const current = parseInt(annBadge.textContent || '0', 10);
        const next = Math.max(0, current + delta);
        annBadge.textContent = next;
        annBadge.style.display = next > 0 ? 'inline-block' : 'none';
    }

    window.Echo.private(`user.${userId}`)
        .listen('.MessageSent', (payload) => {
            // Se siamo sulla pagina del thread relativo, lo show.blade gia gestisce.
            // Altrimenti bump badge sidebar.
            const onThisThread = window.location.pathname.endsWith('/messaggi/' + payload.conversation_id);
            if (!onThisThread) {
                bumpBadge(+1);
            }
        })
        .listen('.ConversationCreated', (payload) => {
            // Nuovo thread aperto verso questo utente: bump badge anche se inbox non aperta
            bumpBadge(+1);
        })
        .listen('.AnnouncementSent', (payload) => {
            // Nuovo annuncio: bump badge "Annunci" (skip se siamo gia' sulla pagina annunci)
            const onAnnouncements = window.location.pathname.startsWith('/learn/annunci');
            if (!onAnnouncements) {
                bumpAnnBadge(+1);
            }
        });
})();
</script>
@endif
</body>
</html>
