@extends('layouts.student')
@section('title', $module->title)
@section('breadcrumb', $course->name . ' > ' . $module->title)

@section('content')
<div id="reading-progress" style="position:fixed;top:0;left:240px;right:0;height:3px;background:#E8F5F5;z-index:50;">
    <div id="reading-bar" style="height:100%;background:#55B1AE;width:0%;transition:width 0.1s;"></div>
</div>

<div style="display:grid; grid-template-columns:1fr 280px; gap:24px; max-width:1100px;">

    <div>
        <a href="/learn/course/{{ $course->slug }}"
           style="display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; text-decoration:none; margin-bottom:12px;">
            ← Torna al corso
        </a>

        @if(!empty($teaching))
        <div style="background:#E8F5F5; border:1px solid #C8D0D0; border-left:4px solid #E28A53; border-radius:8px; padding:10px 14px; margin-bottom:14px; color:#5A6464; font-size:0.82rem; line-height:1.5;">
            👁 <strong style="color:#1A1F1F;">Modalità docenza</strong> — Anteprima del modulo come lo vede il discente. Nessun avanzamento registrato.
        </div>
        @endif

        <div style="background:white; border-radius:12px; padding:24px; margin-bottom:20px;">
            <div style="color:#8A9696; font-size:0.75rem; margin-bottom:8px;">
                <a href="/learn/course/{{ $course->slug }}" style="color:#55B1AE;">{{ $course->name }}</a>
                &rsaquo; {{ $module->title }}
            </div>
            <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:4px;">{{ $module->title }}</h1>
            @if($module->duration_minutes)
            <div style="color:#8A9696; font-size:0.8rem;">&#9201; {{ $module->duration_minutes }} minuti</div>
            @endif
        </div>

        @if($course->video_ai_id)
            @include('student.course._video-player', [
                'videoId' => $course->video_ai_id,
                'scope' => 'course',
                'courseSlug' => $course->slug,
                'moduleId' => null,
                'label' => 'Video introduttivo al corso',
            ])
        @endif

        @if($module->video_ai_id)
            @include('student.course._video-player', [
                'videoId' => $module->video_ai_id,
                'scope' => 'module',
                'courseSlug' => $course->slug,
                'moduleId' => $module->id,
                'label' => $course->video_ai_id ? 'Video del modulo' : null,
            ])
        @endif

        @if($module->content)
        <div style="background:white; border-radius:12px; padding:28px; margin-bottom:20px;">
            <style>
                .module-content { font-family: 'Calibri', system-ui, sans-serif; line-height:1.8; color:#1A1F1F; }
                .module-content h2 { font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:2rem 0 1rem; padding-bottom:0.5rem; border-bottom:2px solid #E8F5F5; }
                .module-content h3 { font-size:1.1rem; font-weight:700; color:#3A8C89; margin:1.5rem 0 0.75rem; }
                .module-content h4 { font-size:1rem; font-weight:700; color:#4A5252; margin:1.2rem 0 0.5rem; }
                .module-content p { margin:0.75rem 0; font-size:0.95rem; color:#1A1F1F; }
                .module-content ul, .module-content ol { margin:0.75rem 0 0.75rem 1.5rem; }
                .module-content li { margin:0.4rem 0; font-size:0.95rem; color:#1A1F1F; line-height:1.7; }
                .module-content strong, .module-content b { color:#1A1F1F; font-weight:700; }
                .module-content em, .module-content i { color:#4A5252; font-style:italic; }
                .module-content blockquote {
                    margin:1.5rem 0; padding:1rem 1.5rem;
                    background:#E8F5F5; border-left:4px solid #55B1AE;
                    border-radius:0 8px 8px 0; color:#3A8C89; font-style:italic;
                }
                .module-content table { width:100%; border-collapse:collapse; margin:1.5rem 0; font-size:0.875rem; }
                .module-content th { background:#E8F5F5; color:#3A8C89; padding:10px 14px; text-align:left; font-weight:700; border:1px solid #C8D0D0; }
                .module-content td { padding:10px 14px; border:1px solid #C8D0D0; color:#1A1F1F; vertical-align:top; }
                .module-content tr:nth-child(even) td { background:#F5F7F7; }
                .module-content hr { border:none; border-top:1px solid #E8F5F5; margin:2rem 0; }
                .module-content a { color:#55B1AE; text-decoration:underline; }
                .module-content code { background:#F5F7F7; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:0.875rem; color:#E28A53; }
                .module-content pre { background:#1A1F1F; color:#E8EDED; padding:16px; border-radius:8px; overflow-x:auto; margin:1rem 0; }
            </style>
            <div class="module-content"
                 x-data="anchoredNotes({
                    moduleId: '{{ $module->id }}',
                    csrf: '{{ csrf_token() }}',
                    isDemo: {{ $isDemo ? 'true' : 'false' }},
                    initial: {{ Js::from($studentNotes->where('anchor', '!=', null)->values()) }}
                 })"
                 x-init="init()"
                 x-ref="contentRoot">
                <div x-ref="contentBody">
                    {!! $module->content !!}
                </div>

                @unless($isDemo)
                {{-- FAB floating "Mie note" --}}
                <button x-show="totalAnchored > 0" type="button"
                        @click="panelOpen = !panelOpen"
                        style="position:fixed; top:90px; right:20px; z-index:60;
                               padding:10px 16px; background:#E28A53; color:white;
                               border:none; border-radius:24px; cursor:pointer;
                               font-size:0.85rem; font-weight:700;
                               box-shadow:0 4px 12px rgba(226,138,83,0.35);
                               display:flex; align-items:center; gap:8px;"
                        title="Mostra le tue note ancorate">
                    📝 <span x-text="totalAnchored"></span>
                </button>

                {{-- Backdrop --}}
                <div x-show="panelOpen" x-transition.opacity
                     @click="panelOpen = false"
                     style="position:fixed; inset:0; background:rgba(26,31,31,0.4); z-index:65; cursor:pointer;"></div>

                {{-- Pannello laterale --}}
                <aside x-show="panelOpen" x-transition
                       style="position:fixed; top:0; right:0; bottom:0; width:340px; max-width:90vw;
                              background:white; box-shadow:-8px 0 24px rgba(26,31,31,0.15);
                              z-index:70; display:flex; flex-direction:column;">
                    <header style="padding:16px 20px; border-bottom:1px solid #E8F5F5;
                                   display:flex; align-items:center; gap:10px;">
                        <span style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">📝 Le tue note</span>
                        <span style="color:#8A9696; font-size:0.85rem;" x-text="'(' + totalAnchored + ')'"></span>
                        <button @click="panelOpen = false" type="button"
                                style="margin-left:auto; background:none; border:none;
                                       font-size:1.4rem; cursor:pointer; color:#8A9696; line-height:1;">×</button>
                    </header>
                    <div style="flex:1; overflow-y:auto; padding:16px 20px;">
                        <template x-if="notes.length === 0">
                            <div style="color:#8A9696; font-size:0.85rem; text-align:center; padding:40px 0;">
                                Nessuna nota ancorata.<br>
                                Passa il mouse su un paragrafo e clicca <strong>+</strong> per crearne una.
                            </div>
                        </template>
                        <template x-for="note in notes" :key="note.anchor">
                            <article style="background:#F5F7F7; border-radius:8px; padding:12px; margin-bottom:10px;
                                            border-left:3px solid #E28A53;">
                                <div style="font-size:0.7rem; color:#D87840; font-weight:700; margin-bottom:4px;"
                                     x-text="'📍 ' + note.anchor.toUpperCase()"></div>
                                <div style="font-size:0.85rem; color:#1A1F1F; line-height:1.5;
                                            white-space:pre-wrap; margin-bottom:8px;"
                                     x-text="note.content"></div>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" @click="goToAnchor(note.anchor); panelOpen = false"
                                            style="padding:4px 10px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:5px; font-size:0.7rem; font-weight:600; cursor:pointer;">↗ vai</button>
                                    <button type="button" @click="openPopoverFor(note.anchor); panelOpen = false"
                                            style="padding:4px 10px; background:white; color:#5A6464; border:1px solid #C8D0D0; border-radius:5px; font-size:0.7rem; cursor:pointer;">✎ modifica</button>
                                    <button type="button" @click="if (confirm('Cancellare questa nota?')) deleteNote(note.anchor)"
                                            style="padding:4px 10px; background:white; color:#C52A2A; border:1px solid #E28282; border-radius:5px; font-size:0.7rem; cursor:pointer;">🗑</button>
                                </div>
                            </article>
                        </template>
                    </div>
                </aside>

                {{-- Popover scrittura nota --}}
                <div x-show="popoverAnchor" x-transition
                     style="position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
                            width:480px; max-width:calc(100vw - 32px); z-index:75;
                            background:white; border-radius:12px; padding:16px;
                            box-shadow:0 8px 32px rgba(26,31,31,0.25); border:1px solid #E8F5F5;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                        <span style="font-weight:700; color:#1A1F1F; font-size:0.85rem;">📝 Nota a</span>
                        <span style="background:#E28A53; color:white; padding:2px 8px; border-radius:6px;
                                     font-size:0.7rem; font-weight:700; font-family:monospace;"
                              x-text="popoverAnchor?.toUpperCase()"></span>
                        <button type="button" @click="popoverAnchor = null"
                                style="margin-left:auto; background:none; border:none;
                                       color:#8A9696; cursor:pointer; font-size:1.2rem; line-height:1;">×</button>
                    </div>
                    <textarea x-model="popoverContent"
                              x-ref="popoverTextarea"
                              @keydown.ctrl.enter.prevent="savePopover()"
                              @keydown.meta.enter.prevent="savePopover()"
                              placeholder="Scrivi la tua nota… (Ctrl+Invio per salvare)"
                              style="width:100%; min-height:100px; padding:10px;
                                     border:1px solid #C8D0D0; border-radius:6px;
                                     font-size:0.85rem; line-height:1.5; resize:vertical;
                                     outline:none; font-family:inherit;"></textarea>
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                        <span style="font-size:0.75rem; color:#8A9696; margin-right:auto;"
                              x-text="popoverStatus"></span>
                        <button type="button" @click="popoverAnchor = null"
                                style="padding:6px 14px; background:white; color:#5A6464;
                                       border:1px solid #C8D0D0; border-radius:6px;
                                       font-size:0.8rem; cursor:pointer;">Annulla</button>
                        <button type="button" @click="savePopover()"
                                style="padding:6px 16px; background:#55B1AE; color:white;
                                       border:none; border-radius:6px; font-size:0.8rem;
                                       font-weight:600; cursor:pointer;">💾 Salva</button>
                    </div>
                </div>
                @endunless
            </div>

            <style>
                .annotated-block { display:flex; align-items:flex-start; gap:0; }
                .annotated-block > *:not(.note-pin-wrapper) { flex:1; min-width:0; }
                .note-pin-wrapper { width:0; flex-shrink:0; align-self:stretch; position:relative; }
                .note-pin {
                    position:sticky; top:84px; left:-36px;
                    width:28px; height:28px;
                    background:white; border:1.5px solid #C8D0D0; border-radius:50%;
                    color:#8A9696; font-size:1rem; font-weight:700;
                    cursor:pointer; opacity:0; transition:opacity 0.15s, background 0.15s;
                    display:flex; align-items:center; justify-content:center;
                    z-index:5; padding:0; line-height:1;
                    transform:translateX(-36px);
                }
                .annotated-block:hover .note-pin { opacity:1; }
                .note-pin.has-note { opacity:1; background:#E28A53; border-color:#E28A53; color:white; }
                .note-pin:hover { background:#3A8C89; border-color:#3A8C89; color:white; }
                .note-pin.has-note:hover { background:#D87840; border-color:#D87840; }
                .module-content [data-note-anchor].anchor-flash {
                    animation: anchorFlash 1.8s ease-out;
                }
                @keyframes anchorFlash {
                    0%   { background:rgba(226,138,83,0); }
                    20%  { background:rgba(226,138,83,0.25); }
                    100% { background:rgba(226,138,83,0); }
                }
            </style>
        </div>
        @else
        <div style="background:white; border-radius:12px; padding:28px; margin-bottom:20px; color:#8A9696; text-align:center;">
            <div style="font-size:2rem; margin-bottom:8px;">&#128196;</div>
            <p>Il contenuto di questo modulo sara disponibile a breve.</p>
            @if($module->description)
            <p style="margin-top:12px; font-size:0.85rem; line-height:1.6; color:#4A5252;">{{ $module->description }}</p>
            @endif
        </div>
        @endif

        @if(isset($instructorManualSections) && $instructorManualSections->isNotEmpty())
        <div style="background:linear-gradient(135deg, rgba(226,138,83,0.08), rgba(226,138,83,0.12));
                    border:1px solid rgba(226,138,83,0.3);
                    border-radius:12px; padding:18px; margin-bottom:20px;">

            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                <div style="font-size:1.3rem;">🎓</div>
                <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">
                    Sezioni del Manuale Formatore per questo modulo
                </div>
                <div style="margin-left:auto; padding:3px 10px;
                            background:rgba(226,138,83,0.2); color:#D87840;
                            border-radius:12px; font-size:0.7rem; font-weight:700;">
                    SOLO DOCENTI
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:8px;">
                @foreach($instructorManualSections as $section)
                <div style="background:white; border-radius:8px; padding:14px;">
                    <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem; margin-bottom:6px;">
                        {{ $section->title }}
                    </div>
                    <div style="color:#5A6464; font-size:0.8rem; line-height:1.5;
                                max-height:80px; overflow:hidden; position:relative;
                                margin-bottom:10px;">
                        {{ mb_substr(strip_tags($section->content_html), 0, 280) }}…
                    </div>
                    <a href="{{ route('student.instructor.material.show', [$course->slug, $section->material_id]) }}#{{ $section->anchor }}"
                       style="display:inline-block; padding:6px 14px; background:#E28A53;
                              color:white; border-radius:6px; text-decoration:none;
                              font-size:0.8rem; font-weight:600;">
                        📖 Apri sezione nel manuale completo
                    </a>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(isset($instructorNotes) && session('student_id'))
        @php
            $loggedStudent = \App\Models\Student::find(session('student_id'));
        @endphp
        @if($loggedStudent && $loggedStudent->isInstructor())
        <div style="background:white; border:1px solid #E8F5F5; border-radius:12px; padding:20px; margin:20px 0;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                <div style="font-size:1.3rem;">📓</div>
                <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">
                    Note formatore per questo modulo ({{ $instructorNotes->count() }})
                </div>
                <a href="{{ route('student.knowledge_base.create', ['course_id' => $course->id, 'module_id' => $module->id, 'return_url' => url()->current()]) }}"
                   style="margin-left:auto; padding:6px 14px; background:#55B1AE; color:white;
                          border-radius:6px; text-decoration:none; font-size:0.78rem; font-weight:600;">
                    + Aggiungi nota
                </a>
            </div>

            @if($instructorNotes->isEmpty())
            <div style="color:#8A9696; font-size:0.85rem; padding:20px; text-align:center;">
                Nessuna nota per questo modulo. Crea la prima.
            </div>
            @else
            <div style="display:flex; flex-direction:column; gap:8px;">
                @foreach($instructorNotes as $n)
                <div style="border:1px solid #F0F4F4; border-radius:8px; padding:12px;
                            border-left:3px solid {{ $n->is_shared ? '#E28A53' : '#55B1AE' }};">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                        <span style="font-size:1.1rem;">{{ $n->emoji }}</span>
                        <span style="font-weight:600; color:#1A1F1F; font-size:0.85rem;">{{ $n->title }}</span>
                        @if($n->is_shared && $n->instructor_id !== session('student_id'))
                        <span style="margin-left:auto; font-size:0.7rem; color:#D87840;">
                            🔁 {{ $n->instructor->email }}
                        </span>
                        @endif
                    </div>
                    <div style="color:#5A6464; font-size:0.8rem; line-height:1.4; padding-left:24px;">
                        {{ Str::limit(strip_tags($n->body_markdown), 180) }}
                    </div>
                    @if($n->instructor_id === session('student_id'))
                    <a href="{{ route('student.knowledge_base.edit', $n->id) }}"
                       style="margin-top:6px; display:inline-block; margin-left:24px; color:#3A8C89; font-size:0.75rem; text-decoration:none;">
                        ✎ modifica
                    </a>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif
        @endif

        @if($module->hasMindmap())
        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <h3 style="font-weight:700; color:#1A1F1F; font-size:0.9rem;">🧠 Mappa mentale del modulo</h3>
                <span style="font-size:0.7rem; color:#8A9696;">
                    Aggiornata {{ $module->mindmap_generated_at?->diffForHumans() ?? '—' }}
                </span>
            </div>
            @if($module->isMindmapStale())
            <div style="background:rgba(226,138,83,0.08); border-left:3px solid #E28A53; padding:8px 12px; border-radius:4px; margin-bottom:12px; color:#7A5230; font-size:0.78rem;">
                ⚠ Il contenuto del modulo è stato aggiornato dopo la generazione di questa mappa. Alcuni concetti potrebbero non essere riflessi.
            </div>
            @endif
            <div style="position:relative; width:100%; height:700px; border:1px solid #F5F7F7; border-radius:8px; overflow:hidden; background:#FDFEFE;">
                <svg id="student-mindmap-svg" style="width:100%; height:100%;"></svg>
                <div style="position:absolute; bottom:8px; right:12px; font-size:0.65rem; color:#8A9696; pointer-events:none; background:rgba(255,255,255,0.85); padding:2px 8px; border-radius:8px;">
                    Trascina per spostare · Scroll per zoom · Click sui nodi per espandere/collassare
                </div>
            </div>
        </div>
        @endif

        @if(isset($moduleConceptMap) && $moduleConceptMap)
        <div style="background:white; border:1px solid #E8F5F5; border-left:4px solid #55B1AE;
                    border-radius:12px; padding:14px 20px; margin-bottom:20px;
                    display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
            <div style="font-size:1.2rem;">🧭</div>
            <div style="flex:1; min-width:180px;">
                <div style="font-weight:700; color:#1A1F1F; font-size:0.9rem;">
                    {{ $moduleConceptMap->title }}
                    @if($moduleConceptMapForked)
                        <span style="margin-left:6px; padding:2px 8px; background:#FEF3C7; color:#92400E; border-radius:4px; font-size:0.65rem; font-weight:700;">PERSONALIZZATA</span>
                    @endif
                </div>
                <div style="color:#8A9696; font-size:0.72rem; margin-top:2px;">
                    Mappa concettuale di questo modulo: i concetti e le loro relazioni.
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('student.course.concept-map.show', [$course->slug, $moduleConceptMap->id]) }}"
                   style="padding:6px 12px; background:#55B1AE; color:white;
                          border-radius:6px; text-decoration:none; font-size:0.78rem; font-weight:600;">
                    Apri mappa
                </a>
                @if($moduleConceptMapForked)
                <a href="{{ route('student.course.concept-map.my', [$course->slug, $moduleConceptMap->id]) }}"
                   style="padding:6px 12px; background:white; color:#55B1AE;
                          border:1px solid #55B1AE; border-radius:6px;
                          text-decoration:none; font-size:0.78rem; font-weight:600;">
                    La mia versione
                </a>
                @endif
            </div>
        </div>
        @endif

        {{-- P29 — documento PDF generato dal contenuto del modulo (sostituisce i documentali caricati) --}}
        @if($hasModuleDocument)
        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:20px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:12px; font-size:0.9rem;">📄 Documento del modulo</h3>
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.2rem;">📕</span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">Dispensa del modulo (PDF)</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Generato dal contenuto del modulo, sempre aggiornato.</div>
                    </div>
                </div>
                @if($isDemo)
                    <span style="padding:5px 12px; background:#F5F7F7; color:#8A9696; border-radius:6px; font-size:0.75rem;">
                        🔒 Solo versione completa
                    </span>
                @else
                    <a href="{{ route('student.module.document.download', [$course->slug, $module]) }}"
                       style="padding:5px 12px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                        Scarica PDF
                    </a>
                @endif
            </div>
        </div>
        @endif

        @if($materials->isNotEmpty())
        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:20px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:12px; font-size:0.9rem;">📎 Materiali del modulo</h3>
            @foreach($materials as $material)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid #F5F7F7;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.2rem;">
                        {{ $material->file_type === 'canvas' ? '🎯' : ($material->file_type === 'pdf' ? '📕' : ($material->file_type === 'video' ? '🎬' : '📄')) }}
                    </span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">{{ $material->title }}</div>
                        @if($material->description)
                        <div style="font-size:0.75rem; color:#8A9696;">{{ $material->description }}</div>
                        @endif
                    </div>
                </div>
                @if(isset($isDemo) && $isDemo && ($material->file_path || $material->external_url))
                    <span style="padding:5px 12px; background:#F5F7F7; color:#8A9696; border-radius:6px; font-size:0.75rem;">
                        🔒 Solo versione completa
                    </span>
                @elseif($material->file_type === 'canvas' && $material->file_path)
                    @php
                        $progettoNumber = null;
                        if (preg_match('/Scheda Progetto (\d+)$/u', $material->title, $matches)) {
                            $progettoNumber = $matches[1];
                        }
                        $canvasUrl = route('student.material.canvas', $material);
                        if ($progettoNumber) {
                            $canvasUrl .= "?progetto={$progettoNumber}";
                        }
                    @endphp
                    <a href="{{ $canvasUrl }}" target="_blank"
                       style="padding:6px 14px; background:linear-gradient(135deg,#55B1AE,#3A8C89); color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                        Apri canvas →
                    </a>
                @elseif($material->is_downloadable && $material->file_path)
                    <a href="{{ route('student.material.download', $material) }}" download
                       style="padding:5px 12px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                        Scarica
                    </a>
                @elseif($material->external_url)
                    <a href="{{ $material->external_url }}" target="_blank"
                       style="padding:5px 12px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                        Apri →
                    </a>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if($quiz)
        <div style="background:linear-gradient(135deg,#E28A53,#c97a45); border-radius:12px; padding:20px; margin-bottom:20px;">
            <h3 style="color:white; font-weight:700; margin-bottom:4px; font-size:1rem;">📝 {{ $quiz->title }}</h3>
            <p style="color:rgba(255,255,255,0.85); font-size:0.8rem; margin-bottom:12px;">
                {{ $quiz->questions()->count() }} domande · Soglia: {{ $quiz->passing_score }}%
                @if($quiz->time_limit_minutes) · ⏱ {{ $quiz->time_limit_minutes }} min @endif
            </p>
            <a href="/learn/quiz/{{ $quiz->id }}"
               style="display:inline-block; padding:8px 20px; background:white; color:#c97a45; border-radius:6px; font-size:0.875rem; font-weight:700; text-decoration:none;">
                Inizia il quiz →
            </a>
        </div>
        @endif

        @if(isset($finalQuiz) && $finalQuiz)
        <div style="background:linear-gradient(135deg,#1A1F1F,#252B2B); border-radius:16px; padding:28px; margin-bottom:20px; border:2px solid rgba(85,177,174,0.3);">
            @if($certificationPassed)
            <div style="text-align:center;">
                <div style="font-size:3rem; margin-bottom:12px;">🏆</div>
                <h3 style="color:#55B1AE; font-weight:700; font-size:1.1rem; margin-bottom:8px;">
                    Esame finale superato!
                </h3>
                <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
                    Hai superato l'esame finale per {{ $course->name }}.
                </p>
                <div style="display:inline-block; padding:8px 20px; background:rgba(85,177,174,0.15); border:1px solid #55B1AE; border-radius:8px; color:#55B1AE; font-size:0.8rem; font-weight:700;">
                    {{ $course->certification_name }}
                </div>
                <div style="display:flex; gap:10px; justify-content:center; margin-top:16px;">
                    <a href="/learn/certificate/{{ $course->slug }}"
                       style="padding:10px 24px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:700; text-decoration:none;">
                        ⬇ Scarica certificato PDF
                    </a>
                    <a href="/learn/certificate/{{ $course->slug }}/view" target="_blank"
                       style="padding:10px 24px; border:1px solid #55B1AE; color:#55B1AE; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
                        👁 Anteprima
                    </a>
                </div>
            </div>
            @else
            <div style="display:flex; align-items:flex-start; gap:20px;">
                <div style="font-size:2.5rem; flex-shrink:0;">🎓</div>
                <div style="flex:1;">
                    <div style="color:#55B1AE; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:6px;">
                        Esame finale
                    </div>
                    <h3 style="color:white; font-weight:700; font-size:1.1rem; margin-bottom:6px;">
                        {{ $finalQuiz->title }}
                    </h3>
                    <p style="color:#8A9696; font-size:0.8rem; margin-bottom:4px;">
                        {{ $finalQuiz->questions()->count() }} domande · Soglia: {{ $finalQuiz->passing_score }}%
                        @if($finalQuiz->time_limit_minutes) · ⏱ {{ $finalQuiz->time_limit_minutes }} minuti @endif
                    </p>
                    <p style="color:#8A9696; font-size:0.8rem; margin-bottom:16px;">
                        Al superamento ricevi: <span style="color:#55B1AE; font-weight:600;">{{ $course->certification_name }}</span>
                    </p>
                    <a href="/learn/quiz/{{ $finalQuiz->id }}"
                       style="display:inline-block; padding:12px 28px; background:#55B1AE; color:white; border-radius:8px; font-size:0.9rem; font-weight:700; text-decoration:none;">
                        Sostieni l'esame →
                    </a>
                </div>
            </div>
            @endif
        </div>
        @endif

        <div style="display:flex; justify-content:space-between; margin-top:8px; gap:12px;">
            @if($prevModule)
            <a href="/learn/course/{{ $course->slug }}/module/{{ $prevModule->id }}" style="padding:10px 20px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                &larr; {{ \Illuminate\Support\Str::limit($prevModule->title, 30) }}
            </a>
            @else
            <div></div>
            @endif

            @if($nextModule)
            <a href="/learn/course/{{ $course->slug }}/module/{{ $nextModule->id }}" style="padding:10px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
                {{ \Illuminate\Support\Str::limit($nextModule->title, 30) }} &rarr;
            </a>
            @endif
        </div>
    </div>

    <div>
        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:16px; position:sticky; top:80px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:12px; font-size:0.9rem;">Il tuo progresso</h3>

            @if($progress->status === 'completed')
            <div style="text-align:center; padding:12px; background:#E8F5F5; border-radius:8px; margin-bottom:12px;">
                <div style="font-size:1.5rem;">&#9989;</div>
                <div style="color:#3A8C89; font-weight:600; font-size:0.875rem;">Modulo completato!</div>
            </div>
            @else
            <button id="complete-btn" onclick="completeModule()" style="width:100%; padding:12px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer; margin-bottom:12px;">
                &#10003; Segna come completato
            </button>
            @endif

            <button type="button" x-data @click="$dispatch('minerva-toggle')"
                    style="width:100%; padding:10px; background:#1A1F1F; color:#55B1AE; border:none; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer; text-align:center;">
                &#10022; Chiedi a {{ atheneum_setting('assistant_name', 'Minerva') }}
            </button>
        </div>

        <div style="background:white; border-radius:12px; padding:16px; margin-bottom:16px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:10px; font-size:0.85rem;">📝 Le tue note</h3>
            <textarea id="student-note"
                      placeholder="Scrivi i tuoi appunti su questo modulo..."
                      style="width:100%; min-height:120px; padding:10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.8rem; outline:none; resize:vertical; color:#1A1F1F; line-height:1.6;">{{ $note?->content }}</textarea>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                <span id="note-status" style="font-size:0.75rem; color:#8A9696;"></span>
                <button onclick="saveNote()"
                        style="padding:5px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                    Salva
                </button>
            </div>
        </div>

        <div style="background:white; border-radius:12px; padding:16px;">
            <div style="color:#8A9696; font-size:0.75rem; margin-bottom:8px; font-weight:700; text-transform:uppercase;">Corso</div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <span>{{ $course->icon }}</span>
                <span style="font-weight:600; color:#1A1F1F; font-size:0.85rem;">{{ $course->name }}</span>
            </div>
            @if($module->duration_minutes)
            <div style="color:#8A9696; font-size:0.8rem;">&#9201; Durata: {{ $module->duration_minutes }} minuti</div>
            @endif
        </div>
    </div>

</div>

@if($module->hasMindmap())
@push('scripts')
{{-- Render markmap esplicito (no autoloader): controllo fit() su un container alto,
     evita il bug "renderizzato minuscolo in alto-sx" dell'autoloader. --}}
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-view@0.18"></script>
<script src="https://cdn.jsdelivr.net/npm/markmap-lib@0.18"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const svg = document.getElementById('student-mindmap-svg');
    if (!svg) return;
    const markdown = @json($module->mindmap_markdown);
    try {
        const { Transformer, Markmap } = window.markmap;
        const { root } = new Transformer().transform(markdown);
        const mm = Markmap.create(svg, { fitRatio: 0.92, autoFit: true }, root);
        // Secondo fit dopo un tick per assicurare layout calcolato col container reale
        setTimeout(() => mm.fit(), 100);
    } catch (e) {
        console.error('Markmap student render error:', e);
        svg.parentElement.innerHTML = '<div style="padding:24px; color:#C52A2A; font-size:0.85rem;">Errore rendering mappa mentale: ' + e.message + '</div>';
    }
});
</script>
@endpush
@endif

@push('scripts')
<script>
async function completeModule() {
    const btn = document.getElementById('complete-btn');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = 'Salvataggio...';
    try {
        const response = await fetch('/learn/course/{{ $course->slug }}/module/{{ $module->id }}/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        if (response.ok || response.redirected) {
            btn.style.background = '#3A8C89';
            btn.textContent = 'Completato!';
            setTimeout(() => location.reload(), 600);
        } else {
            btn.disabled = false;
            btn.textContent = 'Segna come completato';
        }
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Segna come completato';
    }
}

const noteTextarea = document.getElementById('student-note');
let noteTimer = null;

if (noteTextarea) {
    noteTextarea.addEventListener('input', () => {
        clearTimeout(noteTimer);
        document.getElementById('note-status').textContent = 'Modifica in corso...';
        noteTimer = setTimeout(saveNote, 2000);
    });
}

async function saveNote() {
    if (!noteTextarea) return;
    try {
        await fetch('/learn/notes/{{ $module->id }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ content: noteTextarea.value }),
        });
        document.getElementById('note-status').textContent = '✓ Salvato';
        setTimeout(() => {
            document.getElementById('note-status').textContent = '';
        }, 2000);
    } catch(e) {
        document.getElementById('note-status').textContent = 'Errore salvataggio';
    }
}

// Reading progress bar
const progressBar = document.getElementById('reading-bar');
if (progressBar) {
    window.addEventListener('scroll', () => {
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrolled = docHeight > 0 ? (window.scrollY / docHeight) * 100 : 0;
        progressBar.style.width = Math.min(scrolled, 100) + '%';
    });
}

// Note ancorate per paragrafo (Alpine component)
function anchoredNotes(opts) {
    return {
        moduleId: opts.moduleId,
        csrf: opts.csrf,
        isDemo: opts.isDemo,
        notes: opts.initial || [],
        panelOpen: false,
        popoverAnchor: null,
        popoverContent: '',
        popoverStatus: '',

        get totalAnchored() { return this.notes.length; },

        init() {
            if (this.isDemo) return;
            this.$nextTick(() => this.injectPins());
        },

        injectPins() {
            const root = this.$refs.contentBody;
            if (!root) return;
            const blocks = root.querySelectorAll('[data-note-anchor]');
            blocks.forEach((el) => {
                if (el.dataset.pinInjected) return;
                const anchor = el.dataset.noteAnchor;
                if (!anchor) return;

                // Wrappa l'elemento in un container flex
                const wrapper = document.createElement('div');
                wrapper.className = 'annotated-block';
                el.parentNode.insertBefore(wrapper, el);

                // Wrapper del pin (width:0 per non rubare layout, sticky inside)
                const pinWrap = document.createElement('div');
                pinWrap.className = 'note-pin-wrapper';

                const pin = document.createElement('button');
                pin.type = 'button';
                pin.className = 'note-pin';
                pin.dataset.anchor = anchor;
                pin.title = 'Aggiungi/modifica nota a questo paragrafo';
                pin.textContent = '+';
                pin.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.openPopoverFor(anchor);
                });

                pinWrap.appendChild(pin);
                wrapper.appendChild(pinWrap);
                wrapper.appendChild(el);

                el.dataset.pinInjected = '1';
                this.refreshPin(anchor);
            });
        },

        refreshPin(anchor) {
            const root = this.$refs.contentBody;
            if (!root) return;
            const pin = root.querySelector(`.note-pin[data-anchor="${anchor}"]`);
            if (!pin) return;
            const has = this.notes.some(n => n.anchor === anchor);
            if (has) {
                pin.classList.add('has-note');
                pin.textContent = '📝';
            } else {
                pin.classList.remove('has-note');
                pin.textContent = '+';
            }
        },

        openPopoverFor(anchor) {
            const existing = this.notes.find(n => n.anchor === anchor);
            this.popoverAnchor = anchor;
            this.popoverContent = existing?.content || '';
            this.popoverStatus = '';
            this.$nextTick(() => this.$refs.popoverTextarea?.focus());
        },

        async savePopover() {
            if (!this.popoverAnchor) return;
            this.popoverStatus = 'Salvataggio…';
            try {
                const res = await fetch(`/learn/notes/${this.moduleId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({
                        anchor: this.popoverAnchor,
                        content: this.popoverContent,
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    this.popoverStatus = 'Errore di salvataggio';
                    return;
                }
                const anchor = this.popoverAnchor;
                if (data.deleted) {
                    this.notes = this.notes.filter(n => n.anchor !== anchor);
                } else if (data.note) {
                    const idx = this.notes.findIndex(n => n.anchor === anchor);
                    const noteData = {
                        id: data.note.id,
                        anchor: data.note.anchor,
                        content: data.note.content,
                    };
                    if (idx >= 0) this.notes[idx] = noteData;
                    else this.notes.push(noteData);
                }
                this.refreshPin(anchor);
                this.popoverStatus = 'Salvato ✓';
                setTimeout(() => {
                    if (this.popoverStatus === 'Salvato ✓') this.popoverAnchor = null;
                }, 800);
            } catch (e) {
                this.popoverStatus = 'Errore: ' + e.message;
            }
        },

        async deleteNote(anchor) {
            try {
                const res = await fetch(`/learn/notes/${this.moduleId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({ anchor: anchor, content: '' }),
                });
                if (res.ok) {
                    this.notes = this.notes.filter(n => n.anchor !== anchor);
                    this.refreshPin(anchor);
                }
            } catch (e) { /* silent */ }
        },

        goToAnchor(anchor) {
            const root = this.$refs.contentBody;
            if (!root) return;
            const el = root.querySelector(`[data-note-anchor="${anchor}"]`);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.remove('anchor-flash');
            void el.offsetWidth; // restart animation
            el.classList.add('anchor-flash');
        },
    };
}
</script>
@endpush

@endsection
