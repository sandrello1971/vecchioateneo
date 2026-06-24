<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Services\VideoAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    public function create(Course $course, Module $module)
    {
        return view('admin.courses.materials.create', compact('course', 'module'));
    }

    public function store(Request $request, Course $course, Module $module)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:file,video,url,canvas',
            'file' => 'required_if:type,file|file|max:204800|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt',
            'video_file' => 'required_if:type,video|file|max:2048000|mimes:mp4,mov,avi,webm',
            'url' => 'required_if:type,url|nullable|url|max:500',
            'canvas_file' => 'required_if:type,canvas|file|max:5120|extensions:html,htm',
        ]);

        $material = new Material();
        $material->module_id = $module->id;
        $material->title = $request->title;
        $material->description = $request->description;
        $material->sort_order = (Material::where('module_id', $module->id)->max('sort_order') ?? 0) + 1;
        $material->is_downloadable = true;

        if ($request->type === 'file' && $request->hasFile('file')) {
            $file = $request->file('file');
            // Disk 'local' = storage/app/private. I materiali corso non sono pubblici:
            // l'accesso passa esclusivamente per Student\MaterialController con verifica iscrizione.
            $path = $file->store("materials/{$course->slug}", 'local');
            $material->file_path = $path;
            $material->file_type = $file->getClientOriginalExtension();
            $material->file_size = $file->getSize();
        } elseif ($request->type === 'video' && $request->hasFile('video_file')) {
            $file = $request->file('video_file');

            try {
                $videoAI = app(VideoAIService::class);
                $result = $videoAI->ingestVideo(
                    $file->getPathname(),
                    $file->getClientOriginalName()
                );
                $material->file_type = 'video';
                $material->video_ai_id = $result['video_id'];
                $material->file_path = $file->getClientOriginalName();
                $module->update([
                    'video_ai_id' => $result['video_id'],
                    'video_filename' => $file->getClientOriginalName(),
                    'video_status' => $result['status'] ?? 'processing',
                ]);
            } catch (\Exception $e) {
                Log::error('VideoAI upload error: ' . $e->getMessage());
                return back()->withErrors(['video_file' => 'Errore caricamento video: ' . $e->getMessage()]);
            }
        } elseif ($request->type === 'url') {
            $material->external_url = $request->url;
            $material->file_type = 'url';
        } elseif ($request->type === 'canvas' && $request->hasFile('canvas_file')) {
            $file = $request->file('canvas_file');
            // Stessa policy dei PDF: disk 'local' (= storage/app/private), accesso solo
            // tramite Student\MaterialController::canvas() con verifica iscrizione.
            $path = $file->store("materials/{$course->slug}", 'local');
            $material->file_path = $path;
            $material->file_type = 'canvas';
            $material->file_size = $file->getSize();
            $material->is_downloadable = false;
        }

        $material->save();

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('success', 'Materiale aggiunto!');
    }

    public function edit(Course $course, Module $module, Material $material)
    {
        return view('admin.courses.materials.edit', compact('course', 'module', 'material'));
    }

    public function update(Request $request, Course $course, Module $module, Material $material)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $material->update([
            'title' => $request->title,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('success', 'Materiale aggiornato!');
    }

    public function destroy(Course $course, Module $module, Material $material)
    {
        if ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }

        if ($material->video_ai_id) {
            try {
                app(VideoAIService::class)->deleteVideo($material->video_ai_id);
                $module->update([
                    'video_ai_id' => null,
                    'video_filename' => null,
                    'video_status' => 'none',
                ]);
            } catch (\Exception $e) {
                Log::error('VideoAI delete error: ' . $e->getMessage());
            }
        }

        $material->delete();

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('success', 'Materiale eliminato.');
    }

    public function index(Course $course, Module $module)
    {
        return redirect()->route('admin.courses.modules.edit', [$course, $module]);
    }

    public function show(Course $course, Module $module, Material $material)
    {
        return redirect()->route('admin.courses.modules.edit', [$course, $module]);
    }
}
