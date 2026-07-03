<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    use DeterminesTeachingMode;

    public function show(Course $course)
    {
        $student = $this->checkAccess($course);
        $modules = $course->modules()->where('is_active', true)->orderBy('sort_order')->get();

        $progress = StudentModuleProgress::where('student_id', $student->id)
            ->whereIn('module_id', $modules->pluck('id'))
            ->get()->keyBy('module_id');

        $modules->each(function ($module) use ($progress) {
            $p = $progress->get($module->id);
            $module->progress_status = $p?->status ?? 'not_started';
        });

        $totalModules = $modules->count();
        $completedModules = $modules->where('progress_status', 'completed')->count();
        $progressPercent = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;

        // P29 Fase 3 — dispensa PDF del corso intero: offerta se c'è almeno un modulo con contenuto.
        $hasCourseDocument = $modules->contains(fn ($m) => trim(strip_tags((string) $m->content)) !== '');

        $finalQuiz = Quiz::where('course_id', $course->id)
            ->whereNull('module_id')
            ->where('is_active', true)
            ->first();

        $certificationPassed = false;
        if ($finalQuiz) {
            $certificationPassed = QuizAttempt::where('quiz_id', $finalQuiz->id)
                ->where('student_id', $student->id)
                ->where('passed', true)
                ->exists();
        }

        $progressByModule = $progress;

        $hasAnyVideo = (bool) $course->video_ai_id
            || $modules->contains(fn($m) => !empty($m->video_ai_id));

        $instructorMaterials = $student->isInstructor()
            ? $course->instructorMaterials
            : collect();

        $teaching = $this->isTeachingMode($student, $course);

        // Mappa concettuale a livello CORSO (module_id NULL) — al massimo 1
        $courseConceptMap = $course->conceptMaps()->published()->whereNull('module_id')->first();
        $courseConceptMapForked = false;
        if ($courseConceptMap) {
            $courseConceptMapForked = \App\Models\StudentConceptMap::where('student_id', $student->id)
                ->where('course_concept_map_id', $courseConceptMap->id)
                ->exists();
        }

        return view('student.course.show', compact(
            'course', 'modules', 'progressPercent',
            'completedModules', 'totalModules', 'finalQuiz', 'certificationPassed', 'progressByModule',
            'hasAnyVideo', 'instructorMaterials', 'teaching',
            'courseConceptMap', 'courseConceptMapForked', 'hasCourseDocument'
        ));
    }

    public function module(Course $course, Module $module)
    {
        $student = $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        $teaching = $this->isTeachingMode($student, $course);

        if ($student->is_demo) {
            $progress = new StudentModuleProgress(['status' => 'in_progress', 'student_id' => $student->id, 'module_id' => $module->id]);
        } elseif ($teaching) {
            $progress = new StudentModuleProgress(['status' => 'not_started', 'student_id' => $student->id, 'module_id' => $module->id]);
        } else {
            $progress = StudentModuleProgress::firstOrCreate(
                ['student_id' => $student->id, 'module_id' => $module->id],
                ['status' => 'in_progress', 'started_at' => now()]
            );

            if ($progress->status === 'not_started') {
                $progress->update(['status' => 'in_progress', 'started_at' => now()]);
            }
        }

        // P29 Fase 3 — il PDF generato sostituisce i materials DOCUMENTALI (pdf/docx);
        // canvas/video/link restano nella lista. instructor-only già escluso da forStudents().
        $materials = $module->materials()->forStudents()->orderBy('sort_order')->get()
            ->reject(fn ($m) => in_array($m->file_type, ['pdf', 'docx'], true))
            ->values();
        $hasModuleDocument = trim(strip_tags((string) $module->content)) !== '';
        $prevModule = $course->modules()->where('sort_order', '<', $module->sort_order)->orderBy('sort_order', 'desc')->first();
        $nextModule = $course->modules()->where('sort_order', '>', $module->sort_order)->orderBy('sort_order')->first();
        $canvases = is_array($module->metadata ?? null) ? ($module->metadata['canvases'] ?? []) : [];

        $quiz = Quiz::where('module_id', $module->id)->where('is_active', true)->first();

        $finalQuiz = null;
        $isLastModule = !$nextModule;

        if ($isLastModule) {
            $finalQuiz = Quiz::where('course_id', $course->id)
                ->whereNull('module_id')
                ->where('is_active', true)
                ->first();

            $totalModules = $course->modules()->count();
            $completedModules = StudentModuleProgress::where('student_id', $student->id)
                ->whereIn('module_id', $course->modules()->pluck('id'))
                ->where('status', 'completed')
                ->count();

            if ($completedModules < ceil($totalModules * 0.7)) {
                $finalQuiz = null;
            }
        }

        $certificationPassed = false;
        if ($finalQuiz) {
            $certificationPassed = QuizAttempt::where('quiz_id', $finalQuiz->id)
                ->where('student_id', $student->id)
                ->where('passed', true)
                ->exists();
        }

        $isDemo = $student->is_demo;

        // Nota generale (anchor IS NULL) — retrocompatibile con il textarea esistente
        $note = \App\Models\StudentNote::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->whereNull('anchor')
            ->first();

        // Tutte le note dello studente per questo modulo (incluse anchored)
        $studentNotes = \App\Models\StudentNote::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->get(['id', 'anchor', 'content', 'updated_at']);

        if ($isDemo) {
            $module->video_ai_id = null;
        }

        // Inietta gli anchor (id="p-001"...) nei blocchi annotabili del content.
        // Va fatto PRIMA del trim demo così l'utente normale ottiene anchor stabili
        // sull'HTML completo; il demo mostra solo il primo blocco.
        if ($module->content) {
            $module->content = app(\App\Services\NoteAnchorInjector::class)->inject($module->content);
        }

        if ($isDemo && $module->content) {
            // Anteprima demo: solo il PRIMO blocco di contenuto (fino alla fine del
            // primo paragrafo). Il vecchio taglio "prime 20 righe" era inefficace
            // perché l'HTML dei moduli ha pochissimi a-capo (mostrava quasi tutto).
            $html = $module->content;
            if (preg_match('/<\/p>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
                $module->content = substr($html, 0, $m[0][1] + strlen($m[0][0]));
            } else {
                // Fallback: nessun <p> → ~600 caratteri di testo, su confine di parola.
                $text = preg_replace('/\s+\S*$/u', '', substr(strip_tags($html), 0, 600));
                $module->content = '<p>' . e($text) . '…</p>';
            }
            $module->content .= '
            <div style="margin-top:24px; padding:20px; background:linear-gradient(135deg,#1A1F1F,#3A8C89); border-radius:12px; text-align:center;">
                <div style="color:#55B1AE; font-weight:700; margin-bottom:8px;">✦ Stai usando la versione Demo</div>
                <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
                    Visualizzi solo un\'anteprima del contenuto.<br>
                    Acquista il corso per accedere a tutti i materiali.
                </p>
                <a href="/contatti"
                   style="padding:10px 24px; background:#E28A53; color:white; border-radius:8px; font-size:0.875rem; font-weight:700; text-decoration:none;">
                    Acquista il corso completo →
                </a>
            </div>';
        }

        $instructorManualSections = collect();
        $instructorNotes = collect();
        if ($student->isInstructor()) {
            $instructorManualSections = \App\Models\InstructorManualSection::where('module_id', $module->id)
                ->with('material')
                ->orderBy('sort_order')->get();

            $instructorNotes = \App\Models\InstructorNote::visibleTo($student->id)
                ->where('module_id', $module->id)
                ->with(['instructor', 'section'])
                ->latest()->get();
        }

        // Mappa concettuale del modulo (al massimo 1, publica)
        $moduleConceptMap = $course->conceptMaps()
            ->published()
            ->where('module_id', $module->id)
            ->first();
        $moduleConceptMapForked = false;
        if ($moduleConceptMap) {
            $moduleConceptMapForked = \App\Models\StudentConceptMap::where('student_id', $student->id)
                ->where('course_concept_map_id', $moduleConceptMap->id)
                ->exists();
        }

        // Blocco B — presentazione .pptx del modulo visibile al corsista: SOLO la
        // versione PUBBLICATA (le bozze dell'admin restano nascoste).
        $modulePresentation = $module->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();

        // V4 — video pubblicato, SOLO se legato alla presentazione pubblicata corrente.
        $moduleVideo = $modulePresentation
            ? $module->videos()->where('presentation_id', $modulePresentation->id)
                ->where('status', 'ready')->whereNotNull('published_at')->latest('published_at')->first()
            : null;

        return view('student.course.module', compact(
            'course', 'module', 'materials', 'quiz', 'finalQuiz',
            'certificationPassed', 'progress', 'prevModule', 'nextModule',
            'canvases', 'isDemo', 'note', 'studentNotes',
            'instructorManualSections', 'instructorNotes', 'teaching',
            'moduleConceptMap', 'moduleConceptMapForked', 'hasModuleDocument',
            'modulePresentation', 'moduleVideo'
        ));
    }

    /**
     * V4 — stream del video pubblicato del modulo (player HTML5, Range/seek). Stesso
     * gate del modulo (checkAccess). Solo il video legato alla presentazione pubblicata corrente.
     */
    public function moduleVideoStream(Course $course, Module $module)
    {
        $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        $presId = $module->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->value('id');
        abort_unless($presId, 404);

        $video = $module->videos()->where('presentation_id', $presId)
            ->where('status', 'ready')->whereNotNull('published_at')->latest('published_at')->first();
        abort_unless($video && $video->file_path && Storage::disk('local')->exists($video->file_path), 404);

        return response()->file(Storage::disk('local')->path($video->file_path), ['Content-Type' => 'video/mp4']);
    }

    /** R4 — ricerca PER-VIDEO nel video pubblicato del modulo (corsista). Stesso gate. */
    public function moduleVideoSearch(Course $course, Module $module, Request $request, \App\Services\Schola\VideoSearchService $search)
    {
        $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);
        $q = trim($request->input('q', ''));
        abort_if($q === '', 422, 'Inserisci una ricerca.');

        $presId = $module->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->value('id');
        $video = $presId ? $module->videos()->where('presentation_id', $presId)
            ->where('status', 'ready')->whereNotNull('published_at')->latest('published_at')->first() : null;
        abort_unless($video && $video->video_ai_id, 404);

        return response()->json(['matches' => $search->perVideo($video->video_ai_id, $q)]);
    }

    /**
     * Blocco B — download della presentazione PUBBLICATA del modulo (corsista).
     * Gate: stesso checkAccess del modulo. File da storage PRIVATO, mai URL diretto.
     */
    public function presentationDownload(Course $course, Module $module)
    {
        $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        $presentation = $module->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
        abort_unless($presentation && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $filename = $presentation->generation_meta['filename'] ?? (\Illuminate\Support\Str::slug($module->title) . '.pptx');

        return response()->download(Storage::disk('local')->path($presentation->file_path), $filename);
    }

    /** Blocco B — anteprima PNG (slide n) della presentazione PUBBLICATA del modulo. */
    public function presentationImage(Course $course, Module $module, int $n, \App\Services\Schola\SlidePreviewService $preview)
    {
        $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        $presentation = $module->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
        abort_unless($presentation && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $images = $preview->imagesFor($presentation->file_path);
        $relPath = $images[$n - 1] ?? abort(404);

        return response()->file(Storage::disk('local')->path($relPath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function completeModule(Course $course, Module $module)
    {
        $student = $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        if ($student->is_demo) {
            return back()->with('success', 'Demo: completamento non salvato.');
        }

        if ($this->isTeachingMode($student, $course)) {
            return back()->with('info', 'Modalità docenza: il completamento non viene registrato.');
        }

        StudentModuleProgress::updateOrCreate(
            ['student_id' => $student->id, 'module_id' => $module->id],
            ['status' => 'completed', 'completed_at' => now()]
        );

        return back()->with('success', 'Modulo completato!');
    }

    public function canvas(Course $course, Module $module, string $canvas)
    {
        $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        return view('student.course.canvas', compact('course', 'module', 'canvas'));
    }

    private function checkAccess(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));

        if ($student->auto_enroll_all_courses && $course->is_active) {
            return $student;
        }

        $enrolled = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();

        if ($enrolled) {
            return $student;
        }

        if ($this->teaches($student, $course) && $course->is_active) {
            return $student;
        }

        abort(403, 'Non sei iscritto a questo corso.');
    }
}
