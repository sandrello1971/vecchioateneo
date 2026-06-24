<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Services\InstructorManualService;
use App\Services\InstructorManualSplitterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructorMaterialController extends Controller
{
    public function __construct(protected InstructorManualService $service) {}

    public function store(Request $request, Course $course)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'docx'        => 'required|file|extensions:docx,doc,md,markdown|max:20480',
        ]);

        try {
            $this->service->uploadAndImport(
                $request->file('docx'),
                $course,
                $data['title'],
                $data['description'] ?? null,
                null,
                $request->boolean('confirm_overwrite')
            );
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Import fallito: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('success', 'Manuale formatore caricato correttamente.' . $this->sourceFeedback());
    }

    public function update(Request $request, Course $course, Material $material)
    {
        abort_unless($material->course_id === $course->id, 404);
        abort_unless($material->is_instructor_only, 404);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'docx'        => 'nullable|file|extensions:docx,doc,md,markdown|max:20480',
        ]);

        try {
            if ($request->hasFile('docx')) {
                $this->service->uploadAndImport(
                    $request->file('docx'),
                    $course,
                    $data['title'],
                    $data['description'] ?? null,
                    $material,
                    $request->boolean('confirm_overwrite')
                );
            } else {
                $material->update([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? $material->description,
                ]);
            }
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Update fallito: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('success', 'Manuale formatore aggiornato.' . $this->sourceFeedback());
    }

    public function regenerate(Request $request, Course $course, Material $material)
    {
        abort_unless($material->course_id === $course->id, 404);
        abort_unless($material->is_instructor_only, 404);

        try {
            $this->service->regenerateHtml($material, $request->boolean('confirm_overwrite'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Rigenerazione fallita: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('success', 'HTML rigenerato dal file .docx esistente.' . $this->sourceFeedback());
    }

    /**
     * F-c — Feedback testuale sull'esito dell'estrazione del sorgente strutturato, da appendere
     * al messaggio di successo. Vuoto se l'estrazione non è stata eseguita.
     */
    private function sourceFeedback(): string
    {
        $s = $this->service->lastSourceSync;
        if (!$s) {
            return '';
        }

        return match ($s['status']) {
            'generated' => " Sorgente strutturato generato (v{$s['version']}, {$s['blocks']} blocchi) — il corso è freshness-ready."
                . (($s['invalidated'] ?? 0) > 0
                    ? " {$s['invalidated']} proposte in coda sono state invalidate dalla rigenerazione del sorgente."
                    : ''),
            'empty' => ' Sorgente non generato: heading non riconosciuti nel manuale (0 blocchi).',
            'awaiting_confirmation' => ' Sorgente non rigenerato: in attesa di conferma sovrascrittura (spunta la conferma e ricarica).',
            'failed' => ' Sorgente non generato: errore durante l\'estrazione.',
            default => '',
        };
    }

    public function destroy(Course $course, Material $material)
    {
        abort_unless($material->course_id === $course->id, 404);
        abort_unless($material->is_instructor_only, 404);

        $this->service->delete($material);

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('success', 'Manuale formatore eliminato.');
    }

    public function manageSections(Course $course, Material $material)
    {
        abort_unless($material->course_id === $course->id, 404);
        abort_unless($material->is_instructor_only, 404);

        $sections = InstructorManualSection::where('material_id', $material->id)
            ->orderBy('sort_order')->get();

        $modules = Module::where('course_id', $course->id)
            ->orderBy('sort_order')->get(['id', 'sort_order', 'title']);

        return view('admin.courses.instructor-materials.sections', [
            'course'   => $course,
            'material' => $material,
            'sections' => $sections,
            'modules'  => $modules,
        ]);
    }

    public function updateSections(Request $request, Course $course, Material $material)
    {
        abort_unless($material->course_id === $course->id, 404);
        abort_unless($material->is_instructor_only, 404);

        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*' => 'nullable|string',
        ]);

        $sections = InstructorManualSection::where('material_id', $material->id)
            ->get()->keyBy('id');

        $validModuleIds = Module::where('course_id', $course->id)
            ->pluck('id')->toArray();

        $changedCount = 0;

        DB::transaction(function () use ($data, $sections, $validModuleIds, &$changedCount) {
            foreach ($data['assignments'] as $sectionId => $newModuleId) {
                $section = $sections->get($sectionId);
                if (!$section) continue;

                $newModuleId = empty($newModuleId) ? null : $newModuleId;

                if ($newModuleId !== null && !in_array($newModuleId, $validModuleIds)) {
                    continue;
                }

                $oldModuleId = $section->module_id;

                if ($oldModuleId !== $newModuleId) {
                    $section->update([
                        'module_id' => $newModuleId,
                        'module_assigned_manually' => true,
                    ]);
                    $changedCount++;
                }
            }
        });

        return redirect()
            ->route('admin.courses.instructor-materials.sections', [$course->id, $material->id])
            ->with('success', "Salvate $changedCount modifiche al mapping delle sezioni.");
    }

    public function resetSections(Course $course, Material $material, InstructorManualSplitterService $splitter)
    {
        abort_unless($material->course_id === $course->id, 404);
        abort_unless($material->is_instructor_only, 404);

        InstructorManualSection::where('material_id', $material->id)
            ->update(['module_assigned_manually' => false]);

        $count = $splitter->split($material);

        return redirect()
            ->route('admin.courses.instructor-materials.sections', [$course->id, $material->id])
            ->with('success', "Reset completato: $count sezioni ri-mappate automaticamente.");
    }
}
