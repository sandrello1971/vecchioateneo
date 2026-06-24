@extends('layouts.student')
@section('title', 'Assistente AI — ' . $course->name)
@section('breadcrumb', 'Assistente AI · ' . $course->name)

@section('content')
<div style="max-width:800px; height:calc(100vh - 140px); display:flex; flex-direction:column;">

    <div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); border-radius:12px 12px 0 0; padding:16px 20px; display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">&#10022;</div>
        <div>
            <div style="color:white; font-weight:700;">{{ atheneum_setting('assistant_name', 'Minerva') }}</div>
            <div style="color:rgba(255,255,255,0.7); font-size:0.75rem;">Assistente AI · {{ $course->name }}</div>
        </div>
    </div>

    <div id="messages-container" class="minerva-msgs" style="background:white; padding:20px; gap:12px;">

        <div style="display:flex; gap:10px; align-items:flex-start;">
            <div style="width:32px; height:32px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:0.8rem; flex-shrink:0;">&#10022;</div>
            <div style="max-width:70%; padding:12px 16px; background:#F5F7F7; border-radius:0 12px 12px 12px; font-size:0.875rem; color:#1A1F1F; line-height:1.6;">
                @php $assistantName = atheneum_setting('assistant_name', 'Minerva'); $intro = atheneum_setting('assistant_intro_message', ''); @endphp
                @if($intro)
                    {{ $intro }}
                @else
                    Ciao! Sono <strong>{{ $assistantName }}</strong>, il tuo assistente AI per il corso <strong>{{ $course->name }}</strong>.
                    Sono qui per aiutarti a capire i contenuti del corso. Cosa vorresti sapere?
                @endif
            </div>
        </div>

        @foreach($messages as $message)
        @if($message->role === 'user')
        <div style="display:flex; justify-content:flex-end;">
            <div style="max-width:70%; padding:12px 16px; background:#55B1AE; border-radius:12px 0 12px 12px; font-size:0.875rem; color:white; line-height:1.6;">
                {{ $message->content }}
            </div>
        </div>
        @else
        <div style="display:flex; gap:10px; align-items:flex-start;">
            <div style="width:32px; height:32px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:0.8rem; flex-shrink:0;">&#10022;</div>
            <div style="max-width:70%; padding:12px 16px; background:#F5F7F7; border-radius:0 12px 12px 12px; font-size:0.875rem; color:#1A1F1F; line-height:1.6;">
                {!! nl2br(e($message->content)) !!}
            </div>
        </div>
        @endif
        @endforeach

        <div id="messages-end"></div>
    </div>

    <div style="background:white; border-top:1px solid #E8F5F5; border-radius:0 0 12px 12px; padding:16px;">
        <form id="chat-form" style="display:flex; gap:10px;">
            <input type="hidden" id="conversation-id" value="{{ $conversation->id }}">
            <input type="text" id="message-input" placeholder="Scrivi una domanda sui contenuti del corso..." style="flex:1; padding:10px 16px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none; color:#1A1F1F;" autocomplete="off">
            <button type="submit" style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.875rem;">Invia</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
const conversationId = document.getElementById('conversation-id').value;
const form = document.getElementById('chat-form');
const input = document.getElementById('message-input');
const container = document.getElementById('messages-container');

function scrollBottom() {
    container.scrollTop = container.scrollHeight;
}

function addMessage(content, role) {
    const wrapper = document.createElement('div');
    if (role === 'user') {
        wrapper.style.cssText = 'display:flex;justify-content:flex-end;';
        wrapper.innerHTML = `<div style="max-width:70%;padding:12px 16px;background:#55B1AE;border-radius:12px 0 12px 12px;font-size:0.875rem;color:white;line-height:1.6;">${content}</div>`;
    } else {
        wrapper.style.cssText = 'display:flex;gap:10px;align-items:flex-start;';
        const formattedContent = content
            .replace(/\n/g, '<br>')
            .replace(/\[(\d{1,2}:\d{2}(?::\d{2})?)\]/g,
                '<span style="background:#E8F5F5;color:#55B1AE;padding:1px 6px;border-radius:4px;font-family:monospace;font-size:0.8rem;cursor:pointer;" onclick="window.seekVideoTo(\'$1\')">▶ $1</span>');
        wrapper.innerHTML = `
            <div style="width:32px;height:32px;border-radius:50%;background:#55B1AE;display:flex;align-items:center;justify-content:center;color:white;font-size:0.8rem;flex-shrink:0;">&#10022;</div>
            <div style="max-width:70%;padding:12px 16px;background:#F5F7F7;border-radius:0 12px 12px 12px;font-size:0.875rem;color:#1A1F1F;line-height:1.6;">${formattedContent}</div>`;
    }
    container.insertBefore(wrapper, document.getElementById('messages-end'));
    scrollBottom();
}

window.seekVideoTo = function(timestamp) {
    const parts = timestamp.split(':').map(Number);
    let seconds = 0;
    if (parts.length === 2) seconds = parts[0] * 60 + parts[1];
    if (parts.length === 3) seconds = parts[0] * 3600 + parts[1] * 60 + parts[2];

    const video = document.querySelector('video');
    if (video) {
        video.currentTime = seconds;
        video.play();
        video.scrollIntoView({ behavior: 'smooth' });
    } else {
        alert('Apri il modulo video per saltare a ' + timestamp);
    }
}

function addTyping() {
    const div = document.createElement('div');
    div.id = 'typing';
    div.style.cssText = 'display:flex;gap:10px;align-items:flex-start;';
    div.innerHTML = `
        <div style="width:32px;height:32px;border-radius:50%;background:#55B1AE;display:flex;align-items:center;justify-content:center;color:white;font-size:0.8rem;">&#10022;</div>
        <div style="padding:12px 16px;background:#F5F7F7;border-radius:0 12px 12px 12px;font-size:0.875rem;color:#8A9696;">{{ atheneum_setting('assistant_name', 'Minerva') }} sta scrivendo...</div>`;
    container.insertBefore(div, document.getElementById('messages-end'));
    scrollBottom();
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const message = input.value.trim();
    if (!message) return;
    addMessage(escapeHtml(message), 'user');
    input.value = '';
    addTyping();
    try {
        const res = await fetch('/learn/chat/message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ conversation_id: conversationId, message }),
        });
        document.getElementById('typing')?.remove();
        const data = await res.json();
        addMessage(escapeHtml(data.reply || data.message || 'Errore nella risposta.'), 'assistant', data.timestamps || []);
    } catch(err) {
        document.getElementById('typing')?.remove();
        addMessage('Errore di connessione. Riprova tra poco.', 'assistant');
    }
});

scrollBottom();
input.focus();
</script>
@endpush

@endsection
