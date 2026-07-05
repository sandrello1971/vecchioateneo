<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\IngestUploadedVideoJob;
use App\Models\ArtifactPublication;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\UploadedVideo;
use App\Services\Schola\VideoSearchService;
use App\Support\VideoAiConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Video CARICATI dal docente (non generati): inviati a noscite-videoai per l'analisi
 * COMPLETA (trascrizione + frame + Vision) → riproducibili, ricercabili al loro interno
 * e (via un TeachingArtifact transcript) indicizzati nella Minerva. Contesto lezione
 * (lesson_id) o materiale generico (subject_id). Solo il proprietario gestisce; una
 * lezione può avere più video. Gate DPA per i sub-processori esterni (Whisper/Vision).
 */
class UploadedVideoController extends Controller
{
    public function __construct(private VideoSearchService $search) {}

    private function teacherId(): string
    {
        return session('student_id');
    }

    private function authorizeOwner(UploadedVideo $video): void
    {
        abort_unless($video->teacher_id === $this->teacherId(), 403);
    }

    /**
     * Carica un video (dalla lezione o come materiale generico). Gate DPA 'video',
     * salvataggio mp4 locale, record 'processing', dispatch dell'ingest Vision async.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo,video/x-matroska,video/x-m4v|max:512000',
            'lesson_id' => 'nullable|uuid|exists:lessons,id',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
        ]);

        $owner = Student::find($this->teacherId());

        // Contesto: dalla lezione (materia ereditata) o materiale generico (subject_id).
        $lesson = null;
        $subjectId = $data['subject_id'] ?? null;
        if (!empty($data['lesson_id'])) {
            $lesson = Lesson::with('topic')->findOrFail($data['lesson_id']);
            abort_unless($lesson->teacher_id === $this->teacherId(), 403);
            $subjectId = $lesson->topic?->subject_id;
        }

        // Gate DPA: il video passa da Whisper+Vision (sub-processori esterni).
        if (VideoAiConsent::blocked($owner, 'video')) {
            return back()->withInput()->with('error', VideoAiConsent::MESSAGE);
        }

        $video = new UploadedVideo([
            'teacher_id' => $this->teacherId(),
            'lesson_id' => $lesson?->id,
            'subject_id' => $subjectId,
            'school_id' => $owner?->school_id,
            'title' => $data['title'],
            'source_filename' => $request->file('file')->getClientOriginalName(),
            'status' => 'processing',
        ]);
        $video->save();

        // mp4 su storage privato (player locale, indipendente da videoai).
        $ext = strtolower($request->file('file')->getClientOriginalExtension() ?: 'mp4');
        $path = $request->file('file')->storeAs(
            "uploaded-videos/{$video->teacher_id}/{$video->id}",
            "source.{$ext}",
            'local'
        );
        $video->update(['file_path' => $path]);

        // Ingest lungo (upload + Vision + polling): sulla coda (queue worker), non
        // afterResponse — non deve occupare un worker php-fpm per minuti.
        IngestUploadedVideoJob::dispatch($video->id);

        $msg = 'Video caricato. Analisi (trascrizione + immagini) in corso…';
        if ($lesson) {
            return redirect()->route('docente.lessons.show', $lesson)->with('success', $msg);
        }

        return redirect()->route('docente.videos.index')->with('success', $msg);
    }

    /** Lista dei video caricati come MATERIALE (senza lezione) del docente. */
    public function index()
    {
        $videos = UploadedVideo::where('teacher_id', $this->teacherId())
            ->whereNull('lesson_id')
            ->with('subject')
            ->latest()
            ->get();

        $subjects = \App\Models\Subject::orderBy('name')->get();
        $videoAiDpaMissing = VideoAiConsent::dpaMissing(Student::find($this->teacherId()));

        return view('docente.video-caricati.index', compact('videos', 'subjects', 'videoAiDpaMissing'));
    }

    /** Stato per il polling UI (avanzamento analisi). */
    public function status(UploadedVideo $video)
    {
        $this->authorizeOwner($video);

        return response()->json([
            'status' => $video->status,
            'progress' => (int) ($video->meta['progress'] ?? 0),
            'failure_reason' => $video->failure_reason,
            'searchable' => $video->isSearchable(),
            'published' => $video->isPublished(),
        ]);
    }

    /** Stream mp4 locale (anteprima proprietario). Range/seek via BinaryFileResponse. */
    public function stream(UploadedVideo $video)
    {
        $this->authorizeOwner($video);
        abort_unless($video->file_path && Storage::disk('local')->exists($video->file_path), 404);

        return response()->file(Storage::disk('local')->path($video->file_path), ['Content-Type' => 'video/mp4']);
    }

    /** Ricerca PER-VIDEO (anteprima proprietario). Riuso VideoSearchService::perVideo. */
    public function search(Request $request, UploadedVideo $video)
    {
        $this->authorizeOwner($video);
        $q = trim((string) $request->input('q', ''));
        abort_if($q === '', 422, 'Inserisci una ricerca.');
        abort_unless($video->isSearchable(), 404);

        return response()->json(['matches' => $this->search->perVideo($video->video_ai_id, $q)]);
    }

    /**
     * Pubblica il video: visibile agli studenti delle classi dove la lezione è
     * pubblicata + indicizza il suo transcript come scope='class' (RAG Minerva di
     * classe), riusando ArtifactPublication. Best-effort sull'ingestion.
     */
    public function publish(UploadedVideo $video)
    {
        $this->authorizeOwner($video);
        abort_unless($video->status === 'ready', 422, 'Attendi la fine dell\'analisi prima di pubblicare.');
        abort_unless($video->lesson_id, 422, 'Solo i video legati a una lezione si pubblicano agli studenti.');

        $video->update(['published_at' => now()]);

        // Pubblica il transcript sulle classi dove la lezione è già pubblicata.
        $lesson = $video->lesson;
        if ($video->artifact_id && $lesson) {
            $classIds = $lesson->publications()->pluck('school_class_id');
            foreach ($classIds as $classId) {
                $publication = ArtifactPublication::updateOrCreate(
                    ['teaching_artifact_id' => $video->artifact_id, 'school_class_id' => $classId],
                    ['students_can_generate' => true, 'downloadable' => false,
                        'published_at' => now(), 'rag_status' => 'pending', 'rag_failure_reason' => null]
                );
                \App\Jobs\IngestPublicationRagJob::dispatch($publication->id)->afterResponse();
            }
        }

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Video pubblicato: visibile agli studenti e ricercabile.');
    }

    /** Ritira il video (non più visibile agli studenti). Rimuove le pubblicazioni RAG. */
    public function unpublish(UploadedVideo $video)
    {
        $this->authorizeOwner($video);
        $video->update(['published_at' => null]);

        if ($video->artifact_id) {
            $pubs = ArtifactPublication::where('teaching_artifact_id', $video->artifact_id)->get();
            foreach ($pubs as $pub) {
                $id = $pub->id;
                $pub->delete();
                \App\Jobs\PurgeWithdrawnPublicationJob::dispatch($id)->afterResponse();
            }
        }

        $target = $video->lesson_id
            ? redirect()->route('docente.lessons.show', $video->lesson_id)
            : redirect()->route('docente.videos.index');

        return $target->with('success', 'Video ritirato: non più visibile agli studenti.');
    }

    /** Elimina il video (soft delete) + mp4 locale + chunk RAG teacher_private. */
    public function destroy(UploadedVideo $video)
    {
        $this->authorizeOwner($video);

        if ($video->file_path) {
            Storage::disk('local')->deleteDirectory("uploaded-videos/{$video->teacher_id}/{$video->id}");
        }
        // I chunk RAG e l'artefatto seguono la cancellazione dell'artefatto transcript.
        if ($video->artifact) {
            \App\Models\DocumentRag::query()
                ->where('metadata->artifact_id', $video->artifact_id)->delete();
            $video->artifact->delete();
        }

        $lessonId = $video->lesson_id;
        $video->delete();

        $target = $lessonId
            ? redirect()->route('docente.lessons.show', $lessonId)
            : redirect()->route('docente.videos.index');

        return $target->with('success', 'Video eliminato.');
    }
}
