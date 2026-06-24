@extends('layouts.admin')
@section('title', 'Elaborazione documenti')
@section('content')

<div style="max-width:680px; margin:0 auto;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="/admin/courses" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Corsi</a>
        <span style="color:#C8D0D0;">|</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Elaborazione documenti</h2>
    </div>

    <div id="card" style="background:white; border-radius:12px; padding:32px; border:1px solid #C8D0D0;">

        {{-- Stato: running --}}
        <div id="state-running">
            <div style="text-align:center; margin-bottom:24px;">
                <div id="spinner" style="display:inline-block; width:64px; height:64px; border:4px solid #E8F5F5; border-top-color:#55B1AE; border-radius:50%; animation:spin 0.9s linear infinite;"></div>
            </div>

            <div style="text-align:center; margin-bottom:24px;">
                <div id="stage-message" style="font-size:1.15rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">In coda...</div>
                <div style="font-size:0.85rem; color:#8A9696;">
                    Stage <span id="stage-num">0</span> di 5 &middot; <span id="elapsed">0</span>s trascorsi
                </div>
            </div>

            <div style="height:8px; background:#E8F5F5; border-radius:4px; overflow:hidden; margin-bottom:24px;">
                <div id="progress-bar" style="height:100%; width:0%; background:linear-gradient(90deg, #55B1AE, #3A8C89); border-radius:4px; transition:width 0.4s ease;"></div>
            </div>

            <div style="border-top:1px solid #E8F5F5; padding-top:16px;">
                <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Avanzamento</div>
                <div id="stage-list" style="display:flex; flex-direction:column; gap:8px; font-size:0.85rem;">
                    <div data-stage="1" class="stage-item">
                        <span class="stage-icon">○</span>
                        <span>Conversione manuale (pandoc)</span>
                    </div>
                    <div data-stage="2" class="stage-item">
                        <span class="stage-icon">○</span>
                        <span>Identificazione struttura moduli</span>
                    </div>
                    <div data-stage="3" class="stage-item">
                        <span class="stage-icon">○</span>
                        <span>Estrazione metadati corso (LLM)</span>
                    </div>
                    <div data-stage="4" class="stage-item">
                        <span class="stage-icon">○</span>
                        <span>Conversione esame (pandoc)</span>
                    </div>
                    <div data-stage="5" class="stage-item">
                        <span class="stage-icon">○</span>
                        <span>Estrazione domande esame (LLM)</span>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px; padding-top:16px; border-top:1px solid #E8F5F5; font-size:0.7rem; color:#8A9696; font-family:monospace;">
                Job ID: {{ $jobId }}
            </div>
        </div>

        {{-- Stato: success (visibile brevemente prima del redirect) --}}
        <div id="state-success" style="display:none; text-align:center;">
            <div style="font-size:3rem; margin-bottom:12px;">✓</div>
            <div style="font-size:1.15rem; font-weight:700; color:#3A8C89; margin-bottom:6px;">Elaborazione completata</div>
            <div style="font-size:0.85rem; color:#8A9696;">Apertura preview...</div>
        </div>

        {{-- Stato: error --}}
        <div id="state-error" style="display:none;">
            <div style="text-align:center; margin-bottom:20px;">
                <div style="font-size:3rem; margin-bottom:12px;">⚠</div>
                <div style="font-size:1.15rem; font-weight:700; color:#c97a45; margin-bottom:6px;">Elaborazione fallita</div>
                <div style="font-size:0.85rem; color:#8A9696;">Si è verificato un errore durante il processing.</div>
            </div>

            <div style="background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; padding:16px; margin-bottom:20px;">
                <div style="font-size:0.7rem; font-weight:700; color:#c97a45; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Errore (stage <span id="error-stage">?</span>)</div>
                <div id="error-message" style="font-size:0.85rem; color:#4A5252; font-family:monospace; white-space:pre-wrap; word-break:break-word;"></div>
            </div>

            <div style="display:flex; gap:12px; justify-content:center;">
                <a href="{{ route('admin.courses.ingest.form') }}"
                   style="padding:10px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:700; text-decoration:none;">
                    Riprova
                </a>
                <a href="{{ route('admin.courses.index') }}"
                   style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                    Torna ai corsi
                </a>
            </div>

            <div style="margin-top:20px; padding-top:16px; border-top:1px solid #E8F5F5; font-size:0.7rem; color:#8A9696; font-family:monospace;">
                Job ID: {{ $jobId }}
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
.stage-item {
    display:flex;
    align-items:center;
    gap:10px;
    color:#8A9696;
    transition: color 0.3s;
}
.stage-item .stage-icon {
    display:inline-block;
    width:20px;
    height:20px;
    line-height:20px;
    text-align:center;
    border-radius:50%;
    font-size:0.85rem;
    font-weight:700;
    flex-shrink:0;
}
.stage-item.completed { color:#3A8C89; }
.stage-item.completed .stage-icon { background:#E8F5F5; color:#3A8C89; }
.stage-item.current { color:#1A1F1F; font-weight:600; }
.stage-item.current .stage-icon {
    background:#55B1AE;
    color:white;
    animation: pulse-icon 1.2s ease-in-out infinite;
}
@keyframes pulse-icon {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.15); opacity: 0.85; }
}
</style>

<script>
(function () {
    const STATUS_URL = "{{ route('admin.courses.ingest.status', ['job' => $jobId]) }}";
    const PREVIEW_URL = "{{ route('admin.courses.ingest.preview', ['job' => $jobId]) }}";
    const POLL_MS = 2000;
    const STARTED_AT = Date.now();

    const stageMessage = document.getElementById('stage-message');
    const stageNum = document.getElementById('stage-num');
    const progressBar = document.getElementById('progress-bar');
    const elapsedEl = document.getElementById('elapsed');
    const stageItems = document.querySelectorAll('.stage-item');
    const stateRunning = document.getElementById('state-running');
    const stateSuccess = document.getElementById('state-success');
    const stateError = document.getElementById('state-error');
    const errorMessage = document.getElementById('error-message');
    const errorStage = document.getElementById('error-stage');

    let pollHandle = null;
    let elapsedHandle = null;

    function tickElapsed() {
        const sec = Math.floor((Date.now() - STARTED_AT) / 1000);
        elapsedEl.textContent = sec;
    }
    elapsedHandle = setInterval(tickElapsed, 1000);

    function showError(msg, stage) {
        if (pollHandle) clearInterval(pollHandle);
        if (elapsedHandle) clearInterval(elapsedHandle);
        errorMessage.textContent = msg || 'Errore sconosciuto';
        errorStage.textContent = stage != null ? stage : '?';
        stateRunning.style.display = 'none';
        stateError.style.display = 'block';
    }

    function showSuccess() {
        if (pollHandle) clearInterval(pollHandle);
        if (elapsedHandle) clearInterval(elapsedHandle);
        stateRunning.style.display = 'none';
        stateSuccess.style.display = 'block';
    }

    function updateUI(data) {
        const stage = data.stage || 0;
        const total = data.total_stages || 5;
        const message = data.message || '...';

        stageMessage.textContent = message;
        stageNum.textContent = stage;

        const pct = Math.min(100, (stage / total) * 100);
        progressBar.style.width = pct + '%';

        stageItems.forEach((el) => {
            const n = parseInt(el.dataset.stage, 10);
            el.classList.remove('completed', 'current');
            const icon = el.querySelector('.stage-icon');
            if (n < stage) {
                el.classList.add('completed');
                icon.textContent = '✓';
            } else if (n === stage) {
                el.classList.add('current');
                icon.textContent = n;
            } else {
                icon.textContent = '○';
            }
        });
    }

    async function poll() {
        try {
            const resp = await fetch(STATUS_URL, { headers: { 'Accept': 'application/json' } });
            if (resp.status === 404) {
                showError('Job non trovato o scaduto.', '?');
                return;
            }
            if (!resp.ok) {
                console.warn('poll non-ok:', resp.status);
                return;
            }
            const data = await resp.json();
            updateUI(data);

            if (data.done) {
                if (data.error) {
                    showError(data.error, data.failed_at_stage ?? '?');
                } else {
                    showSuccess();
                    setTimeout(() => { window.location.href = PREVIEW_URL; }, 1200);
                }
            }
        } catch (e) {
            console.warn('poll exception:', e);
        }
    }

    pollHandle = setInterval(poll, POLL_MS);
    poll();
})();
</script>

@endsection
