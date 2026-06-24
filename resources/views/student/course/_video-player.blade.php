@php
    $vpId = $videoId ?? null;
    $vpScope = $scope ?? 'module';
    $vpCourseSlug = $courseSlug ?? ($course->slug ?? '');
    $vpModuleId = $moduleId ?? null;
    $vpLabel = $label ?? null;
@endphp

@if($vpId)
<div style="background:white; border-radius:12px; overflow:hidden; margin-bottom:20px;"
     x-data="videoPlayer('{{ $vpId }}', '{{ $vpScope }}', '{{ $vpCourseSlug }}', {{ $vpModuleId ? "'" . $vpModuleId . "'" : 'null' }})" x-init="init()">

    @if($vpLabel)
    <div style="padding:10px 16px; background:#E8F5F5; color:#3A8C89; font-weight:700; font-size:0.8rem; border-bottom:1px solid #C8D0D0;">
        {{ $vpLabel }}
    </div>
    @endif

    <div style="position:relative; background:#000;">
        <video x-ref="videoEl"
               style="width:100%; max-height:400px; display:block;"
               controls
               preload="metadata">
            <source src="/learn/video/{{ $vpId }}/stream" type="video/mp4">
        </video>

        <div x-show="status !== 'ready'" x-cloak
             style="position:absolute; inset:0; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; flex-direction:column; gap:12px;">
            <div style="color:white; font-size:0.9rem; font-weight:600;">
                🎬 Video in elaborazione...
            </div>
            <div style="width:200px; height:4px; background:rgba(255,255,255,0.2); border-radius:2px;">
                <div :style="'width:' + progress + '%; height:100%; background:#55B1AE; border-radius:2px; transition:width 0.5s;'"></div>
            </div>
            <div style="color:#8A9696; font-size:0.8rem;" x-text="progress + '% — ' + stepLabel"></div>
        </div>
    </div>

    <div style="border-bottom:1px solid #E8F5F5; display:flex;">
        <button @click="activeTab='transcript'"
                :style="activeTab==='transcript' ? 'border-bottom:2px solid #55B1AE;color:#55B1AE;' : 'color:#8A9696;'"
                style="padding:12px 20px; border:none; background:none; font-size:0.85rem; font-weight:600; cursor:pointer;">
            📝 Trascrizione
        </button>
        <button @click="activeTab='chat'"
                :style="activeTab==='chat' ? 'border-bottom:2px solid #55B1AE;color:#55B1AE;' : 'color:#8A9696;'"
                style="padding:12px 20px; border:none; background:none; font-size:0.85rem; font-weight:600; cursor:pointer;">
            ✦ Chiedi al video
        </button>
        <button @click="activeTab='search'"
                :style="activeTab==='search' ? 'border-bottom:2px solid #55B1AE;color:#55B1AE;' : 'color:#8A9696;'"
                style="padding:12px 20px; border:none; background:none; font-size:0.85rem; font-weight:600; cursor:pointer;">
            🔎 Cerca nel video
        </button>
    </div>

    <div x-show="activeTab==='transcript'" style="max-height:250px; overflow-y:auto; padding:16px;">
        <template x-if="transcript.length === 0">
            <p style="color:#8A9696; font-size:0.85rem; text-align:center; padding:20px;">
                Trascrizione non ancora disponibile.
            </p>
        </template>
        <template x-for="seg in transcript" :key="seg.start">
            <div @click="seekTo(seg.start)"
                 style="display:flex; gap:12px; padding:6px 8px; border-radius:6px; cursor:pointer; margin-bottom:4px;"
                 onmouseover="this.style.background='#F5F7F7'" onmouseout="this.style.background='transparent'">
                <span style="color:#55B1AE; font-size:0.75rem; font-family:monospace; flex-shrink:0; padding-top:2px;"
                      x-text="seg.timestamp_str"></span>
                <span style="font-size:0.85rem; color:#4A5252; line-height:1.5;" x-text="seg.text"></span>
            </div>
        </template>
    </div>

    <div x-show="activeTab==='chat'" style="padding:16px;">
        <div x-ref="chatMessages" style="max-height:200px; overflow-y:auto; margin-bottom:12px; display:flex; flex-direction:column; gap:8px;">
            <template x-if="videoMessages.length === 0">
                <p style="color:#8A9696; font-size:0.8rem; text-align:center; padding:12px;">
                    Fai una domanda sul contenuto del video. Rispondo con i timestamp esatti.
                </p>
            </template>
            <template x-for="msg in videoMessages" :key="msg.id">
                <div :style="msg.role === 'user' ? 'text-align:right' : 'text-align:left'">
                    <div :style="msg.role === 'user'
                        ? 'display:inline-block;background:#55B1AE;color:white;padding:8px 12px;border-radius:12px 0 12px 12px;font-size:0.8rem;max-width:80%;'
                        : 'display:inline-block;background:#F5F7F7;color:#1A1F1F;padding:8px 12px;border-radius:0 12px 12px 12px;font-size:0.8rem;max-width:80%;line-height:1.5;'"
                         x-text="msg.content"></div>
                    <template x-if="msg.role === 'assistant' && msg.timestamps && msg.timestamps.length > 0">
                        <div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:4px;">
                            <template x-for="ts in msg.timestamps" :key="ts">
                                <button @click="seekToTimestamp(ts)"
                                        style="padding:2px 8px; background:#E8F5F5; color:#55B1AE; border:none; border-radius:4px; font-size:0.75rem; cursor:pointer; font-family:monospace;"
                                        x-text="'▶ ' + ts"></button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
            <div x-show="videoTyping" style="color:#8A9696; font-size:0.8rem; font-style:italic;">{{ atheneum_setting('assistant_name', 'Minerva') }} sta analizzando il video...</div>
        </div>

        <div style="display:flex; gap:8px;">
            <input type="text" x-model="videoQuestion"
                   @keydown.enter="sendVideoChat()"
                   placeholder="Es: Cosa spiega al minuto 5?"
                   style="flex:1; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            <button @click="sendVideoChat()"
                    :disabled="videoTyping"
                    style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                Invia
            </button>
        </div>
    </div>

    <div x-show="activeTab==='search'" style="padding:16px;">
        <div style="display:flex; gap:8px; margin-bottom:12px;">
            <input type="text" x-model="searchQuery"
                   @keydown.enter="searchVideo()"
                   placeholder="Cerca un argomento nel video..."
                   style="flex:1; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            <button @click="searchVideo()"
                    :disabled="searching"
                    style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                <span x-show="!searching">Cerca</span>
                <span x-show="searching">...</span>
            </button>
        </div>

        <div style="max-height:220px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
            <template x-if="!searching && searchResults.length === 0 && searchQuery">
                <p style="color:#8A9696; font-size:0.8rem; text-align:center; padding:12px;">
                    Nessun risultato.
                </p>
            </template>
            <template x-for="(r, i) in searchResults" :key="i">
                <div style="padding:10px 12px; background:#F5F7F7; border-radius:8px; border-left:3px solid #55B1AE;">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                        <button @click="r.scope === activeScope ? seekTo(r.timestamp_seconds) : null"
                                :style="r.scope === activeScope ? 'cursor:pointer;' : 'cursor:default;'"
                                style="padding:2px 8px; background:#E8F5F5; color:#55B1AE; border:none; border-radius:4px; font-size:0.75rem; font-family:monospace; font-weight:600;"
                                x-text="r.timestamp_str"></button>
                        <span style="color:#8A9696; font-size:0.7rem; text-transform:uppercase; font-weight:700;" x-text="r.scope === 'course' ? 'video corso' : 'video modulo'"></span>
                    </div>
                    <div style="font-size:0.8rem; color:#1A1F1F; line-height:1.5;" x-text="r.text"></div>
                    <template x-if="r.scope !== activeScope && r.deep_link">
                        <a :href="r.deep_link"
                           style="display:inline-block; margin-top:6px; font-size:0.75rem; color:#55B1AE; text-decoration:underline;">
                            Apri nel video di quel modulo →
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
@endif

@pushOnce('scripts')
<script>
if (typeof window.videoPlayer === 'undefined') {
    window.videoPlayer = function(videoId, scope, courseSlug, moduleId) {
        return {
            videoId,
            activeScope: scope,
            courseSlug,
            moduleId,
            activeTab: 'transcript',
            status: 'processing',
            progress: 0,
            stepLabel: 'Avvio...',
            transcript: [],
            videoMessages: [],
            videoQuestion: '',
            videoTyping: false,
            videoHistory: [],
            searchQuery: '',
            searchResults: [],
            searching: false,
            statusTimer: null,

            stepLabels: {
                'upload': 'Caricamento...',
                'extraction': 'Estrazione audio...',
                'transcription': 'Trascrizione...',
                'frames': 'Analisi frame...',
                'indexing': 'Indicizzazione...',
                'correlations': 'Correlazioni...',
                'ready': 'Pronto!',
            },

            async init() {
                await this.checkStatus();
                if (this.status === 'ready') await this.loadTranscript();
                this.handleDeepLink();
            },

            handleDeepLink() {
                const u = new URL(window.location.href);
                const t = u.searchParams.get('t');
                if (!t) return;
                const seconds = parseInt(t);
                if (isNaN(seconds)) return;
                const trySeek = () => {
                    if (this.status === 'ready' && this.$refs.videoEl) {
                        this.seekTo(seconds);
                        this.$refs.videoEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        setTimeout(trySeek, 500);
                    }
                };
                setTimeout(trySeek, 300);
            },

            async checkStatus() {
                try {
                    const res = await fetch(`/learn/video/${this.videoId}/status`);
                    const data = await res.json();
                    this.status = data.status;
                    this.progress = data.progress || 0;
                    this.stepLabel = this.stepLabels[data.step] || data.step || '';

                    if (data.status === 'processing') {
                        this.statusTimer = setTimeout(() => this.checkStatus(), 3000);
                    } else if (data.status === 'ready') {
                        await this.loadTranscript();
                    }
                } catch(e) {
                    this.status = 'ready';
                }
            },

            async loadTranscript() {
                try {
                    const res = await fetch(`/learn/video/${this.videoId}/transcript`);
                    const data = await res.json();
                    this.transcript = data.segments || [];
                } catch(e) {}
            },

            seekTo(seconds) {
                const v = this.$refs.videoEl;
                if (v) {
                    v.currentTime = seconds;
                    v.play().catch(() => {});
                }
            },

            seekToTimestamp(ts) {
                const parts = ts.split(':').map(Number);
                let seconds = 0;
                if (parts.length === 2) seconds = parts[0] * 60 + parts[1];
                if (parts.length === 3) seconds = parts[0] * 3600 + parts[1] * 60 + parts[2];
                this.seekTo(seconds);
            },

            async sendVideoChat() {
                if (!this.videoQuestion.trim() || this.videoTyping) return;

                const question = this.videoQuestion;
                this.videoQuestion = '';
                this.videoTyping = true;

                this.videoMessages.push({ id: Date.now(), role: 'user', content: question });

                try {
                    const res = await fetch(`/learn/video/${this.videoId}/chat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ question, history: this.videoHistory }),
                    });

                    const data = await res.json();
                    this.videoMessages.push({
                        id: Date.now() + 1,
                        role: 'assistant',
                        content: data.answer,
                        timestamps: data.timestamps || [],
                    });

                    this.videoHistory.push(
                        { role: 'user', content: question },
                        { role: 'assistant', content: data.answer }
                    );

                    this.$nextTick(() => {
                        const el = this.$refs.chatMessages;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                } catch(e) {
                    this.videoMessages.push({
                        id: Date.now() + 1,
                        role: 'assistant',
                        content: 'Errore nella risposta. Riprova.',
                        timestamps: [],
                    });
                }

                this.videoTyping = false;
            },

            async searchVideo() {
                if (!this.searchQuery.trim() || this.searching) return;
                this.searching = true;
                let url;
                if (this.activeScope === 'module' && this.moduleId) {
                    url = `/learn/course/${this.courseSlug}/module/${this.moduleId}/video-search?q=${encodeURIComponent(this.searchQuery)}`;
                } else {
                    url = `/learn/course/${this.courseSlug}/video-search?q=${encodeURIComponent(this.searchQuery)}`;
                }
                try {
                    const res = await fetch(url);
                    const data = await res.json();
                    this.searchResults = data.results || [];
                } catch(e) {
                    this.searchResults = [];
                }
                this.searching = false;
            },
        };
    };
}
</script>
@endPushOnce
