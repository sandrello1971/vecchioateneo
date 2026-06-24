<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseConceptMapRequest;
use App\Http\Requests\Admin\UpdateCourseConceptMapRequest;
use App\Models\Course;
use App\Models\CourseConceptMap;
use App\Models\Module;
use App\Services\ConceptMapGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CourseConceptMapController extends Controller
{
    public function __construct(
        private ConceptMapGenerationService $conceptMapService,
    ) {}

    public function index(Course $course)
    {
        $maps = $course->conceptMaps()->with('module')->orderBy('module_id')->orderBy('created_at')->get();
        $courseMap = $maps->firstWhere('module_id', null);
        $mapsByModule = $maps->whereNotNull('module_id')->keyBy('module_id');
        $modules = $course->modules()->orderBy('sort_order')->get();

        return view('admin.courses.concept-maps.index', compact('course', 'maps', 'courseMap', 'mapsByModule', 'modules'));
    }

    public function create(Course $course)
    {
        $modules = $course->modules()->orderBy('sort_order')->get(['id', 'title', 'sort_order']);
        $usedModuleIds = $course->conceptMaps()->whereNotNull('module_id')->pluck('module_id')->all();
        $hasCourseMap = $course->conceptMaps()->whereNull('module_id')->exists();

        return view('admin.courses.concept-maps.create', compact('course', 'modules', 'usedModuleIds', 'hasCourseMap'));
    }

    public function store(StoreCourseConceptMapRequest $request, Course $course)
    {
        $moduleId = $request->input('module_id') ?: null;
        $this->guardUniqueScope($course, $moduleId);

        $map = $course->conceptMaps()->create([
            'module_id' => $moduleId,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'visibility' => $request->input('visibility', CourseConceptMap::VISIBILITY_DRAFT),
            'sort_order' => $request->input('sort_order', 0),
            'data' => ['nodes' => [], 'edges' => [], 'physics' => ['enabled' => true]],
        ]);

        return redirect()
            ->route('admin.courses.concept-maps.edit', [$course, $map])
            ->with('success', 'Mappa concettuale creata. Ora puoi popolarla manualmente o con AI.');
    }

    /**
     * "Crea con AI in 1 click": crea il record con titolo automatico,
     * lancia subito la generazione, e redirige all'editor con la mappa popolata.
     */
    public function autoCreate(Request $request, Course $course)
    {
        $data = $request->validate([
            'module_id' => 'nullable|uuid',
        ]);
        $moduleId = $data['module_id'] ?? null;

        $module = null;
        if ($moduleId) {
            $module = Module::where('id', $moduleId)->where('course_id', $course->id)->firstOrFail();
        }

        $this->guardUniqueScope($course, $moduleId);

        $title = $module
            ? "Mappa concettuale — {$module->title}"
            : "Mappa concettuale del corso";

        $map = $course->conceptMaps()->create([
            'module_id' => $moduleId,
            'title' => $title,
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
            ]);
            $note = ' Verifica i nodi/archi e pubblicala quando sei soddisfatto.';
            Log::info('ConceptMap autoCreate ok', [
                'concept_map_id' => $map->id,
                'course_id' => $course->id,
                'module_id' => $moduleId,
            ]);
        } catch (Throwable $e) {
            Log::error('ConceptMap autoCreate AI failed (record kept empty)', [
                'concept_map_id' => $map->id,
                'error' => $e->getMessage(),
            ]);
            $note = ' MA la generazione AI è fallita: ' . $e->getMessage() . '. Puoi popolarla manualmente o riprovare con "Genera con AI".';
        }

        return redirect()
            ->route('admin.courses.concept-maps.edit', [$course, $map])
            ->with('success', 'Mappa creata.' . $note);
    }

    /**
     * Lancia un'eccezione se esiste già una mappa per questo (course, module).
     */
    private function guardUniqueScope(Course $course, ?string $moduleId): void
    {
        $exists = CourseConceptMap::where('course_id', $course->id)
            ->where(function ($q) use ($moduleId) {
                $moduleId === null
                    ? $q->whereNull('module_id')
                    : $q->where('module_id', $moduleId);
            })
            ->exists();
        if ($exists) {
            abort(422, $moduleId === null
                ? 'Esiste già una mappa concettuale per questo corso. Modifica quella esistente.'
                : 'Esiste già una mappa concettuale per questo modulo. Modifica quella esistente.');
        }
    }

    public function edit(Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);

        $modules = $course->modules()->orderBy('sort_order')->get(['id', 'title']);
        $materials = \App\Models\Material::whereIn('module_id', $modules->pluck('id'))
            ->orderBy('sort_order')->get(['id', 'module_id', 'title', 'file_type']);

        return view('admin.courses.concept-maps.edit', [
            'course' => $course,
            'map' => $concept_map,
            'modules' => $modules,
            'materials' => $materials,
        ]);
    }

    public function update(UpdateCourseConceptMapRequest $request, Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);

        $attrs = $request->only(['title', 'description', 'visibility', 'sort_order']);
        if ($request->has('data')) {
            $attrs['data'] = $request->input('data');
        }
        $concept_map->update($attrs);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'updated_at' => $concept_map->updated_at?->toIso8601String()]);
        }

        return back()->with('success', 'Mappa concettuale aggiornata.');
    }

    public function destroy(Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);
        $concept_map->delete();

        return redirect()
            ->route('admin.courses.concept-maps.index', $course)
            ->with('success', 'Mappa concettuale eliminata.');
    }

    /**
     * Genera (o rigenera) la mappa via Claude API, salva il JSON in data,
     * aggiorna content_hash e flag ai_generated.
     */
    public function generate(Request $request, Course $course, CourseConceptMap $concept_map)
    {
        $this->ensureBelongsToCourse($concept_map, $course);

        try {
            // Se la mappa è module-level, genera dal solo modulo; altrimenti dal corso.
            $module = $concept_map->isModuleLevel() ? $concept_map->module : null;
            $graph = $this->conceptMapService->generate($course, $module);

            $concept_map->update([
                'data' => $graph,
                'ai_generated' => true,
                'ai_generated_at' => now(),
                'content_hash' => $concept_map->currentContentHash(),
            ]);

            Log::info('ConceptMap saved', [
                'concept_map_id' => $concept_map->id,
                'course_id' => $course->id,
                'nodes' => count($graph['nodes']),
                'edges' => count($graph['edges']),
                'by_admin' => session('admin_email') ?? 'unknown',
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $graph,
                    'ai_generated_at' => $concept_map->ai_generated_at?->toIso8601String(),
                ]);
            }

            return redirect()
                ->route('admin.courses.concept-maps.edit', [$course, $concept_map])
                ->with('success', 'Mappa concettuale generata con AI. Rivedila e salva.');
        } catch (Throwable $e) {
            Log::error('ConceptMap generation failed', [
                'concept_map_id' => $concept_map->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }

            return back()->with('error', 'Errore generazione mappa concettuale: ' . $e->getMessage());
        }
    }

    private function ensureBelongsToCourse(CourseConceptMap $map, Course $course): void
    {
        if ($map->course_id !== $course->id) {
            abort(404);
        }
    }
}
