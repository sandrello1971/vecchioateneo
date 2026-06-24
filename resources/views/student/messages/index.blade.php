@extends('layouts.student')
@section('title', 'Messaggi')
@section('content')

@php
    $currentUser = \App\Models\Student::find(session('student_id'));
@endphp

<div style="max-width:900px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Messaggi</h2>
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#C52A2A; font-size:0.875rem;">
        {{ session('error') }}
    </div>
    @endif

    @if($conversations->isEmpty())
        <div style="background:white; border-radius:10px; padding:48px 24px; text-align:center; color:#8A9696;">
            <div style="font-size:3rem; margin-bottom:12px;">✉️</div>
            <h3 style="font-size:1rem; font-weight:600; color:#4A5252; margin-bottom:8px;">Nessuna conversazione</h3>
            <p style="font-size:0.875rem;">Le conversazioni con i tuoi formatori compariranno qui.<br>
               Per iniziarne una, vai sulla pagina di un corso e clicca <strong>Scrivi al formatore</strong>.</p>
        </div>
    @else
        <div style="background:white; border-radius:10px; overflow:hidden;">
            @foreach($conversations as $conv)
                @php
                    $other = $conv->otherParticipant($currentUser);
                    $unread = $conv->unreadCountFor($currentUser);
                @endphp
                <a href="{{ route('student.messages.show', $conv) }}"
                   style="display:block; padding:16px 20px; border-bottom:1px solid #F5F7F7; text-decoration:none; color:inherit; transition:background 0.15s;"
                   onmouseover="this.style.background='#FAFBFB'"
                   onmouseout="this.style.background='white'">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <span style="font-weight:{{ $unread > 0 ? '700' : '600' }}; color:#1A1F1F; font-size:0.95rem;">{{ $other?->name ?? '—' }}</span>
                                @if($conv->course)
                                <span style="font-size:0.7rem; color:#55B1AE; background:rgba(85,177,174,0.1); padding:2px 8px; border-radius:10px; font-weight:600;">{{ $conv->course->name }}</span>
                                @endif
                                @if($unread > 0)
                                <span style="margin-left:auto; background:#E28A53; color:white; font-size:0.7rem; font-weight:700; padding:2px 9px; border-radius:10px;">{{ $unread }}</span>
                                @endif
                            </div>
                            <div style="font-weight:{{ $unread > 0 ? '600' : '500' }}; color:#4A5252; font-size:0.85rem; margin-bottom:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $conv->subject }}</div>
                            @if($conv->latest_body)
                            <div style="color:#8A9696; font-size:0.78rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                {{ $conv->latest_sender_id === $currentUser->id ? 'Tu: ' : '' }}{{ \Illuminate\Support\Str::limit($conv->latest_body, 100) }}
                            </div>
                            @endif
                        </div>
                        <div style="color:#8A9696; font-size:0.7rem; white-space:nowrap; flex-shrink:0;">
                            {{ $conv->last_message_at?->diffForHumans() ?? '—' }}
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div style="margin-top:16px;">{{ $conversations->links() }}</div>
    @endif
</div>

@endsection
