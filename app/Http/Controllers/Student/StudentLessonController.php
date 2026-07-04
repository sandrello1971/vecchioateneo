<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ResolvesScholaAccess;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\StudentLessonNote;
use App\Models\TeachingDocument;
use App\Services\NoteAnchorInjector;
use Illuminate\Support\Facades\Storage;

// Fruizione studente di una LEZIONE pubblicata (P20b): corpo con appunti per
// paragrafo, materiali audio/video con seek, Minerva di lezione. Defense in
// depth: iscrizione attiva + lezione effettivamente pubblicata sulla classe.
class StudentLessonController extends Controller
{
    use ResolvesScholaAccess;

    /** 403 se la lezione non è pubblicata su questa classe (niente leak). */
    private function assertLessonPublished(Lesson $lesson, SchoolClass $class): void
    {
        abort_unless(
            LessonPublication::where('lesson_id', $lesson->id)
                ->where('school_class_id', $class->id)->exists(),
            403,
            'Questa lezione non è disponibile nella tua classe.'
        );
    }

    public function show(SchoolClass $class, Lesson $lesson)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);
        abort_unless($lesson->generation_status === 'ready' && !empty($lesson->content), 404);

        $lesson->load('topic');
        $publication = LessonPublication::where('lesson_id', $lesson->id)
            ->where('school_class_id', $class->id)->first();

        // Corpo: markdown → HTML → anchor per paragrafo (appunti).
        $bodyHtml = app(NoteAnchorInjector::class)->inject(schola_markdown($lesson->content));

        // Materiali audio/video della lezione (ricerca video / player con seek).
        $mediaMaterials = $lesson->teachingDocuments()
            ->where('status', 'ready')
            ->whereIn('source_type', ['audio', 'youtube'])
            ->orderBy('created_at')
            ->get();

        // Appunti PERSONALI dello studente (privati: solo suoi, mai del docente).
        $notes = StudentLessonNote::where('student_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->get(['id', 'anchor', 'content'])
            ->keyBy('anchor');

        // Note del DOCENTE per paragrafo (didattiche: visibili a tutti gli studenti).
        $teacherNotes = \App\Models\LessonTeacherNote::where('lesson_id', $lesson->id)
            ->get(['anchor', 'content'])
            ->keyBy('anchor');

        // Auto-generati PRIVATI dello studente per questa lezione (P20c) + rate limit.
        $generated = \App\Models\StudentGeneratedArtifact::where('student_id', $student->id)
            ->where('lesson_publication_id', $publication->id)
            ->orderByDesc('created_at')
            ->get();
        $usage = app(\App\Services\Schola\ScholaUsage::class)->generationStatus($student->id);

        // Presentazione .pptx pronta (P21): scaricabile, mai generabile dallo studente.
        // Solo la versione PUBBLICATA è visibile: le bozze del formatore restano nascoste.
        $publishedPresId = $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->value('id');
        $hasPresentation = $publishedPresId !== null;

        // V4 — video pubblicato, SOLO se legato alla presentazione pubblicata corrente
        // (un video da una presentazione ritirata/cambiata non resta esposto).
        $hasVideo = $publishedPresId && $lesson->videos()->where('presentation_id', $publishedPresId)
            ->where('status', 'ready')->whereNotNull('published_at')->exists();

        return view('student.lezioni.show', compact(
            'class', 'lesson', 'publication', 'bodyHtml', 'mediaMaterials', 'notes', 'teacherNotes',
            'generated', 'usage', 'hasPresentation', 'hasVideo'
        ));
    }

    /**
     * V4 — stream del video pubblicato della lezione (player HTML5, supporta Range/seek
     * via BinaryFileResponse). Stesso gate della presentazione: iscrizione attiva +
     * lezione pubblicata. Solo il video legato alla presentazione pubblicata corrente.
     */
    public function video(SchoolClass $class, Lesson $lesson)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);

        $publishedPresId = $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->value('id');
        abort_unless($publishedPresId, 404);

        $video = $lesson->videos()->where('presentation_id', $publishedPresId)
            ->where('status', 'ready')->whereNotNull('published_at')->latest('published_at')->first();
        abort_unless($video && $video->file_path && Storage::disk('local')->exists($video->file_path), 404);

        return response()->file(Storage::disk('local')->path($video->file_path), ['Content-Type' => 'video/mp4']);
    }

    /**
     * R4 — ricerca PER-VIDEO nel video pubblicato della lezione. Stesso gate del video
     * (iscrizione attiva + lezione pubblicata). Proxy a videoai sul video_ai_id del video.
     */
    public function videoSearch(SchoolClass $class, Lesson $lesson, \Illuminate\Http\Request $request, \App\Services\Schola\VideoSearchService $search)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);
        $q = trim($request->input('q', ''));
        abort_if($q === '', 422, 'Inserisci una ricerca.');

        $publishedPresId = $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->value('id');
        $video = $publishedPresId ? $lesson->videos()->where('presentation_id', $publishedPresId)
            ->where('status', 'ready')->whereNotNull('published_at')->latest('published_at')->first() : null;
        abort_unless($video && $video->video_ai_id, 404);

        return response()->json(['matches' => $search->perVideo($video->video_ai_id, $q)]);
    }

    /**
     * Download della presentazione .pptx di una lezione pubblicata (P21). Lo
     * studente può SOLO scaricare (niente generazione). Stesso criterio di accesso
     * della fruizione: iscrizione attiva + lezione pubblicata sulla sua classe.
     * File da storage PRIVATO, mai URL diretto.
     */
    public function presentation(SchoolClass $class, Lesson $lesson)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);

        // Solo la versione PUBBLICATA, la più recente per data di pubblicazione.
        $presentation = $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
        abort_unless($presentation && $presentation->file_path
            && Storage::disk('local')->exists($presentation->file_path), 404);

        $filename = $presentation->generation_meta['filename'] ?? (\Illuminate\Support\Str::slug($lesson->title) . '.pptx');

        return response()->download(Storage::disk('local')->path($presentation->file_path), $filename);
    }

    /**
     * Streaming del file audio di un materiale della lezione, con Range (seek del
     * player e deep-link delle citazioni ?t=secondi). Difeso da enrollment +
     * pubblicazione + appartenenza del materiale alla lezione.
     */
    public function materialSource(SchoolClass $class, Lesson $lesson, TeachingDocument $document)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);

        abort_unless($document->lesson_id === $lesson->id, 404);
        abort_unless($document->source_type === 'audio', 404);

        $files = $document->source_files ?? [];
        abort_unless(!empty($files) && Storage::disk('local')->exists($files[0]), 404);

        // response()->file → BinaryFileResponse: gestisce le Range request (seek).
        return response()->file(Storage::disk('local')->path($files[0]));
    }
}
