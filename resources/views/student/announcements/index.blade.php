@extends('layouts.student')
@section('title', 'Annunci')
@section('content')

@php
    $currentUser = \App\Models\Student::find(session('student_id'));
    $isAnyCourseInstructor = $currentUser
        && \DB::table('course_instructor')->where('instructor_id', $currentUser->id)->exists();
@endphp

<div style="max-width:900px;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Annunci</h2>
            <p style="font-size:0.8rem; color:#8A9696; margin-top:2px;">Comunicazioni dei formatori dei tuoi corsi</p>
        </div>
        @if($isAnyCourseInstructor)
        <a href="{{ route('student.announcements.create') }}"
           style="padding:8px 18px; background:#E28A53; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
            📢 Nuovo annuncio
        </a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    @if($announcements->isEmpty())
    <div style="background:white; border-radius:10px; padding:48px 24px; text-align:center; color:#8A9696;">
        <div style="font-size:3rem; margin-bottom:12px;">📢</div>
        <h3 style="font-size:1rem; font-weight:600; color:#4A5252; margin-bottom:8px;">Nessun annuncio</h3>
        <p style="font-size:0.875rem;">
            @if($isAnyCourseInstructor)
                Puoi pubblicare il primo annuncio dal bottone in alto.
            @else
                Quando i formatori dei tuoi corsi pubblicheranno annunci, compariranno qui.
            @endif
        </p>
    </div>
    @else
    <div style="background:white; border-radius:10px; overflow:hidden;">
        @foreach($announcements as $ann)
            @php
                $isMine = $ann->instructor_id === $currentUser->id;
                // is_read viene dalla addSelect del controller; null = non letto.
                // Per i propri annunci pubblicati la nozione di "unread" non si applica.
                $isUnread = !$isMine && empty($ann->is_read);
            @endphp
            <a href="{{ route('student.announcements.show', $ann) }}"
               style="display:block; padding:16px 20px; border-bottom:1px solid #F5F7F7; text-decoration:none; color:inherit; transition:background 0.15s; {{ $isUnread ? 'background:#FFF8F1;' : '' }}"
               onmouseover="this.style.background='#FAFBFB'"
               onmouseout="this.style.background='{{ $isUnread ? '#FFF8F1' : 'white' }}'">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <span style="font-size:0.7rem; color:#E28A53; background:rgba(226,138,83,0.12); padding:2px 8px; border-radius:10px; font-weight:700; letter-spacing:0.05em;">📢 ANNUNCIO</span>
                            <span style="font-size:0.7rem; color:#55B1AE; background:rgba(85,177,174,0.1); padding:2px 8px; border-radius:10px; font-weight:600;">{{ $ann->course->name }}</span>
                            @if($isMine)
                            <span style="font-size:0.65rem; color:#8A9696; text-transform:uppercase; letter-spacing:0.05em;">tu</span>
                            @endif
                            @if($isUnread)
                            <span style="margin-left:auto; background:#E28A53; color:white; font-size:0.65rem; font-weight:700; padding:2px 8px; border-radius:10px;">NUOVO</span>
                            @endif
                        </div>
                        <div style="font-weight:{{ $isUnread ? '700' : '600' }}; color:#1A1F1F; font-size:0.95rem; margin-bottom:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $ann->subject }}</div>
                        <div style="color:#8A9696; font-size:0.78rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            di <strong>{{ $ann->instructor->name }}</strong> — {{ \Illuminate\Support\Str::limit($ann->body, 100) }}
                        </div>
                    </div>
                    <div style="color:#8A9696; font-size:0.7rem; white-space:nowrap; flex-shrink:0;">
                        {{ $ann->created_at->diffForHumans() }}
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <div style="margin-top:16px;">{{ $announcements->links() }}</div>
    @endif
</div>

@endsection
