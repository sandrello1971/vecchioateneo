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
        $publishedPres = $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
        $publishedPresId = $publishedPres?->id;
        $hasPresentation = $publishedPres !== null;
        $presentationSlides = (int) ($publishedPres->generation_meta['slides'] ?? 0);

        // V4 — video pubblicato, SOLO se legato alla presentazione pubblicata corrente
        // (un video da una presentazione ritirata/cambiata non resta esposto).
        $hasVideo = $publishedPresId && $lesson->videos()->where('presentation_id', $publishedPresId)
            ->where('status', 'ready')->whereNotNull('published_at')->exists();

        // Video CARICATI dal docente e pubblicati sulla lezione (player + ricerca in-video).
        $uploadedVideos = $lesson->uploadedVideos()
            ->where('status', 'ready')->whereNotNull('published_at')
            ->orderBy('created_at')->get();

        return view('student.lezioni.show', compact(
            'class', 'lesson', 'publication', 'bodyHtml', 'mediaMaterials', 'notes', 'teacherNotes',
            'generated', 'usage', 'hasPresentation', 'hasVideo', 'presentationSlides', 'uploadedVideos'
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
     * P21 — immagine PNG della n-esima slide della presentazione PUBBLICATA, per il
     * visualizzatore (lightbox) dello studente. Stesso gate: iscrizione attiva +
     * lezione pubblicata. Le bozze del docente restano nascoste.
     */
    public function presentationSlide(SchoolClass $class, Lesson $lesson, int $n, \App\Services\Schola\SlidePreviewService $preview)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);

        $presentation = $lesson->presentations()->where('status', 'ready')
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

    /** Gate + risoluzione di un video CARICATO pubblicato di questa lezione. */
    private function publishedUploadedVideo(SchoolClass $class, Lesson $lesson, string $videoId): \App\Models\UploadedVideo
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);

        $video = $lesson->uploadedVideos()->where('id', $videoId)
            ->where('status', 'ready')->whereNotNull('published_at')->first();
        abort_unless($video, 404);

        return $video;
    }

    /** Stream mp4 locale di un video caricato pubblicato (Range/seek via BinaryFileResponse). */
    public function uploadedVideo(SchoolClass $class, Lesson $lesson, string $video)
    {
        $uploaded = $this->publishedUploadedVideo($class, $lesson, $video);
        abort_unless($uploaded->file_path && Storage::disk('local')->exists($uploaded->file_path), 404);

        return response()->file(Storage::disk('local')->path($uploaded->file_path), ['Content-Type' => 'video/mp4']);
    }

    /** Ricerca PER-VIDEO in un video caricato pubblicato. Riuso VideoSearchService. */
    public function uploadedVideoSearch(SchoolClass $class, Lesson $lesson, string $video, \Illuminate\Http\Request $request, \App\Services\Schola\VideoSearchService $search)
    {
        $uploaded = $this->publishedUploadedVideo($class, $lesson, $video);
        $q = trim((string) $request->input('q', ''));
        abort_if($q === '', 422, 'Inserisci una ricerca.');
        abort_unless($uploaded->video_ai_id, 404);

        return response()->json(['matches' => $search->perVideo($uploaded->video_ai_id, $q)]);
    }

    /** "Chiedi al video" su un video caricato pubblicato: Q&A grounded. */
    public function uploadedVideoAsk(SchoolClass $class, Lesson $lesson, string $video, \Illuminate\Http\Request $request, \App\Services\VideoAIService $ai)
    {
        $uploaded = $this->publishedUploadedVideo($class, $lesson, $video);
        $q = trim((string) $request->input('question', ''));
        abort_if($q === '', 422, 'Scrivi una domanda.');
        abort_unless($uploaded->video_ai_id, 404);

        return response()->json($ai->askVideo($uploaded->video_ai_id, $q, (array) $request->input('history', [])));
    }

    /** "Chiedi al video" sul video narrato (generato) pubblicato della lezione. */
    public function videoAsk(SchoolClass $class, Lesson $lesson, \Illuminate\Http\Request $request, \App\Services\VideoAIService $ai)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertLessonPublished($lesson, $class);
        $q = trim((string) $request->input('question', ''));
        abort_if($q === '', 422, 'Scrivi una domanda.');

        $publishedPresId = $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->value('id');
        $video = $publishedPresId ? $lesson->videos()->where('presentation_id', $publishedPresId)
            ->where('status', 'ready')->whereNotNull('published_at')->latest('published_at')->first() : null;
        abort_unless($video && $video->video_ai_id, 404);

        return response()->json($ai->askVideo($video->video_ai_id, $q, (array) $request->input('history', [])));
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
