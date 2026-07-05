@props(['title', 'streamUrl', 'searchUrl', 'status' => 'ready', 'failureReason' => null])

{{-- Player HTML5 (mp4 locale, Range/seek) + ricerca PER-VIDEO (parlato + Vision).
     Riusato lato docente (anteprima) e studente (video pubblicato). --}}
<div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:14px;"
     x-data="uploadedVideoSearch('{{ $searchUrl }}')">
    <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F; margin-bottom:10px;">🎥 {{ $title }}</div>

    @if($status === 'ready')
        <video x-ref="player" controls preload="metadata"
               style="width:100%; max-width:880px; border-radius:8px; background:#0A0A0A; aspect-ratio:16/9;"
               src="{{ $streamUrl }}"></video>

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
    @elseif($status === 'failed')
        <p style="font-size:0.82rem; color:#B03A3A;">Analisi non riuscita{{ $failureReason ? ': ' . $failureReason : '' }}.</p>
    @else
        <p style="font-size:0.82rem; color:#8A9696;">Analisi in corso (trascrizione + immagini)…</p>
    @endif
</div>

@once
<script>
    function uploadedVideoSearch(url) {
        return {
            url, q: '', results: [], loading: false, done: false,
            async run() {
                if (!this.q.trim()) return;
                this.loading = true; this.done = false; this.results = [];
                try {
                    const r = await fetch(this.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ q: this.q }),
                    });
                    const j = await r.json();
                    this.results = j.matches || [];
                } catch (e) { this.results = []; }
                this.loading = false; this.done = true;
            },
            seek(s) { this.$refs.player.currentTime = s; this.$refs.player.play(); this.$refs.player.scrollIntoView({ behavior: 'smooth', block: 'center' }); },
            fmt(s) { const m = Math.floor(s / 60), sec = Math.floor(s % 60); return m + ':' + String(sec).padStart(2, '0'); },
        };
    }
</script>
@endonce
