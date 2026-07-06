<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVideoJob;
use App\Jobs\GenerateVideoScriptJob;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Services\Schola\VideoIndexService;
use App\Services\Schola\VideoScriptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// V1 — video narrato di una lezione (lato docente). Per ora solo la generazione del
// COPIONE (Claude) dalla presentazione PUBBLICATA. Niente TTS/mp4 (V3). Solo proprietario.
class LessonVideoController extends Controller
{
    private function authorizeOwner(Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === session('student_id'), 403);
    }

    /** Presentazione pubblicata corrente della lezione (sorgente del video), o null. */
    private function publishedPresentation(Lesson $lesson)
    {
        return $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
    }

    /** Video agganciato alla presentazione pubblicata corrente, o null. */
    private function currentVideo(Lesson $lesson): ?LessonVideo
    {
        $published = $this->publishedPresentation($lesson);

        return $published
            ? $lesson->videos()->where('presentation_id', $published->id)->latest()->first()
            : null;
    }

    /** Avvia la generazione del copione dalla presentazione pubblicata. */
    public function generateScript(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);

        $published = $this->publishedPresentation($lesson);
        abort_unless($published, 422,
            'Pubblica prima le slide: il video si genera dalla presentazione pubblicata.');
        abort_unless(!empty($published->spec), 422,
            'Rigenera le slide dal sistema per abilitare il copione del video.');

        $video = $lesson->videos()->firstOrCreate(
            ['presentation_id' => $published->id],
            ['status' => 'pending', 'script_status' => 'none']
        );

        if ($video->status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Generazione copione già in corso.');
        }

        $video->update(['status' => 'generating']);
        GenerateVideoScriptJob::dispatch($video->id, 'lesson')->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Generazione del copione avviata. Sarà pronto a breve.');
    }

    public function status(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $video = $this->currentVideo($lesson);

        return response()->json([
            'status' => $video?->status ?? 'none',
            'script_status' => $video?->script_status ?? 'none',
            'failure_reason' => $video?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    /** V2 — correzione a mano di una riga del copione. */
    public function editLine(Request $request, Lesson $lesson, VideoScriptService $service)
    {
        $this->authorizeOwner($lesson);
        $data = $request->validate(['slide_number' => 'required|integer|min:1', 'text' => 'required|string|max:3000']);
        $video = $this->currentVideo($lesson);
        abort_unless($video && !empty($video->script), 404);

        $service->editLine($video, $data['slide_number'], $data['text']);

        return redirect()->route('docente.lessons.show', $lesson)->with('success', 'Riga del copione aggiornata.');
    }

    /** V2 — correzione via prompt di una riga (merge mirato). */
    public function editLinePrompt(Request $request, Lesson $lesson, VideoScriptService $service)
    {
        $this->authorizeOwner($lesson);
        $data = $request->validate(['slide_number' => 'required|integer|min:1', 'instruction' => 'required|string|max:2000']);
        $video = $this->currentVideo($lesson);
        abort_unless($video && !empty($video->script), 404);

        $service->editLineViaPrompt($video, $data['slide_number'], $data['instruction']);

        return redirect()->route('docente.lessons.show', $lesson)->with('success', 'Riga del copione ritoccata.');
    }

    /** V2 — conferma copione (solo cambio stato; il GATE costi/render è V3). */
    public function confirm(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $video = $this->currentVideo($lesson);
        abort_unless($video && $video->script_status === 'draft' && !empty($video->script), 422,
            'Nessun copione in bozza da confermare.');

        $video->update(['script_status' => 'confirmed']);

        return redirect()->route('docente.lessons.show', $lesson)->with('success', 'Copione confermato.');
    }

    /** V3 [GATE costi] — genera il video MP4 (TTS + ffmpeg). Solo da copione confermato. */
    public function generateVideo(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $video = $this->currentVideo($lesson);
        abort_unless($video && $video->script_status === 'confirmed', 422,
            'Conferma il copione prima di generare il video.');

        if ($video->status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)->with('success', 'Generazione video già in corso.');
        }

        $video->update(['status' => 'generating']);
        GenerateVideoJob::dispatch($video->id, 'lesson')->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)->with('success', 'Generazione del video avviata. Sarà pronto a breve.');
    }

    /**
     * R3/V4 — pubblica il video. GATE: pubblicabile SOLO se interrogabile. Per i video
     * GENERATI l'indicizzazione è gratis → "indicizza e pubblica" in un colpo: se il
     * video non è indicizzato lo indicizza (R2) e solo a successo imposta published_at.
     * Indicizzazione fallita (videoai down) → publish annullato, resta bozza.
     */
    public function publishVideo(Lesson $lesson, VideoIndexService $indexer)
    {
        $this->authorizeOwner($lesson);
        $video = $this->currentVideo($lesson);
        abort_unless($video && $video->status === 'ready', 422, 'Genera prima il video.');

        // Indicizzazione per la RICERCA dentro il video: best-effort, NON blocca la
        // pubblicazione (il video è comunque riproducibile). Se fallisce, si può
        // reindicizzare dopo (pulsante Pubblica/Rigenera).
        $indexWarning = null;
        if (!$video->indexed_at || !$video->video_ai_id) {
            try {
                $indexer->indexGenerated($video);
                $video->refresh();
            } catch (\Throwable $e) {
                \Log::warning('[schola] indicizzazione video lezione fallita, pubblico comunque', ['video' => $video->id, 'error' => $e->getMessage()]);
                $indexWarning = $e->getMessage();
            }
        }

        $video->update(['published_at' => now()]);

        return redirect()->route('docente.lessons.show', $lesson)->with(
            $indexWarning ? 'warning' : 'success',
            $indexWarning
                ? 'Video pubblicato: visibile agli studenti. La ricerca dentro il video non è ancora disponibile (indicizzazione non riuscita: ' . $indexWarning . ').'
                : 'Video pubblicato e indicizzato: visibile agli studenti e ricercabile.'
        );
    }

    /** V4 — ritira il video (non più visibile agli studenti). */
    public function unpublishVideo(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $video = $this->currentVideo($lesson);
        abort_unless($video, 404);

        $video->update(['published_at' => null]);

        return redirect()->route('docente.lessons.show', $lesson)->with('success', 'Video ritirato: non è più visibile agli studenti.');
    }

    /** V3 — download del video MP4 (lato docente proprietario). Storage privato. */
    public function downloadVideo(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $video = $this->currentVideo($lesson);
        abort_unless($video && $video->status === 'ready' && $video->file_path
            && Storage::disk('local')->exists($video->file_path), 404);

        return response()->download(Storage::disk('local')->path($video->file_path), Str::slug($lesson->title) . '.mp4');
    }
}
