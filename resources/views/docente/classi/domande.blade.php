@extends('layouts.docente')
@section('title', 'Domande scoperte · ' . $class->name)
@section('breadcrumb', 'Classi / ' . $class->name . ' / Domande scoperte')
@section('content')
<div style="max-width:920px;">
    <div style="margin-bottom:8px;"><a href="{{ route('docente.classes.activity', $class) }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Attività</a></div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">Domande scoperte</h1>
    <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
        Domande degli studenti fuori dai materiali della classe — la mappa di ciò che manca o non è stato capito.
        Aperte: <strong>{{ collect($clusters)->sum('count') }}</strong> · gestite: {{ $addressed }} · ignorate: {{ $dismissed }}
    </p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif

    @forelse($clusters as $cluster)
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:0; margin-bottom:10px;">
            <details>
                <summary style="padding:14px 18px; cursor:pointer; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:0.72rem; font-weight:800; color:#fff; background:#E28A53; border-radius:10px; padding:2px 9px;">{{ $cluster['count'] }}</span>
                    <span style="font-weight:600; color:#1A1F1F; font-size:0.9rem; flex:1;">{{ $cluster['label'] }}</span>
                    @unless($cluster['clustered'])<span style="font-size:0.68rem; color:#8A9696;">(no clustering)</span>@endunless
                </summary>

                <div style="padding:0 18px 16px;">
                    {{-- Azioni sull'intero cluster --}}
                    <form method="POST" action="{{ route('docente.classes.questions.bulk', $class) }}" style="display:flex; gap:8px; margin:0 0 12px;">
                        @csrf
                        @foreach($cluster['questions'] as $q)<input type="hidden" name="question_ids[]" value="{{ $q['id'] }}">@endforeach
                        <button name="status" value="addressed" style="padding:6px 12px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">Segna cluster come gestito</button>
                        <button name="status" value="dismissed" style="padding:6px 12px; background:white; color:#8A9696; border:1px solid #C8D0D0; border-radius:6px; font-size:0.78rem; cursor:pointer;">Ignora cluster</button>
                    </form>

                    {{-- Singole domande --}}
                    @foreach($cluster['questions'] as $q)
                        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:8px 0; border-top:1px solid #F0F2F2;">
                            <div style="font-size:0.85rem; color:#1A1F1F;">
                                {{ $q['text'] }}
                                <div style="font-size:0.72rem; color:#8A9696; margin-top:2px;">
                                    {{ $q['student_name'] ?? 'studente' }} · {{ \Illuminate\Support\Carbon::parse($q['created_at'])->format('d/m H:i') }}
                                    @if($q['best_similarity'] !== null) · sim. migliore {{ number_format($q['best_similarity'], 2) }}@endif
                                </div>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <form method="POST" action="{{ route('docente.questions.update', $q['id']) }}">@csrf @method('PATCH')
                                    <input type="hidden" name="status" value="addressed">
                                    <button title="Gestita" style="padding:4px 9px; background:#E8F5F5; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; cursor:pointer;">&#10003;</button>
                                </form>
                                <form method="POST" action="{{ route('docente.questions.update', $q['id']) }}">@csrf @method('PATCH')
                                    <input type="hidden" name="status" value="dismissed">
                                    <button title="Ignora" style="padding:4px 9px; background:white; color:#8A9696; border:1px solid #C8D0D0; border-radius:6px; font-size:0.75rem; cursor:pointer;">&times;</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    @empty
        <div style="background:white; border:2px dashed #C8D0D0; border-radius:12px; padding:36px; text-align:center; color:#8A9696;">
            <div style="font-size:2rem; margin-bottom:6px;">&#127881;</div>
            Nessuna domanda scoperta aperta. Gli studenti trovano risposta nei materiali della classe.
        </div>
    @endforelse
</div>
@endsection
