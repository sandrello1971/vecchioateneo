@extends('layouts.student')
@section('title', $conversation->subject)
@section('content')

@php
    $currentUser = \App\Models\Student::find(session('student_id'));
    $other = $conversation->otherParticipant($currentUser);
@endphp

<div style="max-width:780px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a href="{{ route('student.messages.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.875rem;">← Messaggi</a>
    </div>

    {{-- Header thread --}}
    <div style="background:white; border-radius:10px 10px 0 0; padding:18px 22px; border-bottom:1px solid #F5F7F7;">
        <h2 style="font-size:1.1rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">{{ $conversation->subject }}</h2>
        <div style="display:flex; gap:12px; align-items:center; font-size:0.8rem; color:#8A9696;">
            <span>con <strong style="color:#4A5252;">{{ $other?->name ?? '—' }}</strong></span>
            @if($conversation->course)
            <span>•</span>
            <span style="color:#55B1AE; font-weight:600;">{{ $conversation->course->name }}</span>
            @endif
        </div>
    </div>

    {{-- Lista messaggi --}}
    <div id="messages-container" style="background:#FAFBFB; padding:20px 22px; max-height:540px; overflow-y:auto;">
        @foreach($conversation->messages as $msg)
            @php $isMine = $msg->sender_id === $currentUser->id; @endphp
            <div data-message-id="{{ $msg->id }}" style="display:flex; justify-content:{{ $isMine ? 'flex-end' : 'flex-start' }}; margin-bottom:14px;">
                <div style="max-width:75%;">
                    <div style="font-size:0.7rem; color:#8A9696; margin-bottom:3px; {{ $isMine ? 'text-align:right;' : '' }}">
                        <strong style="color:#4A5252;">{{ $isMine ? 'Tu' : $msg->sender->name }}</strong>
                        · {{ $msg->created_at->format('d/m/Y H:i') }}
                    </div>
                    <div style="background:{{ $isMine ? '#55B1AE' : 'white' }}; color:{{ $isMine ? 'white' : '#1A1F1F' }}; padding:10px 14px; border-radius:12px; font-size:0.9rem; line-height:1.45; white-space:pre-wrap; word-wrap:break-word; box-shadow:0 1px 2px rgba(0,0,0,0.04);">{{ $msg->body }}</div>
                </div>
            </div>
        @endforeach

        {{-- Typing indicator (mostrato/nascosto via JS) --}}
        <div id="typing-indicator" style="display:none; padding:6px 14px; color:#8A9696; font-size:0.8rem; font-style:italic;">
            <span id="typing-name"></span> sta scrivendo…
        </div>
    </div>

    {{-- Form reply --}}
    <div style="background:white; border-radius:0 0 10px 10px; padding:16px 22px;">
        @if($errors->any())
        <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:10px 14px; border-radius:6px; margin-bottom:12px; color:#C52A2A; font-size:0.85rem;">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('student.messages.messages.store', $conversation) }}">
            @csrf
            <textarea id="reply-body" name="body" rows="3" maxlength="5000" required
                      placeholder="Scrivi la tua risposta…"
                      style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; resize:vertical; min-height:70px;">{{ old('body') }}</textarea>
            <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                <button type="submit" style="padding:8px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">Invia</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    if (!window.Echo) return;  // Reverb non disponibile (utente non loggato?)

    const conversationId = @json($conversation->id);
    const currentUserId = @json($currentUser->id);
    const container = document.getElementById('messages-container');
    const typingIndicator = document.getElementById('typing-indicator');
    const typingName = document.getElementById('typing-name');
    const replyBody = document.getElementById('reply-body');

    // Auto-scroll al fondo all'apertura del thread (mostra ultimi messaggi)
    container.scrollTop = container.scrollHeight;

    // Helper per rendering bubble nuovo messaggio (mirror del blade foreach loop)
    function renderMessageBubble(payload) {
        const isMine = payload.sender_id === currentUserId;
        const wrap = document.createElement('div');
        wrap.setAttribute('data-message-id', payload.message_id);
        wrap.style.cssText = `display:flex; justify-content:${isMine ? 'flex-end' : 'flex-start'}; margin-bottom:14px;`;

        const inner = document.createElement('div');
        inner.style.maxWidth = '75%';

        const meta = document.createElement('div');
        meta.style.cssText = `font-size:0.7rem; color:#8A9696; margin-bottom:3px; ${isMine ? 'text-align:right;' : ''}`;
        const time = new Date(payload.created_at).toLocaleString('it-IT', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        meta.innerHTML = `<strong style="color:#4A5252;">${isMine ? 'Tu' : escapeHtml(payload.sender_name)}</strong> · ${time}`;

        const bubble = document.createElement('div');
        bubble.style.cssText = `background:${isMine ? '#55B1AE' : 'white'}; color:${isMine ? 'white' : '#1A1F1F'}; padding:10px 14px; border-radius:12px; font-size:0.9rem; line-height:1.45; white-space:pre-wrap; word-wrap:break-word; box-shadow:0 1px 2px rgba(0,0,0,0.04);`;
        bubble.textContent = payload.body;

        inner.appendChild(meta);
        inner.appendChild(bubble);
        wrap.appendChild(inner);
        return wrap;
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    // Subscribe al private channel del thread per ricevere MessageSent live
    window.Echo.private(`conversation.${conversationId}`)
        .listen('.MessageSent', (payload) => {
            // Skip duplicati (es. messaggio mio gia in DOM dal redirect post-submit)
            if (container.querySelector(`[data-message-id="${payload.message_id}"]`)) return;

            // Insert prima del typing indicator
            container.insertBefore(renderMessageBubble(payload), typingIndicator);
            container.scrollTop = container.scrollHeight;

            // Notifica server: marca questo come letto (no broadcast back, solo DB)
            fetch(@json(route('student.messages.markRead', $conversation)), {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
            }).catch(() => {});
        });

    // Presence channel per typing indicator (whisper events client-to-client)
    const presence = window.Echo.join(`conversation.${conversationId}`)
        .listenForWhisper('typing', (e) => {
            if (e.userId === currentUserId) return;  // ignora propri whisper
            typingName.textContent = e.userName;
            typingIndicator.style.display = 'block';
            clearTimeout(window._typingTimeout);
            window._typingTimeout = setTimeout(() => {
                typingIndicator.style.display = 'none';
            }, 2000);
        });

    // Emetti whisper "typing" mentre l'utente digita (throttle a 1s)
    let lastWhisperAt = 0;
    replyBody.addEventListener('input', () => {
        const now = Date.now();
        if (now - lastWhisperAt < 1000) return;
        lastWhisperAt = now;
        presence.whisper('typing', {
            userId: currentUserId,
            userName: @json($currentUser->name),
        });
    });
})();
</script>
@endpush

@endsection
