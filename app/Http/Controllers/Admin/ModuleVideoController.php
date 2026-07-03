<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVideoJob;
use App\Jobs\GenerateVideoScriptJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleVideo;
use App\Services\Schola\VideoIndexService;
use App\Services\Schola\VideoScriptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// V1 — video narrato di un MODULO (lato admin). Solo generazione del COPIONE dalla
// presentazione PUBBLICATA. Gemello di Docente\LessonVideoController.
class ModuleVideoController extends Controller
{
    private function ensureInCourse(Course $course, Module $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    private function publishedPresentation(Module $module)
    {
        return $module->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
    }

    private function currentVideo(Module $module): ?ModuleVideo
    {
        $published = $this->publishedPresentation($module);

        return $published
            ? $module->videos()->where('presentation_id', $published->id)->latest()->first()
            : null;
    }

    public function generateScript(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);

        $published = $this->publishedPresentation($module);
        abort_unless($published, 422,
            'Pubblica prima le slide: il video si genera dalla presentazione pubblicata.');
        abort_unless(!empty($published->spec), 422,
            'Rigenera le slide dal sistema per abilitare il copione del video.');

        $video = $module->videos()->firstOrCreate(
            ['presentation_id' => $published->id],
            ['status' => 'pending', 'script_status' => 'none']
        );

        if ($video->status === 'generating') {
            return back()->with('success', 'Generazione copione già in corso.');
        }

        $video->update(['status' => 'generating']);
        GenerateVideoScriptJob::dispatch($video->id, 'module')->afterResponse();

        return back()->with('success', 'Generazione del copione avviata. Sarà pronto a breve.');
    }

    public function status(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $video = $this->currentVideo($module);

        return response()->json([
            'status' => $video?->status ?? 'none',
            'script_status' => $video?->script_status ?? 'none',
            'failure_reason' => $video?->generation_meta['failure_reason'] ?? null,
        ]);
    }

    /** V2 — correzione a mano di una riga del copione. */
    public function editLine(Request $request, Course $course, Module $module, VideoScriptService $service)
    {
        $this->ensureInCourse($course, $module);
        $data = $request->validate(['slide_number' => 'required|integer|min:1', 'text' => 'required|string|max:3000']);
        $video = $this->currentVideo($module);
        abort_unless($video && !empty($video->script), 404);

        $service->editLine($video, $data['slide_number'], $data['text']);

        return back()->with('success', 'Riga del copione aggiornata.');
    }

    /** V2 — correzione via prompt di una riga (merge mirato). */
    public function editLinePrompt(Request $request, Course $course, Module $module, VideoScriptService $service)
    {
        $this->ensureInCourse($course, $module);
        $data = $request->validate(['slide_number' => 'required|integer|min:1', 'instruction' => 'required|string|max:2000']);
        $video = $this->currentVideo($module);
        abort_unless($video && !empty($video->script), 404);

        $service->editLineViaPrompt($video, $data['slide_number'], $data['instruction']);

        return back()->with('success', 'Riga del copione ritoccata.');
    }

    /** V2 — conferma copione (solo cambio stato; il GATE costi/render è V3). */
    public function confirm(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $video = $this->currentVideo($module);
        abort_unless($video && $video->script_status === 'draft' && !empty($video->script), 422,
            'Nessun copione in bozza da confermare.');

        $video->update(['script_status' => 'confirmed']);

        return back()->with('success', 'Copione confermato.');
    }

    /** V3 [GATE costi] — genera il video MP4 (TTS + ffmpeg). Solo da copione confermato. */
    public function generateVideo(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $video = $this->currentVideo($module);
        abort_unless($video && $video->script_status === 'confirmed', 422,
            'Conferma il copione prima di generare il video.');

        if ($video->status === 'generating') {
            return back()->with('success', 'Generazione video già in corso.');
        }

        $video->update(['status' => 'generating']);
        GenerateVideoJob::dispatch($video->id, 'module')->afterResponse();

        return back()->with('success', 'Generazione del video avviata. Sarà pronto a breve.');
    }

    /**
     * R3/V4 — pubblica il video del modulo. GATE: pubblicabile solo se interrogabile.
     * Generato → "indicizza e pubblica" in un colpo (indicizzazione gratis); a successo
     * published_at. Indicizzazione fallita → publish annullato, resta bozza.
     */
    public function publishVideo(Course $course, Module $module, VideoIndexService $indexer)
    {
        $this->ensureInCourse($course, $module);
        $video = $this->currentVideo($module);
        abort_unless($video && $video->status === 'ready', 422, 'Genera prima il video.');

        if (!$video->indexed_at || !$video->video_ai_id) {
            try {
                $indexer->indexGenerated($video);
                $video->refresh();
            } catch (\Throwable $e) {
                return back()->with('error', 'Pubblicazione annullata: indicizzazione non riuscita (' . $e->getMessage() . '). Il video resta in bozza.');
            }
        }

        $video->update(['published_at' => now()]);

        return back()->with('success', 'Video pubblicato e indicizzato: visibile ai corsisti e ricercabile.');
    }

    /** V4 — ritira il video del modulo. */
    public function unpublishVideo(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $video = $this->currentVideo($module);
        abort_unless($video, 404);

        $video->update(['published_at' => null]);

        return back()->with('success', 'Video ritirato: non è più visibile ai corsisti.');
    }

    /** V3 — download del video MP4 (lato admin). Storage privato. */
    public function downloadVideo(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $video = $this->currentVideo($module);
        abort_unless($video && $video->status === 'ready' && $video->file_path
            && Storage::disk('local')->exists($video->file_path), 404);

        return response()->download(Storage::disk('local')->path($video->file_path), Str::slug($module->title) . '.mp4');
    }
}
