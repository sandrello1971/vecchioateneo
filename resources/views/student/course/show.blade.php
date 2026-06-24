@extends('layouts.student')
@section('title', $course->name)
@section('breadcrumb', $course->name)

@section('content')

<div style="max-width:800px;">

    @php $demoStudent = \App\Models\Student::find(session('student_id')); $isDemoView = $demoStudent && $demoStudent->is_demo; @endphp

    <div style="background:{{ $course->color }}; border-radius:12px; padding:24px; margin-bottom:24px; color:white;">
        <div style="display:flex; align-items:center; gap:16px; @if(!$isDemoView) margin-bottom:16px; @endif">
            <span style="font-size:2.5rem;">{{ $course->icon }}</span>
            <div>
                <h1 style="font-size:1.5rem; font-weight:700;">{{ $course->name }}</h1>
                @if(!$isDemoView)
                <p style="opacity:0.85; font-size:0.875rem;">{{ $course->description }}</p>
                @else
                <p style="opacity:0.85; font-size:0.8rem;">Demo del corso Rumore di fondo — esplora liberamente</p>
                @endif
            </div>
        </div>
        @unless($isDemoView)
        <div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:0.8rem; opacity:0.9;">
                <span>Progresso complessivo</span>
                <span>{{ $progressPercent }}% ({{ $completedModules }}/{{ $totalModules }})</span>
            </div>
            <div style="height:8px; background:rgba(255,255,255,0.3); border-radius:4px;">
                <div style="height:100%; width:{{ $progressPercent }}%; background:white; border-radius:4px; transition:width 0.3s;"></div>
            </div>
        </div>
        @endunless
    </div>

    @if(!empty($teaching))
    <div style="background:#E8F5F5; border:1px solid #C8D0D0; border-left:4px solid #E28A53; border-radius:8px; padding:12px 16px; margin-bottom:16px; color:#5A6464; font-size:0.85rem; line-height:1.5;">
        👁 <strong style="color:#1A1F1F;">Modalità docenza</strong> — Stai vedendo il corso come lo vede il discente. Avanzamento, quiz e certificato non vengono registrati. Usa "Aggiungi nota" per preparare la lezione.
    </div>
    @endif

    <div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;" x-data>
        <div>
            <div style="color:#55B1AE; font-weight:700; font-size:0.875rem;">&#10022; Assistente AI — {{ atheneum_setting('assistant_name', 'Minerva') }}</div>
            <div style="color:#8A9696; font-size:0.75rem;">Hai dubbi sui contenuti? Chiedimi!</div>
        </div>
        <a href="{{ route('student.chat.show', $course) }}"
           style="padding:8px 16px; background:#E28A53; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block;">
            Apri chat &rarr;
        </a>
    </div>

    @if(empty($teaching))
        @php
            $messageUser = \App\Models\Student::find(session('student_id'));
            $courseInstructorId = \DB::table('student_course')
                ->where('student_id', $messageUser->id)
                ->where('course_id', $course->id)
                ->where('is_active', true)
                ->value('instructor_id');
            $courseInstructor = $courseInstructorId ? \App\Models\Student::find($courseInstructorId) : null;
            $canMessageInstructor = $courseInstructor
                && $messageUser->can('startConversationWith', [\App\Models\Conversation::class, $courseInstructor, $course]);
        @endphp
        @if($canMessageInstructor)
        <div style="background:white; border:1px solid #E5E7E7; border-radius:12px; padding:14px 18px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
            <div>
                <div style="color:#1A1F1F; font-weight:600; font-size:0.875rem;">✉️ Hai una domanda per {{ $courseInstructor->name }}?</div>
                <div style="color:#8A9696; font-size:0.75rem;">Apri una conversazione privata col formatore del corso.</div>
            </div>
            <a href="{{ route('student.messages.create', ['instructor' => $courseInstructor->id, 'course' => $course->id]) }}"
               style="padding:8px 16px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none; display:inline-block;">
                Scrivi al formatore &rarr;
            </a>
        </div>
        @endif
    @endif

    @if($course->video_ai_id)
        @include('student.course._video-player', [
            'videoId' => $course->video_ai_id,
            'scope' => 'course',
            'courseSlug' => $course->slug,
            'moduleId' => null,
            'label' => 'Video introduttivo al corso',
        ])
    @endif

    @if($hasAnyVideo)
    <div style="background:white; border-radius:12px; padding:16px 20px; margin-bottom:20px; border-left:4px solid #55B1AE;"
         x-data="courseVideoSearch('{{ $course->slug }}')">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
            <span style="font-size:1rem;">🔎</span>
            <div style="font-weight:700; color:#1A1F1F; font-size:0.9rem;">Cerca in tutti i video del corso</div>
        </div>
        <div style="display:flex; gap:8px;">
            <input type="text" x-model="query"
                   @keydown.enter="runSearch()"
                   placeholder="Es: come si configura Rumore di fondo?"
                   style="flex:1; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            <button @click="runSearch()"
                    :disabled="searching"
                    style="padding:8px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                <span x-show="!searching">Cerca</span>
                <span x-show="searching">...</span>
            </button>
        </div>

        <div x-show="query && !searching && results.length === 0"
             style="color:#8A9696; font-size:0.8rem; text-align:center; padding:10px 0;">
            Nessun risultato.
        </div>

        <div x-show="results.length > 0" style="margin-top:12px; display:flex; flex-direction:column; gap:8px; max-height:320px; overflow-y:auto;">
            <template x-for="(r, i) in results" :key="i">
                <a :href="r.deep_link"
                   style="text-decoration:none; padding:10px 12px; background:#F5F7F7; border-radius:8px; border-left:3px solid #55B1AE; display:block;">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                        <span style="padding:2px 8px; background:#E8F5F5; color:#55B1AE; border-radius:4px; font-size:0.75rem; font-family:monospace; font-weight:600;"
                              x-text="r.timestamp_str"></span>
                        <span style="color:#8A9696; font-size:0.7rem; text-transform:uppercase; font-weight:700;"
                              x-text="r.scope === 'course' ? 'video corso' : 'video modulo'"></span>
                        <span style="color:#3A8C89; font-size:0.75rem; font-weight:600;" x-text="r.title"></span>
                    </div>
                    <div style="font-size:0.8rem; color:#1A1F1F; line-height:1.5;" x-text="r.text"></div>
                </a>
            </template>
        </div>
    </div>

    @pushOnce('scripts')
    <script>
    function courseVideoSearch(slug) {
        return {
            slug,
            query: '',
            results: [],
            searching: false,
            async runSearch() {
                if (!this.query.trim() || this.searching) return;
                this.searching = true;
                try {
                    const res = await fetch(`/learn/course/${this.slug}/video-search?q=${encodeURIComponent(this.query)}`);
                    const data = await res.json();
                    this.results = data.results || [];
                } catch(e) {
                    this.results = [];
                }
                this.searching = false;
            },
        };
    }
    </script>
    @endPushOnce
    @endif

    @if(isset($courseConceptMap) && $courseConceptMap)
    <div style="background:white; border:1px solid #E8F5F5; border-left:4px solid #E28A53;
                border-radius:12px; padding:16px 20px; margin-bottom:20px;
                display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <div style="font-size:1.4rem;">🧭</div>
        <div style="flex:1; min-width:200px;">
            <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">
                {{ $courseConceptMap->title }}
                @if($courseConceptMapForked)
                    <span style="margin-left:6px; padding:2px 8px; background:#FEF3C7; color:#92400E; border-radius:4px; font-size:0.65rem; font-weight:700;">PERSONALIZZATA</span>
                @endif
            </div>
            <div style="color:#8A9696; font-size:0.75rem; margin-top:2px;">
                Mappa concettuale dell'intero corso: i concetti chiave e le relazioni che li legano.
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('student.course.concept-map.show', [$course->slug, $courseConceptMap->id]) }}"
               style="padding:7px 14px; background:#E28A53; color:white;
                      border-radius:6px; text-decoration:none; font-size:0.8rem; font-weight:600;">
                Apri mappa
            </a>
            @if($courseConceptMapForked)
            <a href="{{ route('student.course.concept-map.my', [$course->slug, $courseConceptMap->id]) }}"
               style="padding:7px 14px; background:white; color:#E28A53;
                      border:1px solid #E28A53; border-radius:6px;
                      text-decoration:none; font-size:0.8rem; font-weight:600;">
                La mia versione
            </a>
            @endif
        </div>
    </div>
    @endif

    @if($instructorMaterials->isNotEmpty())
    <div style="background:linear-gradient(135deg, rgba(226,138,83,0.08), rgba(226,138,83,0.12));
                border:1px solid rgba(226,138,83,0.3);
                border-radius:12px; padding:20px; margin-bottom:20px;">

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
            <div style="font-size:1.3rem;">🎓</div>
            <div style="font-weight:700; color:#1A1F1F; font-size:1rem;">
                Materiali Formatore
            </div>
            <div style="margin-left:auto; padding:3px 10px;
                        background:rgba(226,138,83,0.2); color:#D87840;
                        border-radius:12px; font-size:0.7rem; font-weight:700;">
                SOLO DOCENTI
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:8px;">
            @foreach($instructorMaterials as $im)
            <div style="display:flex; align-items:center; gap:12px;
                        padding:10px 14px; background:white; border-radius:8px;">
                <div style="flex:1;">
                    <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem;">
                        {{ $im->title }}
                    </div>
                    @if($im->description)
                    <div style="color:#8A9696; font-size:0.75rem; margin-top:2px;">
                        {{ $im->description }}
                    </div>
                    @endif
                </div>
                <a href="{{ route('student.instructor.material.show', [$course->slug, $im->id]) }}"
                   style="padding:6px 12px; background:#E28A53; color:white;
                          border-radius:6px; text-decoration:none;
                          font-size:0.8rem; font-weight:600;">
                    Consulta
                </a>
                <a href="{{ route('student.instructor.material.download', [$course->slug, $im->id]) }}"
                   style="padding:6px 12px; background:white; color:#E28A53;
                          border:1px solid #E28A53; border-radius:6px;
                          text-decoration:none; font-size:0.8rem; font-weight:600;">
                    📥 .docx
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- P29 — dispensa PDF dell'intero corso (tutti i moduli, generata on-access) --}}
    @if($hasCourseDocument)
    <div style="background:white; border-radius:12px; padding:20px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:1.3rem;">📘</span>
                <div>
                    <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">Dispensa del corso (PDF)</div>
                    <div style="color:#8A9696; font-size:0.75rem;">Un unico documento con tutti i moduli, sempre aggiornato.</div>
                </div>
            </div>
            @if($isDemoView)
                <span style="padding:6px 12px; background:#F5F7F7; color:#8A9696; border-radius:6px; font-size:0.78rem;">
                    🔒 Solo versione completa
                </span>
            @else
                <a href="{{ route('student.course.document.download', $course->slug) }}"
                   style="padding:6px 14px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                    📥 Scarica dispensa
                </a>
            @endif
        </div>
    </div>
    @endif

    <div style="display:flex; flex-direction:column; gap:8px;">
        @foreach($modules as $index => $module)
        @php
            $mp = $progressByModule[$module->id] ?? null;
            $status = $mp?->status ?? 'not_started';
        @endphp
        <div style="background:white; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="padding:16px 20px; display:flex; align-items:center; gap:16px;">
                <div style="width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.875rem; flex-shrink:0;
                    background:{{ $status === 'completed' ? '#E8F5F5' : ($status === 'in_progress' ? '#fff3ec' : '#F5F7F7') }};
                    color:{{ $status === 'completed' ? '#3A8C89' : ($status === 'in_progress' ? '#c97a45' : '#8A9696') }};">
                    {{ $status === 'completed' ? '✓' : ($index + 1) }}
                </div>

                <div style="flex:1;">
                    <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem;">{{ $module->title }}</div>
                    <div style="color:#8A9696; font-size:0.75rem;">
                        @if($module->duration_minutes)
                            @php
                                $mins = $module->duration_minutes;
                                if ($mins >= 60) {
                                    $h = floor($mins / 60);
                                    $m = $mins % 60;
                                    $durationLabel = $h . 'h' . ($m > 0 ? ' ' . $m . "'" : '');
                                } else {
                                    $durationLabel = $mins . "'";
                                }
                            @endphp
                            &#9201; {{ $durationLabel }}
                        @endif
                        @if($module->description)
                            &middot; {{ \Illuminate\Support\Str::limit($module->description, 60) }}
                        @endif
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    @if($status === 'completed')
                    <span style="font-size:0.7rem; padding:3px 8px; background:#E8F5F5; color:#3A8C89; border-radius:4px; font-weight:600;">✓ Completato</span>
                    @elseif($status === 'in_progress')
                    <span style="font-size:0.7rem; padding:3px 8px; background:#fff3ec; color:#c97a45; border-radius:4px; font-weight:600;">In corso</span>
                    @else
                    <span style="font-size:0.7rem; padding:3px 8px; background:#F5F7F7; color:#8A9696; border-radius:4px;">Non iniziato</span>
                    @endif

                    <a href="/learn/course/{{ $course->slug }}/module/{{ $module->id }}"
                       style="padding:6px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                        Apri
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($progressPercent >= 70 && $finalQuiz)
    <div style="background:linear-gradient(135deg,#1A1F1F,#252B2B); border-radius:16px; padding:24px; margin-top:16px; border:2px solid rgba(85,177,174,0.4); text-align:center;">
        <div style="font-size:2rem; margin-bottom:12px;">🎓</div>
        @if($certificationPassed)
        <h3 style="color:#55B1AE; font-weight:700; margin-bottom:6px;">Esame finale superato!</h3>
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:8px;">
            Hai ottenuto: <span style="color:#55B1AE; font-weight:600;">{{ $course->certification_name }}</span>
        </p>
        <a href="/learn/certificate/{{ $course->slug }}"
           style="display:inline-block; margin-top:12px; padding:10px 24px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:700; text-decoration:none;">
            ⬇ Scarica il tuo certificato
        </a>
        @else
        <h3 style="color:white; font-weight:700; margin-bottom:6px;">Pronto per l'esame finale?</h3>
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
            Hai completato il {{ $progressPercent }}% del corso. Puoi sostenere l'esame finale.
        </p>
        <a href="/learn/quiz/{{ $finalQuiz->id }}"
           style="display:inline-block; padding:12px 32px; background:#55B1AE; color:white; border-radius:8px; font-weight:700; text-decoration:none; font-size:0.9rem;">
            Esame finale →
        </a>
        @endif
    </div>
    @endif

</div>
@endsection
