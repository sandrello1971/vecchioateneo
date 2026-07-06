@props(['title', 'streamUrl', 'searchUrl', 'askUrl' => null, 'status' => 'ready', 'failureReason' => null])

{{-- Player HTML5 (mp4 locale, Range/seek) + due modalità: "Cerca" (passaggi, parlato
     + Vision) e "Chiedi al video" (Q&A grounded). Riusato dal docente (anteprima) e
     dallo studente, per video caricati e generati. --}}
<div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:14px;"
     x-data="uploadedVideoBox('{{ $searchUrl }}', @js($askUrl))">
    <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F; margin-bottom:10px;">🎥 {{ $title }}</div>

    @if($status === 'ready')
        <video x-ref="player" controls preload="metadata"
               style="width:100%; max-width:880px; border-radius:8px; background:#0A0A0A; aspect-ratio:16/9;"
               src="{{ $streamUrl }}"></video>

        @if($askUrl)
            {{-- Toggle modalità --}}
            <div style="margin-top:12px; display:inline-flex; gap:2px; background:#EEF2F2; border-radius:8px; padding:3px;">
                <button type="button" @click="mode='search'" :style="mode==='search' ? 'background:#55B1AE;color:white;' : 'background:transparent;color:#4A5252;'"
                        style="border:none; padding:6px 14px; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Cerca</button>
                <button type="button" @click="mode='ask'" :style="mode==='ask' ? 'background:#55B1AE;color:white;' : 'background:transparent;color:#4A5252;'"
                        style="border:none; padding:6px 14px; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">Chiedi al video</button>
            </div>
        @endif

        {{-- Modalità CERCA (passaggi) --}}
        <div x-show="mode==='search'">
            <form @submit.prevent="run()" style="margin-top:12px; display:flex; gap:6px; max-width:880px;">
                <input x-model="q" type="text" placeholder="Cerca in questo video (parlato, testo a schermo e immagini)…"
                       style="flex:1; padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
                <button style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Cerca</button>
            </form>
            <p x-show="loading" style="margin-top:8px; font-size:0.82rem; color:#8A9696;">Ricerca…</p>
            <p x-show="done && !results.length" style="margin-top:8px; font-size:0.82rem; color:#8A9696;">Nessun riscontro in questo video.</p>
            <ul x-show="results.length" style="margin-top:8px; list-style:none; padding:0; max-width:880px; display:flex; flex-direction:column; gap:4px;">
                <template x-for="m in results" :key="m.start + '_' + m.text.slice(0,12)">
                    <li @click="seek(m.start)" style="cursor:pointer; padding:7px 10px; background:#F4F6F6; border-radius:7px; font-size:0.82rem; color:#4A5252;">
                        <span style="font-weight:700; color:#3A8C89;" x-text="fmt(m.start)"></span>
                        <span style="font-size:0.68rem; color:#8A9696;" x-text="m.type === 'frame' ? ' schermo/immagine' : ' parlato'"></span>
                        — <span x-text="m.text"></span>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Modalità CHIEDI (Q&A grounded) --}}
        @if($askUrl)
        <div x-show="mode==='ask'" x-cloak>
            <form @submit.prevent="runAsk()" style="margin-top:12px; display:flex; gap:6px; max-width:880px;">
                <input x-model="aq" type="text" placeholder="Fai una domanda sul contenuto del video…"
                       style="flex:1; padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
                <button style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Invia</button>
            </form>
            <p x-show="aloading" style="margin-top:8px; font-size:0.82rem; color:#8A9696;">Sto leggendo il video…</p>
            <div x-show="aanswer" x-cloak @click="onAnswerClick($event)"
                 style="margin-top:10px; max-width:880px; padding:12px 14px; background:#F4F6F6; border-radius:8px; font-size:0.86rem; color:#2A3030; line-height:1.5;"
                 x-html="aanswer"></div>
            <p style="margin-top:6px; font-size:0.72rem; color:#8A9696;">Risponde solo sul contenuto del video; i minuti [m:ss] sono cliccabili.</p>
        </div>
        @endif
    @elseif($status === 'failed')
        <p style="font-size:0.82rem; color:#B03A3A;">Analisi non riuscita{{ $failureReason ? ': ' . $failureReason : '' }}.</p>
    @else
        <p style="font-size:0.82rem; color:#8A9696;">Analisi in corso (trascrizione + immagini)…</p>
    @endif
</div>

@once
<script>
    function uploadedVideoBox(searchUrl, askUrl) {
        return {
            mode: 'search',
            // Cerca
            url: searchUrl, q: '', results: [], loading: false, done: false,
            // Chiedi
            askUrl, aq: '', aanswer: '', aloading: false,
            _csrf() { return document.querySelector('meta[name=csrf-token]').content; },
            async run() {
                if (!this.q.trim()) return;
                this.loading = true; this.done = false; this.results = [];
                try {
                    const r = await fetch(this.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf() },
                        body: JSON.stringify({ q: this.q }),
                    });
                    const j = await r.json();
                    this.results = j.matches || [];
                } catch (e) { this.results = []; }
                this.loading = false; this.done = true;
            },
            async runAsk() {
                if (!this.aq.trim() || !this.askUrl) return;
                this.aloading = true; this.aanswer = '';
                try {
                    const r = await fetch(this.askUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf() },
                        body: JSON.stringify({ question: this.aq }),
                    });
                    const j = await r.json();
                    this.aanswer = this.renderAnswer(j.answer || 'Nessuna risposta.');
                } catch (e) { this.aanswer = 'Errore nella risposta. Riprova.'; }
                this.aloading = false;
            },
            renderAnswer(text) {
                // Escape HTML, poi **grassetto**, a capo, e [m:ss] cliccabili (seek).
                let h = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                h = h.replace(/\[(?:AUDIO |FRAME )?(\d{1,2}):(\d{2})(?::(\d{2}))?\]/g, (m, a, b, c) => {
                    const sec = c !== undefined ? (+a*3600 + +b*60 + +c) : (+a*60 + +b);
                    const label = c !== undefined ? `${a}:${b}:${c}` : `${a}:${b}`;
                    return `<a href="#" data-sec="${sec}" style="color:#3A8C89; font-weight:700; text-decoration:none;">[${label}]</a>`;
                });
                return h.replace(/\n/g, '<br>');
            },
            onAnswerClick(e) {
                const a = e.target.closest('[data-sec]');
                if (!a) return;
                e.preventDefault();
                this.seek(parseFloat(a.dataset.sec));
            },
            seek(s) { this.$refs.player.currentTime = s; this.$refs.player.play(); this.$refs.player.scrollIntoView({ behavior: 'smooth', block: 'center' }); },
            fmt(s) { const m = Math.floor(s / 60), sec = Math.floor(s % 60); return m + ':' + String(sec).padStart(2, '0'); },
        };
    }
</script>
@endonce
