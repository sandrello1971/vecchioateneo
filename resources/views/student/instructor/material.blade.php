@extends('layouts.student')

@section('title', $material->title . ' — ' . $course->name)
@section('breadcrumb', 'Formatori · ' . $course->name . ' · ' . $material->title)

@section('content')
<div style="max-width:900px; margin:0 auto;">

    <div style="background:linear-gradient(135deg, #E28A53, #D87840);
                border-radius:12px; padding:14px 20px; margin-bottom:20px;
                display:flex; align-items:center; gap:12px;">
        <div style="font-size:1.5rem;">🎓</div>
        <div style="color:white;">
            <div style="font-weight:700; font-size:0.9rem;">
                Materiale riservato ai formatori
            </div>
            <div style="font-size:0.75rem; opacity:0.9;">
                Non condividere con gli studenti
            </div>
        </div>
        <div style="flex:1;"></div>
        <a href="{{ route('student.instructor.material.download', [$course->slug, $material->id]) }}"
           style="padding:8px 16px; background:rgba(255,255,255,0.2);
                  color:white; border-radius:8px; text-decoration:none;
                  font-size:0.85rem; font-weight:600; white-space:nowrap;">
            📥 Scarica .docx
        </a>
    </div>

    @if(!empty($sectionsWithNotes))
    <div style="background:rgba(226,138,83,0.06); border:1px solid rgba(226,138,83,0.2);
                border-radius:8px; padding:12px 16px; margin-bottom:16px;">
        <div style="font-weight:600; color:#D87840; font-size:0.85rem; margin-bottom:6px;">
            📓 Hai {{ array_sum($sectionsWithNotes) }} note su {{ count($sectionsWithNotes) }} sezioni di questo manuale
        </div>
        <a href="{{ route('student.knowledge_base.index', ['course_id' => $course->id]) }}"
           style="font-size:0.8rem; color:#3A8C89; text-decoration:none;">
            → Vedi tutte le note del corso
        </a>
    </div>
    @endif

    <div class="instructor-manual"
         style="background:white; border-radius:12px; padding:40px;
                line-height:1.7;">
        {!! $material->content_html !!}
    </div>

    <div style="margin-top:20px; text-align:center;">
        <a href="{{ route('student.course.show', $course->slug) }}"
           style="color:#55B1AE; text-decoration:none; font-size:0.85rem;">
            ← Torna a {{ $course->name }}
        </a>
    </div>
</div>

<style>
.instructor-manual h1 { font-size:1.6rem; margin:24px 0 12px; color:#1A1F1F; font-weight:700; scroll-margin-top:80px; }
.instructor-manual h2 { font-size:1.25rem; margin:20px 0 10px; color:#3A8C89; font-weight:700; scroll-margin-top:80px; }
.instructor-manual h3 { font-size:1.05rem; margin:16px 0 8px; color:#1A1F1F; font-weight:600; }
.instructor-manual p { margin:8px 0; color:#3A4040; }
.instructor-manual ul, .instructor-manual ol { margin:8px 0 8px 24px; }
.instructor-manual li { margin:4px 0; }
.instructor-manual strong { color:#1A1F1F; font-weight:700; }
.instructor-manual em { color:#4A5252; }
.instructor-manual table { border-collapse:collapse; margin:12px 0; width:100%; }
.instructor-manual table td { padding:10px 14px; border:1px solid #E8F5F5; }
.instructor-manual table td:first-child { background:#F5F7F7; }
.instructor-manual blockquote { margin:12px 0; padding:10px 16px;
    border-left:3px solid #E28A53; background:rgba(226,138,83,0.08);
    border-radius:0 8px 8px 0; }
.instructor-manual code { background:#F5F7F7; padding:2px 6px;
    border-radius:4px; font-size:0.9em; color:#E28A53; }
</style>
@endsection
