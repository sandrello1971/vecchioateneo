<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Minerva — {{ $class->name }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        body { font-family: 'Calibri', system-ui, sans-serif; margin:0; background:#F5F7F7; color:#1A1F1F; }
        .wrap { max-width: 820px; margin: 0 auto; padding: 20px 16px 120px; }
        .topbar { background:#1A1F1F; color:#E8EDED; padding:12px 16px; display:flex; align-items:center; gap:12px; }
        .badge { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:#55B1AE; border:1px solid #55B1AE; border-radius:4px; padding:2px 8px; }
        .msg { margin-bottom:14px; }
        .bubble { padding:12px 14px; border-radius:12px; font-size:0.92rem; line-height:1.55; display:inline-block; max-width:90%; white-space:pre-wrap; }
        .user .bubble { background:#55B1AE; color:white; }
        .user { text-align:right; }
        .assistant .bubble { background:white; border:1px solid #C8D0D0; }
        .sources { margin-top:6px; font-size:0.75rem; color:#4A5252; }
        .sources a, .sources span.src { display:inline-block; margin:2px 6px 2px 0; padding:2px 8px; background:#E8F5F5; border:1px solid #C8E0E0; border-radius:10px; color:#3A8C89; text-decoration:none; }
        .searching { color:#E28A53; font-size:0.85rem; font-weight:600; display:flex; align-items:center; gap:8px; }
        .dot { width:9px;height:9px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite; }
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
        .composer { position:fixed; bottom:0; left:0; right:0; background:white; border-top:1px solid #C8D0D0; padding:12px 16px; }
        .composer-inner { max-width:820px; margin:0 auto; display:flex; gap:10px; }
        .composer textarea { flex:1; resize:none; padding:10px 12px; border:1px solid #C8D0D0; border-radius:10px; font-size:0.9rem; font-family:inherit; }
        .composer button { padding:0 20px; background:#55B1AE; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer; }
        .composer button:disabled { opacity:0.5; cursor:not-allowed; }
        .back { color:#8A9696; font-size:0.85rem; text-decoration:none; }
    </style>
</head>
<body x-data="classChat()">
    <div class="topbar">
        <a href="{{ $asDocente ? route('docente.classes.show', $class) : route('student.classes.index') }}" style="color:#8A9696; text-decoration:none;">&larr;</a>
        <strong>Minerva</strong>
        <span style="color:#8A9696; font-size:0.85rem;">{{ $class->name }}</span>
        <span class="badge">{{ $asDocente ? 'Docente' : 'Classe' }}</span>
    </div>

    <div class="wrap">
        <p style="font-size:0.8rem; color:#8A9696; margin-top:4px;">
            Minerva risponde <strong>solo</strong> in base ai materiali {{ $asDocente ? 'che hai caricato e pubblicato' : 'pubblicati dal tuo docente per questa classe' }}.
        </p>

        <div id="messages">
            <template x-for="(m, i) in messages" :key="i">
                <div class="msg" :class="m.role">
                    <div class="bubble" x-text="m.content"></div>
                    <template x-if="m.role === 'assistant' && m.sources && m.sources.length">
                        <div class="sources">
                            Fonti:
                            <template x-for="(s, j) in m.sources" :key="j">
                                <span>
                                    <template x-if="s.url">
                                        <a :href="s.url" target="_blank" rel="noopener"
                                           x-text="s.title + (s.timestamp ? ' [' + s.timestamp + ']' : '')"></a>
                                    </template>
                                    <template x-if="!s.url">
                                        <span class="src" x-text="s.title + (s.timestamp ? ' [' + s.timestamp + ']' : '')"></span>
                                    </template>
                                </span>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="loading">
                <div class="msg assistant">
                    <div class="searching"><span class="dot"></span> Minerva sta cercando nei materiali della classe…</div>
                </div>
            </template>
        </div>
    </div>

    <div class="composer">
        <div class="composer-inner">
            <textarea x-model="question" rows="1" placeholder="Fai una domanda sui materiali della classe…"
                      @keydown.enter.prevent="send()"></textarea>
            <button @click="send()" :disabled="loading || !question.trim()">Invia</button>
        </div>
    </div>

    <script>
    function classChat() {
        return {
            messages: @json($messages->map(fn ($m) => ['role' => $m->role, 'content' => $m->content, 'sources' => $m->context_documents ?? []])->values()),
            question: '',
            loading: false,
            schoolClassId: @json($class->id),
            async send() {
                const q = this.question.trim();
                if (!q || this.loading) return;
                this.messages.push({role: 'user', content: q, sources: []});
                this.question = '';
                this.loading = true;
                try {
                    const r = await fetch('{{ route('student.minerva.ask') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({question: q, school_class_id: this.schoolClassId}),
                    });
                    const d = await r.json();
                    this.messages.push({role: 'assistant', content: d.answer || 'Risposta non disponibile.', sources: d.sources || []});
                } catch (e) {
                    this.messages.push({role: 'assistant', content: 'Errore di rete. Riprova.', sources: []});
                } finally {
                    this.loading = false;
                    this.$nextTick(() => window.scrollTo(0, document.body.scrollHeight));
                }
            },
        };
    }
    </script>
</body>
</html>
