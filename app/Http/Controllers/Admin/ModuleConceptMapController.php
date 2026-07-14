<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseConceptMap;
use App\Models\Module;
use App\Services\ConceptMapGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mappa concettuale A LIVELLO MODULO, esposta inline nella modifica del modulo
 * (speculare a ModuleMindMapController per la mappa mentale). Riusa il modello
 * CourseConceptMap (scope module_id) e ConceptMapGenerationService: qui è solo
 * un wrapper sottile che genera in BOZZA e torna alla pagina del modulo.
 */
class ModuleConceptMapController extends Controller
{
    public function __construct(private ConceptMapGenerationService $conceptMapService)
    {
    }

    private function mapFor(Course $course, Module $module): ?CourseConceptMap
    {
        return $course->conceptMaps()->where('module_id', $module->id)->first();
    }

    /** Genera (o rigenera) la mappa concettuale del modulo. Resta in BOZZA per revisione. */
    public function generate(Course $course, Module $module)
    {
        abort_unless($module->course_id === $course->id, 404);

        if (empty($module->content)) {
            return back()->with('error', 'Il modulo non ha contenuto: salvalo prima di generare la mappa concettuale.');
        }

        $map = $this->mapFor($course, $module) ?? $course->conceptMaps()->create([
            'module_id' => $module->id,
            'title' => "Mappa concettuale — {$module->title}",
            'description' => null,
            'visibility' => CourseConceptMap::VISIBILITY_DRAFT,
            'sort_order' => 0,
            'data' => ['nodes' => [], 'edges' => [], 'physics' => ['enabled' => true]],
        ]);

        try {
            $graph = $this->conceptMapService->generate($course, $module);
            $map->update([
                'data' => $graph,
                'ai_generated' => true,
                'ai_generated_at' => now(),
                'content_hash' => $map->currentContentHash(),
                // Torna sempre in bozza: l'output AV va rivisto prima di mostrarlo ai discenti.
                'visibility' => CourseConceptMap::VISIBILITY_DRAFT,
            ]);

            Log::info('Module ConceptMap generated', [
                'module_id' => $module->id,
                'concept_map_id' => $map->id,
                'by_admin' => session('admin_email') ?? 'unknown',
            ]);

            return redirect()
                ->route('admin.courses.modules.edit', [$course, $module])
                ->with('success', 'Mappa concettuale generata in bozza. Verifica nodi e relazioni, poi pubblicala.');
        } catch (Throwable $e) {
            Log::error('Module ConceptMap generation failed', [
                'module_id' => $module->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Errore generazione mappa concettuale: ' . $e->getMessage());
        }
    }

    /** Pubblica o rimette in bozza la mappa concettuale del modulo. */
    public function setVisibility(Request $request, Course $course, Module $module)
    {
        abort_unless($module->course_id === $course->id, 404);
        $map = $this->mapFor($course, $module);
        abort_unless($map, 404);

        $data = $request->validate(['visibility' => 'required|in:draft,published']);
        $map->update(['visibility' => $data['visibility']]);

        return back()->with('success', $data['visibility'] === CourseConceptMap::VISIBILITY_PUBLISHED
            ? 'Mappa concettuale pubblicata: ora è visibile ai discenti.'
            : 'Mappa concettuale rimessa in bozza: non è più visibile ai discenti.');
    }

    /** Elimina la mappa concettuale del modulo. */
    public function destroy(Course $course, Module $module)
    {
        abort_unless($module->course_id === $course->id, 404);
        $this->mapFor($course, $module)?->delete();

        return back()->with('success', 'Mappa concettuale eliminata.');
    }
}
