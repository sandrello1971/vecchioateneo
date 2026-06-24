@extends('layouts.student')
@section('title', 'Impostazioni formatore')
@section('content')

<div style="max-width:800px;">

    <div style="margin-bottom:20px;">
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Impostazioni formatore</h2>
        <p style="font-size:0.85rem; color:#8A9696; margin-top:4px;">
            Gestisci le preferenze per i corsi in cui insegni.
        </p>
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('student.instructor_settings.updateDm') }}">
        @csrf
        @method('PATCH')

        <div style="background:white; border-radius:10px; overflow:hidden; margin-bottom:20px;">
            <div style="padding:16px 20px; border-bottom:1px solid #F5F7F7;">
                <h3 style="font-size:0.95rem; font-weight:700; color:#1A1F1F;">Messaggi diretti dai discenti</h3>
                <p style="font-size:0.8rem; color:#8A9696; margin-top:4px; line-height:1.45;">
                    Quando disabilitato, i discenti non potranno aprire <strong>nuove</strong> conversazioni con te in quel corso.
                    Le conversazioni già aperte continueranno a funzionare normalmente.
                </p>
            </div>

            <div>
                @foreach($teachingRows as $row)
                <label style="display:flex; align-items:center; gap:12px; padding:14px 20px; border-bottom:1px solid #F5F7F7; cursor:pointer; transition:background 0.15s;"
                       onmouseover="this.style.background='#FAFBFB'"
                       onmouseout="this.style.background='white'">
                    {{-- Hidden input garantisce che il browser invii almeno 0 per ogni course_id
                         (HTML form omette le checkbox unchecked; questo simula un valore esplicito) --}}
                    <input type="hidden" name="accepts_dm[{{ $row->course_id }}]" value="0">
                    <input type="checkbox"
                           name="accepts_dm[{{ $row->course_id }}]"
                           value="1"
                           {{ $row->accepts_dm ? 'checked' : '' }}
                           style="width:18px; height:18px; accent-color:#55B1AE; cursor:pointer;">
                    <div style="flex:1;">
                        <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem;">{{ $row->course_name }}</div>
                        <div style="color:#8A9696; font-size:0.75rem; margin-top:2px;">
                            {{ $row->accepts_dm ? 'I discenti possono scriverti' : 'Nuovi DM disabilitati' }}
                        </div>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
            <button type="submit" style="padding:9px 22px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">
                Salva preferenze
            </button>
        </div>
    </form>
</div>

@endsection
