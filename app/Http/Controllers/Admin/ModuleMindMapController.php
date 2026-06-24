<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Services\MindMapGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModuleMindMapController extends Controller
{
    public function __construct(
        private MindMapGenerationService $mindMapService,
    ) {}

    /**
     * Genera (o rigenera) la mindmap chiamando Claude API.
     */
    public function generate(Request $request, Course $course, Module $module)
    {
        if ($module->course_id !== $course->id) {
            abort(404);
        }

        try {
            $markdown = $this->mindMapService->generate($module);

            $module->update([
                'mindmap_markdown' => $markdown,
                'mindmap_content_hash' => $module->currentContentHash(),
                'mindmap_generated_at' => now(),
            ]);

            Log::info('MindMap saved', [
                'module_id' => $module->id,
                'course_id' => $course->id,
                'by_admin' => session('admin_email') ?? 'unknown',
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'mindmap_markdown' => $markdown,
                    'mindmap_generated_at' => $module->mindmap_generated_at?->toIso8601String(),
                ]);
            }

            return redirect()
                ->route('admin.courses.modules.edit', [$course, $module])
                ->with('success', 'Mappa mentale generata con successo.')
                ->with('mindmap_just_generated', true);

        } catch (Throwable $e) {
            Log::error('MindMap generation failed', [
                'module_id' => $module->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Errore generazione mappa: ' . $e->getMessage());
        }
    }

    /**
     * Update manuale del markdown (instructor edita il testo generato).
     */
    public function update(Request $request, Course $course, Module $module)
    {
        if ($module->course_id !== $course->id) {
            abort(404);
        }

        $data = $request->validate([
            'mindmap_markdown' => 'nullable|string|max:20000',
        ]);

        // Se svuota il campo, considerala "non generata"
        if (empty($data['mindmap_markdown'])) {
            $module->update([
                'mindmap_markdown' => null,
                'mindmap_content_hash' => null,
                'mindmap_generated_at' => null,
            ]);
            return back()->with('success', 'Mappa mentale rimossa.');
        }

        // Edit manuale → aggiorna hash al content corrente (non viene marcata stale subito)
        $module->update([
            'mindmap_markdown' => $data['mindmap_markdown'],
            'mindmap_content_hash' => $module->currentContentHash(),
            'mindmap_generated_at' => now(),
        ]);

        Log::info('MindMap manually updated', [
            'module_id' => $module->id,
            'by_admin' => session('admin_email') ?? 'unknown',
        ]);

        return back()->with('success', 'Mappa mentale aggiornata.');
    }
}
