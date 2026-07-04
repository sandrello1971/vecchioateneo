@extends('layouts.docente')
@section('title', $lesson->title)
@section('breadcrumb', 'Argomenti / ' . ($lesson->topic->name ?? '') . ' / ' . $lesson->title)
@section('content')
@php
    $readyMaterials = $materials->where('status', 'ready')->filter(fn($m) => trim((string)$m->extracted_text) !== '');
    $canCompose = $readyMaterials->isNotEmpty();
    $meta = (array) $lesson->generation_meta;
    $artifactLabels = [
        'summary' => 'Riassunto', 'mindmap' => 'Mappa mentale', 'conceptmap' => 'Mappa concettuale',
        'quiz' => 'Quiz', 'outline' => 'Scaletta',
    ];
@endphp
<div style="max-width:980px;" x-data="lessonStatus('{{ $lesson->id }}', '{{ $lesson->generation_status }}')">
    <div style="margin-bottom:8px;">
        <a href="{{ route('docente.topics.show', $lesson->topic_id) }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; {{ $lesson->topic->name ?? 'Argomento' }}</a>
    </div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $lesson->title }}</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">{{ $lesson->topic->subject->name ?? '' }}</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Materiali sorgente --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Materiali della lezione ({{ $materials->count() }})</div>
        @forelse($materials as $m)
            @php $mb = ['pending'=>['#8A9696','in coda'],'processing'=>['#E28A53','in elaborazione'],'ready'=>['#3A8C89','pronto'],'failed'=>['#A8521F','fallito']]; [$c,$l]=$mb[$m->status]??['#8A9696',$m->status]; @endphp
            <div style="display:flex; align-items:center; gap:8px; padding:6px 0; border-top:1px solid #F0F2F2; font-size:0.82rem;">
                <span style="color:#3A8C89;">&#128196;</span>
                <a href="{{ route('docente.materials.show', $m) }}" style="flex:1; color:#1A1F1F; text-decoration:none;">{{ $m->title }} <span style="color:#8A9696;">· {{ $m->source_type }}</span></a>
                <span style="font-size:0.7rem; font-weight:700; color:{{ $c }}; border:1px solid {{ $c }}; border-radius:4px; padding:1px 8px;">{{ $l }}</span>
            </div>
        @empty
            <p style="color:#8A9696; font-size:0.85rem;">Nessun materiale assegnato. Caricane uno qui sotto oppure vai all'<a href="{{ route('docente.topics.show', $lesson->topic_id) }}" style="color:#55B1AE;">argomento</a> per classificare il pool.</p>
        @endforelse

        {{-- Upload materiale direttamente nella lezione: nasce già legato a questa lezione,
             materia ereditata dall'argomento. Compare anche nella sezione Materiali. --}}
        <div x-data="{ open: false }" style="margin-top:12px; border-top:1px solid #F0F2F2; padding-top:12px;">
            <button type="button" @click="open = !open" style="padding:7px 14px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">
                <span x-show="!open">&#43; Aggiungi materiale</span>
                <span x-show="open" x-cloak>Chiudi</span>
            </button>
            <div x-show="open" x-cloak style="margin-top:12px;">
                <x-material-upload-form :lesson="$lesson" :video-ai-dpa-missing="$videoAiDpaMissing" :external-types="$externalTypes" />
            </div>
        </div>
    </div>

    {{-- Stato composizione (polling) --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; flex:1;">Corpo della lezione</div>
            <template x-if="status==='generating'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.85rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite;"></span>
                    <span>Composizione in corso…</span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.85rem;">&#10003; Pronta</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.85rem;">&#10007; Composizione fallita</span></template>
            <template x-if="status==='draft'"><span style="color:#8A9696; font-weight:600; font-size:0.85rem;">Bozza</span></template>
        </div>

        @if($lesson->generation_status === 'failed' && ($meta['failure_reason'] ?? null))
            <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $meta['failure_reason'] }}</p>
        @endif

        @if($lesson->generation_status === 'ready' && !empty($meta))
            <div style="margin-top:8px; font-size:0.75rem; color:#8A9696;">
                @isset($meta['model']) modello: {{ $meta['model'] }} @endisset
                @isset($meta['tokens_in']) · token in/out: {{ $meta['tokens_in'] }}/{{ $meta['tokens_out'] ?? 0 }} @endisset
                @isset($meta['sources_count']) · fonti: {{ $meta['sources_count'] }} @endisset
                @if($meta['segments_preserved'] ?? false) · <span title="riferimenti temporali audio/video conservati">timestamp conservati</span> @endif
            </div>
        @endif

        {{-- Azioni di composizione (Feedback UX: data-async, anti-doppio-submit) --}}
        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;" x-show="status!=='generating'">
            @if($lesson->generation_status === 'draft' || $lesson->generation_status === 'failed')
                <form method="POST" action="{{ route('docente.lessons.generate', $lesson) }}" data-async>
                    @csrf
                    <button @disabled(!$canCompose) data-busy-label="Composizione in corso…"
                        style="padding:9px 16px; background:{{ $canCompose ? '#55B1AE' : '#C8D0D0' }}; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:{{ $canCompose ? 'pointer' : 'not-allowed' }};">
                        {{ $lesson->generation_status === 'failed' ? 'Riprova composizione' : 'Componi lezione' }}
                    </button>
                </form>
                @unless($canCompose)<span style="font-size:0.78rem; color:#8A9696; align-self:center;">Serve almeno un materiale pronto con testo.</span>@endunless
            @elseif($lesson->generation_status === 'ready')
                <form method="POST" action="{{ route('docente.lessons.regenerate', $lesson) }}" data-async
                      onsubmit="return confirm('Ricomporre la lezione? Il contenuto attuale (comprese le modifiche manuali) verrà sovrascritto.');">
                    @csrf
                    <button data-busy-label="Ricomposizione…" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Ricomponi (sovrascrive)</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Editor + anteprima del corpo (sempre modificabile dopo la generazione) --}}
    @if($lesson->generation_status === 'ready' || !empty($lesson->content))
        <div x-data="{tab:'edit'}" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <button type="button" @click="tab='edit'" :style="tab==='edit' ? 'background:#1A1F1F;color:white' : 'background:#F0F2F2;color:#4A5252'" style="padding:6px 14px; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Modifica</button>
                <button type="button" @click="tab='preview'" :style="tab==='preview' ? 'background:#1A1F1F;color:white' : 'background:#F0F2F2;color:#4A5252'" style="padding:6px 14px; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Anteprima</button>
            </div>

            <div x-show="tab==='edit'">
                <form method="POST" action="{{ route('docente.lessons.content', $lesson) }}">
                    @csrf @method('PATCH')
                    <textarea name="content" rows="22" style="width:100%; padding:12px; border:1px solid #C8D0D0; border-radius:8px; font-family:ui-monospace,monospace; font-size:0.82rem; line-height:1.5; color:#1A1F1F;">{{ $lesson->content }}</textarea>
                    <div style="margin-top:10px;"><button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Salva modifiche</button></div>
                </form>
            </div>

            <div x-show="tab==='preview'" style="display:none;" x-data="docenteLessonNotes()">
                <p style="font-size:0.78rem; color:#8A9696; margin:0 0 10px;">Passa il mouse su un paragrafo e clicca &#9998; per aggiungere una <strong>nota del docente</strong>: la vedranno tutti gli studenti della classe.</p>
                {{-- Stesso layout/rendering della vista studente (partials condivisi). --}}
                <div class="lesson-card"><div class="lesson-body">{!! $bodyHtml !!}</div></div>
            </div>
        </div>

        {{-- Artefatti a livello di lezione --}}
        @if($lesson->generation_status === 'ready')
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Genera dalla lezione</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @foreach(['summary'=>'Riassunto','outline'=>'Scaletta','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale'] as $t=>$lab)
                    <form method="POST" action="{{ route('docente.lessons.artifacts.generate', $lesson) }}" data-async>
                        @csrf<input type="hidden" name="type" value="{{ $t }}">
                        <button data-busy-label="Genero…" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">{{ $lab }}</button>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('docente.lessons.artifacts.generate', $lesson) }}" data-async style="display:flex; gap:6px; align-items:center;">
                    @csrf<input type="hidden" name="type" value="quiz">
                    <input type="number" name="num_questions" value="10" min="3" max="20" style="width:60px; padding:7px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                    <button data-busy-label="Genero…" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">Quiz</button>
                </form>
            </div>

            @if($artifacts->isNotEmpty())
            <div style="margin-top:12px;">
                @foreach($artifacts as $a)
                    <div x-data="artifactRow('{{ $a->id }}','{{ $a->status }}')" style="display:flex; align-items:center; gap:8px; padding:7px 0; border-top:1px solid #F0F2F2; font-size:0.82rem;">
                        <span style="flex:1;"><a href="{{ route('docente.artifacts.show', $a) }}" style="color:#1A1F1F; text-decoration:none;">{{ $artifactLabels[$a->type] ?? $a->type }} — {{ $a->title }}</a></span>
                        <template x-if="status==='generating'"><span style="color:#E28A53; font-size:0.75rem; font-weight:600;">in corso…</span></template>
                        <template x-if="status==='ready'"><span style="color:#3A8C89; font-size:0.75rem; font-weight:700;">&#10003;</span></template>
                        <template x-if="status==='failed'"><span style="color:#A8521F; font-size:0.75rem; font-weight:700;">&#10007;</span></template>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif
    @endif

    {{-- Presentazione .pptx (P21) — BI-VERSIONE: "Versione online" (pubblicata, ciò che
         vedono gli studenti) + "Bozza in lavorazione" (su cui lavora il formatore).
         Il polling segue lo stato della BOZZA (generazione/correzione). --}}
    @if($lesson->generation_status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;"
         x-data="presentationStatus('{{ $draft?->status ?? 'none' }}')">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em;">Presentazione (.pptx)</div>

        {{-- ===================== VERSIONE ONLINE (pubblicata) ===================== --}}
        @if($published)
            @php $pubUrls = array_map(fn ($i) => route('docente.lessons.presentation.preview', [$lesson, $i]) . '?version=published', range(1, (int) ($published->generation_meta['slides'] ?? 0))); @endphp
            <div style="margin-top:12px; border:1px solid #BFE3D9; background:#F2FAF7; border-radius:8px; padding:12px 14px;">
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span style="display:inline-block; padding:2px 9px; background:#3A8C89; color:white; border-radius:6px; font-size:0.72rem; font-weight:700;">● PUBBLICATA</span>
                    <span style="font-size:0.75rem; color:#3A8C89;">Visibile agli studenti · pubblicata il {{ $published->published_at->format('d/m/Y · H:i') }}</span>
                    @if(($published->generation_meta['slides'] ?? null))<span style="font-size:0.75rem; color:#8A9696;">· {{ $published->generation_meta['slides'] }} slide</span>@endif
                    <span style="flex:1;"></span>
                    <form method="POST" action="{{ route('docente.lessons.presentation.unpublish', $lesson) }}"
                          onsubmit="return confirm('Ritirare la presentazione? Gli studenti non la vedranno più.') && (this.querySelector('button').disabled=true || true);">
                        @csrf
                        <button style="padding:7px 13px; background:white; color:#A8521F; border:1px solid #A8521F; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">Ritira</button>
                    </form>
                </div>
                @if(($published->generation_meta['slides'] ?? 0) > 0)
                    <div style="margin-top:10px;"><x-slide-lightbox :images="$pubUrls" /></div>
                @endif
            </div>
        @endif

        {{-- ===================== BOZZA (in lavorazione) ===================== --}}
        @php
            $draftUrls = $draft ? array_map(fn ($i) => route('docente.lessons.presentation.preview', [$lesson, $i]) . '?version=draft', range(1, (int) ($draft->generation_meta['slides'] ?? 0))) : [];
            $canEdit = ($draft && !empty($draft->spec)) || (!$draft && $published && !empty($published->spec));
        @endphp
        <div style="margin-top:14px;">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                @if($draft)
                    <span style="display:inline-block; padding:2px 9px; background:#F5E6B8; color:#7A5C00; border-radius:6px; font-size:0.72rem; font-weight:700;">BOZZA</span>
                @else
                    <span style="font-size:0.8rem; color:#8A9696;">{{ $published ? 'Nessuna bozza in lavorazione.' : 'Nessuna presentazione: generala o caricala.' }}</span>
                @endif
                <template x-if="status==='generating'">
                    <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.82rem; font-weight:600;">
                        <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite;"></span><span>In corso…</span>
                    </span>
                </template>
                @if($draft && $draft->status === 'ready' && ($draft->generation_meta['slides'] ?? null))
                    <span style="font-size:0.75rem; color:#8A9696;">{{ $draft->generation_meta['slides'] }} slide</span>
                @endif
                @if($draft && ($draft->source ?? 'generated') === 'uploaded')
                    <span style="display:inline-block; padding:2px 8px; background:#EEF3F3; color:#3A8C89; border-radius:6px; font-size:0.72rem; font-weight:700;">Versione caricata</span>
                @endif
            </div>

            @if($draft && $draft->status === 'failed' && ($draft->generation_meta['failure_reason'] ?? null))
                <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $draft->generation_meta['failure_reason'] }}</p>
            @endif

            {{-- Azioni (nascoste durante generating; x-show sul wrapper, flex sul figlio) --}}
            <div style="margin-top:12px;" x-show="status!=='generating'">
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                @if(!$draft || in_array($draft->status, ['pending', 'failed']))
                    <form method="POST" action="{{ route('docente.lessons.presentation.generate', $lesson) }}" data-async>
                        @csrf
                        <button data-busy-label="Generazione…" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">{{ ($draft?->status ?? null) === 'failed' ? 'Riprova generazione' : 'Genera bozza' }}</button>
                    </form>
                @elseif($draft->status === 'ready')
                    <form method="POST" action="{{ route('docente.lessons.presentation.publish', $lesson) }}"
                          onsubmit="return confirm('Pubblicare questa bozza? Sostituirà la versione online attuale e sarà visibile agli studenti.') && (this.querySelector('button').disabled=true || true);">
                        @csrf
                        <button style="padding:9px 16px; background:#3A8C89; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">&#10003; Pubblica</button>
                    </form>
                    <a href="{{ route('docente.lessons.presentation.download', $lesson) }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 16px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11015; Scarica</a>
                    <form method="POST" action="{{ route('docente.lessons.presentation.regenerate', $lesson) }}" data-async
                          onsubmit="return confirm('Rigenerare la bozza? Il file della bozza verrà sovrascritto.');">
                        @csrf
                        <button data-busy-label="Rigenerazione…" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Rigenera</button>
                    </form>
                @endif

                {{-- Carica una propria versione .pptx come bozza --}}
                <form method="POST" action="{{ route('docente.lessons.presentation.upload', $lesson) }}" enctype="multipart/form-data"
                      style="display:inline-flex; align-items:center; gap:6px;"
                      onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Caricamento…';">
                    @csrf
                    <input type="file" name="presentation" accept=".pptx" required style="font-size:0.78rem; max-width:190px;">
                    <button style="padding:9px 14px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">Carica .pptx</button>
                </form>

                {{-- Elimina (bozza se presente, altrimenti la pubblicata) --}}
                @if(($draft && $draft->status !== 'pending') || (!$draft && $published))
                    <form method="POST" action="{{ route('docente.lessons.presentation.destroy', $lesson) }}"
                          onsubmit="return confirm('Eliminare {{ $draft ? 'la bozza' : 'la presentazione' }}? Operazione non reversibile.') && (this.querySelector('button').disabled=true || true);">
                        @csrf @method('DELETE')
                        <button style="padding:9px 14px; background:white; color:#A8521F; border:1px solid #A8521F; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">{{ $draft ? 'Elimina bozza' : 'Elimina' }}</button>
                    </form>
                @endif
            </div>
            </div>

            {{-- Anteprima della BOZZA (galleria + lightbox) --}}
            @if($draft && $draft->status === 'ready' && ($draft->generation_meta['slides'] ?? 0) > 0)
                <div style="margin-top:16px; border-top:1px solid #F0F2F2; padding-top:14px;">
                    <div style="font-size:0.72rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Anteprima bozza</div>
                    <x-slide-lightbox :images="$draftUrls" />
                </div>
            @endif

            {{-- Correzione via prompt: bozza con spec, oppure (senza bozza) pubblicata con spec → crea una bozza --}}
            @if($canEdit)
                <div style="margin-top:16px; border-top:1px solid #F0F2F2; padding-top:14px;" x-show="status!=='generating'">
                    <div style="font-size:0.72rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Correggi le slide (sulla bozza)</div>
                    <form method="POST" action="{{ route('docente.lessons.presentation.edit', $lesson) }}" data-async>
                        @csrf
                        <textarea name="instruction" rows="2" maxlength="2000" required
                                  placeholder="Descrivi la modifica (es. «Nella slide 3 aggiungi un esempio pratico»)"
                                  style="width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; resize:vertical;"></textarea>
                        <button data-busy-label="Correzione…" style="margin-top:8px; padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Applica correzione</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- V1 — Video narrato: copione (draft) dalla presentazione PUBBLICATA. UI minima. --}}
    @if($lesson->generation_status === 'ready' && $published)
        @php $lessonVideo = $lesson->videos()->where('presentation_id', $published->id)->latest()->first(); @endphp
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;"
             x-data="{ s: '{{ $lessonVideo?->status ?? 'none' }}' }"
             x-init="if (s === 'generating') { const t = setInterval(async () => { const r = await fetch('{{ route('docente.lessons.video.status', $lesson) }}'); const j = await r.json(); if (j.status !== 'generating') { clearInterval(t); location.reload(); } }, 4000); }">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; flex:1;">Video narrato</div>
                @if(($lessonVideo?->script_status ?? 'none') === 'draft')
                    <span style="display:inline-block; padding:2px 9px; background:#F5E6B8; color:#7A5C00; border-radius:6px; font-size:0.72rem; font-weight:700;">Copione: bozza</span>
                @endif
            </div>

            <template x-if="s === 'generating'">
                <p style="margin-top:8px; font-size:0.82rem; color:#E28A53;">Generazione del copione in corso… la pagina si aggiorna da sola.</p>
            </template>

            @if(($lessonVideo?->status ?? null) === 'failed' && ($lessonVideo->generation_meta['failure_reason'] ?? null))
                <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $lessonVideo->generation_meta['failure_reason'] }}</p>
            @endif

            <div style="margin-top:12px;" x-show="s !== 'generating'">
                <form method="POST" action="{{ route('docente.lessons.video.script', $lesson) }}" data-async>
                    @csrf
                    <button data-busy-label="Preparazione…" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">{{ ($lessonVideo?->script_status ?? 'none') === 'draft' ? 'Rigenera copione' : 'Prepara copione video' }}</button>
                </form>
                <p style="margin-top:6px; font-size:0.72rem; color:#8A9696;">Il copione resta in bozza: nessun costo voce finché non lo confermi.</p>
            </div>

            {{-- V2 — revisione copione: anteprima slide + testo affiancati, correzione a mano e via prompt --}}
            @if(in_array($lessonVideo?->script_status ?? 'none', ['draft', 'confirmed'], true) && !empty($lessonVideo->script))
                <div style="margin-top:14px; border-top:1px solid #F0F2F2; padding-top:12px;" x-data="{ zoom: null }">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                        <div style="font-size:0.72rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.05em;">Copione per slide</div>
                        <span style="flex:1;"></span>
                        @if($lessonVideo->script_status === 'confirmed')
                            <span style="display:inline-block; padding:2px 9px; background:#3A8C89; color:white; border-radius:6px; font-size:0.72rem; font-weight:700;">Copione confermato</span>
                        @else
                            <form method="POST" action="{{ route('docente.lessons.video.confirm', $lesson) }}"
                                  onsubmit="return confirm('Confermare il copione? Potrai sempre rimodificarlo (tornerà in bozza).') && (this.querySelector('button').disabled=true || true);">
                                @csrf
                                <button style="padding:7px 13px; background:#3A8C89; color:white; border:none; border-radius:8px; font-size:0.8rem; font-weight:700; cursor:pointer;">&#10003; Conferma copione</button>
                            </form>
                        @endif
                    </div>

                    @foreach($lessonVideo->script as $line)
                        @php $sn = (int) ($line['slide_number'] ?? 0); $pv = route('docente.lessons.presentation.preview', [$lesson, $sn]) . '?version=published'; @endphp
                        <div style="display:flex; gap:12px; padding:12px 0; border-top:1px solid #F4F6F6;">
                            <img src="{{ $pv }}" alt="Slide {{ $sn }}" loading="lazy" @click="zoom = '{{ $pv }}'"
                                 style="width:200px; aspect-ratio:16/9; object-fit:contain; background:#0A0A0A; border:1px solid #C8D0D0; border-radius:6px; cursor:zoom-in; flex-shrink:0;">
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:0.72rem; font-weight:700; color:#8A9696; margin-bottom:4px;">Slide {{ $sn }}</div>
                                <form method="POST" action="{{ route('docente.lessons.video.line', $lesson) }}"
                                      onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Salvataggio…';">
                                    @csrf
                                    <input type="hidden" name="slide_number" value="{{ $sn }}">
                                    <textarea name="text" rows="3" maxlength="3000" style="width:100%; box-sizing:border-box; padding:7px 9px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem; resize:vertical;">{{ $line['text'] ?? '' }}</textarea>
                                    <button style="margin-top:5px; padding:6px 12px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:7px; font-size:0.78rem; font-weight:600; cursor:pointer;">Salva</button>
                                </form>
                                <form method="POST" action="{{ route('docente.lessons.video.line.prompt', $lesson) }}"
                                      style="margin-top:6px; display:flex; gap:6px;"
                                      onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='…';">
                                    @csrf
                                    <input type="hidden" name="slide_number" value="{{ $sn }}">
                                    <input type="text" name="instruction" maxlength="2000" required placeholder="Ritocca con un'istruzione (es. «rendila più discorsiva»)"
                                           style="flex:1; min-width:0; padding:6px 9px; border:1px solid #C8D0D0; border-radius:7px; font-size:0.8rem;">
                                    <button style="padding:6px 12px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:7px; font-size:0.78rem; font-weight:600; cursor:pointer;">Ritocca</button>
                                </form>
                            </div>
                        </div>
                    @endforeach

                    {{-- zoom anteprima --}}
                    <div x-show="zoom" x-cloak @click="zoom = null" @keydown.escape.window="zoom = null"
                         style="position:fixed; inset:0; z-index:1000; background:rgba(10,10,10,0.92); display:flex; align-items:center; justify-content:center;">
                        <img :src="zoom" alt="" style="max-width:90vw; max-height:88vh; object-fit:contain;">
                    </div>
                </div>
            @endif

            {{-- V3 — render MP4: da copione confermato. Pronto → scarica. --}}
            @if(($lessonVideo?->status ?? null) === 'ready' && $lessonVideo->file_path)
                <div style="margin-top:14px; border-top:1px solid #F0F2F2; padding-top:12px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <span style="display:inline-block; padding:2px 9px; background:#3A8C89; color:white; border-radius:6px; font-size:0.72rem; font-weight:700;">🎬 Video pronto</span>
                    @isset($lessonVideo->generation_meta['seconds'])<span style="font-size:0.75rem; color:#8A9696;">{{ (int) $lessonVideo->generation_meta['seconds'] }}s</span>@endisset
                    @if($lessonVideo->published_at)
                        <span style="display:inline-block; padding:2px 9px; background:#3A8C89; color:white; border-radius:6px; font-size:0.72rem; font-weight:700;">Pubblicato</span>
                    @else
                        <span style="display:inline-block; padding:2px 9px; background:#F5E6B8; color:#7A5C00; border-radius:6px; font-size:0.72rem; font-weight:700;">Bozza</span>
                    @endif
                    <span style="flex:1;"></span>
                    <a href="{{ route('docente.lessons.video.download', $lesson) }}" style="padding:7px 13px; background:white; color:#3A8C89; border:1px solid #3A8C89; border-radius:8px; font-size:0.8rem; font-weight:600; text-decoration:none;">&#11015; Scarica</a>
                    @if($lessonVideo->published_at)
                        <form method="POST" action="{{ route('docente.lessons.video.unpublish', $lesson) }}"
                              onsubmit="return confirm('Ritirare il video? Gli studenti non lo vedranno più.') && (this.querySelector('button').disabled=true || true);">
                            @csrf
                            <button style="padding:7px 13px; background:white; color:#A8521F; border:1px solid #A8521F; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">Ritira</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('docente.lessons.video.publish', $lesson) }}"
                              onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Pubblicazione…';">
                            @csrf
                            <button style="padding:7px 14px; background:#3A8C89; color:white; border:none; border-radius:8px; font-size:0.8rem; font-weight:700; cursor:pointer;">&#10003; Pubblica</button>
                        </form>
                    @endif
                </div>
                @unless($lessonVideo->published_at)
                    <p style="font-size:0.72rem; color:#8A9696;">Pubblicando, il video viene anche indicizzato (ricercabile). Indicizzazione gratuita per i video generati.</p>
                @endunless
            @endif
            @if(($lessonVideo?->script_status ?? 'none') === 'confirmed')
                <div style="margin-top:12px; border-top:1px solid #F0F2F2; padding-top:12px;" x-show="s !== 'generating'">
                    <form method="POST" action="{{ route('docente.lessons.video.generate', $lesson) }}" data-async
                          onsubmit="return confirm('Generare il video? Verrà sintetizzata la voce (ha un costo) e composto l\'mp4.');">
                        @csrf
                        <button data-busy-label="Generazione…" style="padding:9px 16px; background:#A6192E; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">🎬 {{ ($lessonVideo?->status ?? null) === 'ready' ? 'Rigenera video' : 'Genera video' }}</button>
                    </form>
                    <p style="margin-top:6px; font-size:0.72rem; color:#8A9696;">La voce TTS ha un costo: si genera solo dal copione confermato.</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Pubblicazione su classi (P20a) — Feedback UX: rag_status + polling --}}
    @if($lesson->generation_status === 'ready')
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;" x-data="lessonPublications('{{ $lesson->id }}')">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Pubblica agli studenti</div>

        @if($teacherClasses->isEmpty())
            <p style="color:#8A9696; font-size:0.85rem;">Nessuna classe disponibile: ti serve una cattedra (o una classe libera) per pubblicare.</p>
        @else
        <form method="POST" action="{{ route('docente.lessons.publish', $lesson) }}" data-async>
            @csrf
            <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:10px;">
                @foreach($teacherClasses as $class)
                    @php $pub = $lesson->publications->firstWhere('school_class_id', $class->id); @endphp
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; color:#1A1F1F;">
                        <input type="checkbox" name="class_ids[]" value="{{ $class->id }}" @checked($pub)>
                        <span style="flex:1;">{{ $class->name }} @if($class->school_id)<span style="color:#8A9696; font-size:0.75rem;">· scuola</span>@else<span style="color:#8A9696; font-size:0.75rem;">· libera</span>@endif</span>
                        @if($pub)
                            @php $rs = ['pending'=>['#8A9696','in coda'],'indexing'=>['#E28A53','indicizzazione…'],'ready'=>['#3A8C89','pubblicata'],'failed'=>['#A8521F','fallita']]; [$c,$l]=$rs[$pub->rag_status]??['#8A9696',$pub->rag_status]; @endphp
                            <span data-pub-class="{{ $class->id }}" style="font-size:0.72rem; font-weight:700; color:{{ $c }}; border:1px solid {{ $c }}; border-radius:4px; padding:1px 8px;">{{ $l }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <label style="display:flex; align-items:center; gap:8px; font-size:0.82rem; color:#4A5252; margin-bottom:10px;">
                <input type="checkbox" name="students_can_generate" value="1" checked>
                Gli studenti possono generare quiz/autoverifica dalla lezione
            </label>
            <button data-busy-label="Pubblicazione…" style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Pubblica / aggiorna</button>
        </form>

        @if($lesson->publications->isNotEmpty())
        <div style="margin-top:12px; border-top:1px solid #F0F2F2; padding-top:10px;">
            <div style="font-size:0.72rem; color:#8A9696; margin-bottom:6px;">Pubblicazioni attive — il ritiro rimuove i contenuti dalla classe (RAG).</div>
            @foreach($lesson->publications as $pub)
                <div style="display:flex; align-items:center; gap:8px; padding:5px 0; font-size:0.82rem;">
                    <span style="flex:1; color:#1A1F1F;">{{ $pub->schoolClass->name ?? '—' }}</span>
                    <form method="POST" action="{{ route('docente.lesson-publications.destroy', $pub) }}" data-async onsubmit="return confirm('Ritirare la pubblicazione da questa classe? Gli studenti non vedranno più la lezione.');">
                        @csrf @method('DELETE')
                        <button data-busy-label="Ritiro…" style="border:none; background:none; color:#A8521F; cursor:pointer; font-size:0.78rem;">ritira</button>
                    </form>
                </div>
            @endforeach
        </div>
        @endif
        @endif
    </div>
    @endif
</div>

@push('styles')<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>@endpush
{{-- Stessa tipografia + KaTeX della vista studente: l'anteprima è IDENTICA. --}}
@include('schola.partials.lesson-typography')
@include('schola.partials.lesson-katex')
@pushOnce('scripts', 'alpine-cdn')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endPushOnce
@push('scripts')
<script>
function lessonStatus(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/lezioni/${id}/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') {
                        clearInterval(timer);
                        window.location.reload();
                    }
                } catch(e) {}
            }, 5000);
        },
    };
}
function artifactRow(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/artefatti/${id}/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') clearInterval(timer);
                } catch(e) {}
            }, 5000);
        },
    };
}
// Note del docente per paragrafo (didattiche, visibili agli studenti).
function docenteLessonNotes() {
    const csrf = '{{ csrf_token() }}';
    const saveUrl = '{{ route('docente.lessons.teacher-notes.save', $lesson) }}';
    const initial = @json($teacherNotes->map->content);
    return {
        notes: initial,
        init() { this.decorate(); },
        decorate() {
            this.$el.querySelectorAll('.lesson-body [data-note-anchor]').forEach(el => {
                const anchor = el.getAttribute('data-note-anchor');
                if (!el.querySelector('.note-tab')) {
                    const btn = document.createElement('button');
                    btn.className = 'note-tab';
                    btn.innerHTML = '&#9998;';
                    btn.title = 'Nota del docente';
                    btn.addEventListener('click', () => this.edit(anchor));
                    el.prepend(btn);
                }
                this.render(el, anchor);
            });
        },
        render(el, anchor) {
            const tab = el.querySelector('.note-tab');
            el.querySelectorAll('.note-teacher').forEach(n => n.remove());
            if (this.notes[anchor]) {
                if (tab) tab.classList.add('has-note');
                const t = document.createElement('div');
                t.className = 'note-teacher';
                t.innerHTML = '<span class="note-teacher-label">&#128221; Nota del docente</span>';
                const b = document.createElement('div'); b.textContent = this.notes[anchor]; t.appendChild(b);
                el.appendChild(t);
            } else if (tab) { tab.classList.remove('has-note'); }
        },
        async edit(anchor) {
            const current = this.notes[anchor] || '';
            const val = window.prompt('Nota del docente per questo paragrafo (vuoto = elimina):', current);
            if (val === null) return;
            try {
                await fetch(saveUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({anchor, content: val}),
                });
                if (val.trim() === '') delete this.notes[anchor]; else this.notes[anchor] = val;
            } catch(e) {}
            const el = this.$el.querySelector(`.lesson-body [data-note-anchor="${anchor}"]`);
            if (el) this.render(el, anchor);
        },
    };
}
// Polling stato presentazione (.pptx) generating→ready/failed.
function presentationStatus(initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch('{{ route('docente.lessons.presentation.status', $lesson) }}', {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') { clearInterval(timer); window.location.reload(); }
                } catch(e) {}
            }, 5000);
        },
    };
}
// Polling stato pubblicazioni (rag_status pending→indexing→ready/failed).
function lessonPublications(lessonId) {
    const LABELS = {pending:['#8A9696','in coda'],indexing:['#E28A53','indicizzazione…'],ready:['#3A8C89','pubblicata'],failed:['#A8521F','fallita']};
    return {
        init() {
            const pending = () => Array.from(this.$el.querySelectorAll('[data-pub-class]'))
                .some(el => !el.textContent.includes('pubblicata') && !el.textContent.includes('fallita'));
            if (!pending()) return;
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/lezioni/${lessonId}/pubblicazioni/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    let allDone = true;
                    d.publications.forEach(p => {
                        const el = this.$el.querySelector(`[data-pub-class="${p.school_class_id}"]`);
                        const [c,l] = LABELS[p.rag_status] || ['#8A9696', p.rag_status];
                        if (el) { el.textContent = l; el.style.color = c; el.style.borderColor = c; }
                        if (p.rag_status !== 'ready' && p.rag_status !== 'failed') allDone = false;
                    });
                    if (allDone) clearInterval(timer);
                } catch(e) {}
            }, 4000);
        },
    };
}
</script>
@endpush
@endsection
