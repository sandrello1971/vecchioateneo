<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Course $course)
    {
        $modules = $course->modules()->with('materials')->orderBy('sort_order')->get();
        return view('admin.courses.modules', compact('course', 'modules'));
    }

    public function create(Course $course)
    {
        return view('admin.courses.modules.create', compact('course'));
    }

    public function store(Request $request, Course $course)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable',
        ]);

        $data['is_active'] = isset($data['is_active']);
        $data['course_id'] = $course->id;

        $module = Module::create($data);

        return redirect("/admin/courses/{$course->id}/modules/{$module->id}/edit")
            ->with('success', 'Modulo creato.');
    }

    public function show(Course $course, Module $module)
    {
        return redirect("/admin/courses/{$course->id}/modules/{$module->id}/edit");
    }

    public function edit(Course $course, Module $module)
    {
        $module->load('materials');
        $moduleConceptMap = $course->conceptMaps()->where('module_id', $module->id)->first();

        return view('admin.courses.modules.edit', compact('course', 'module', 'moduleConceptMap'));
    }

    public function update(Request $request, Course $course, Module $module)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable',
            'video_file' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:2048000',
        ]);
        $data['is_active'] = isset($data['is_active']);
        unset($data['video_file']);
        $module->update($data);

        if ($request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $videoAI = app(\App\Services\VideoAIService::class);

            if ($module->video_ai_id) {
                try {
                    $videoAI->deleteVideo($module->video_ai_id);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('VideoAI delete (module) old video failed: ' . $e->getMessage());
                }
            }

            try {
                $result = $videoAI->ingestVideo(
                    $file->getPathname(),
                    $file->getClientOriginalName()
                );

                $module->update([
                    'video_ai_id' => $result['video_id'],
                    'video_filename' => $file->getClientOriginalName(),
                    'video_status' => $result['status'] ?? 'processing',
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('VideoAI upload error: ' . $e->getMessage());
                return back()->with('error', 'Upload video fallito: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Modulo aggiornato.');
    }

    public function destroy(Course $course, Module $module)
    {
        $module->delete();
        return redirect("/admin/courses/{$course->id}/modules")->with('success', 'Modulo eliminato.');
    }
}
