<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\InstructorManualSection;
use App\Models\InstructorNote;
use App\Models\Module;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstructorNoteController extends Controller
{
    private function instructor(): Student
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $s = Student::findOrFail($studentId);
        abort_unless($s->isInstructor(), 403, 'Riservato ai formatori');
        return $s;
    }

    public function index(Request $request)
    {
        $instructor = $this->instructor();

        $query = InstructorNote::visibleTo($instructor->id)
            ->with(['course', 'module', 'section', 'instructor', 'images']);

        $this->applyFilters($query, $request, $instructor->id);

        $notes = $query->latest()->paginate(20);
        $courses = Course::orderBy('name')->get(['id', 'name', 'slug']);
        $allTags = $this->collectTags($instructor->id);

        return view('student.knowledge_base.index', [
            'notes'    => $notes,
            'courses'  => $courses,
            'allTags'  => $allTags,
            'kinds'    => InstructorNote::KINDS,
            'filters'  => $request->only(['q', 'kind', 'course_id', 'module_id', 'tag', 'mine_only', 'trashed']),
        ]);
    }

    public function create(Request $request)
    {
        $this->instructor();

        $course = $request->course_id ? Course::find($request->course_id) : null;
        $modules = $course
            ? Module::where('course_id', $course->id)->orderBy('sort_order')->get()
            : collect();
        $sections = $course
            ? InstructorManualSection::where('course_id', $course->id)->orderBy('sort_order')->get()
            : collect();
        $courses = Course::orderBy('name')->get(['id', 'name', 'slug']);

        return view('student.knowledge_base.form', [
            'note' => new InstructorNote([
                'course_id' => $request->course_id,
                'module_id' => $request->module_id,
                'instructor_manual_section_id' => $request->section_id,
                'kind' => $request->kind ?? 'promemoria',
            ]),
            'courses' => $courses,
            'modules' => $modules,
            'sections' => $sections,
            'kinds' => InstructorNote::KINDS,
            'returnUrl' => $request->return_url,
        ]);
    }

    public function store(Request $request)
    {
        $instructor = $this->instructor();
        $data = $this->validateNote($request);

        InstructorNote::create([
            'instructor_id' => $instructor->id,
            ...$data,
        ]);

        return redirect($request->return_url ?: route('student.knowledge_base.index'))
            ->with('success', 'Nota creata.');
    }

    public function edit(Request $request, InstructorNote $note)
    {
        $instructor = $this->instructor();
        abort_unless($note->instructor_id === $instructor->id, 403, 'Solo l\'autore può modificare');

        $courses = Course::orderBy('name')->get(['id', 'name', 'slug']);
        $modules = Module::where('course_id', $note->course_id)->orderBy('sort_order')->get();
        $sections = InstructorManualSection::where('course_id', $note->course_id)
            ->orderBy('sort_order')->get();

        return view('student.knowledge_base.form', [
            'note' => $note,
            'courses' => $courses,
            'modules' => $modules,
            'sections' => $sections,
            'kinds' => InstructorNote::KINDS,
            'returnUrl' => $request->return_url,
        ]);
    }

    public function update(Request $request, InstructorNote $note)
    {
        $instructor = $this->instructor();
        abort_unless($note->instructor_id === $instructor->id, 403);

        $data = $this->validateNote($request);
        $note->update($data);

        return redirect($request->return_url ?: route('student.knowledge_base.index'))
            ->with('success', 'Nota aggiornata.');
    }

    public function destroy(Request $request, InstructorNote $note)
    {
        $instructor = $this->instructor();
        abort_unless($note->instructor_id === $instructor->id, 403);

        $note->delete();

        return redirect($request->return_url ?: route('student.knowledge_base.index'))
            ->with('success', 'Nota cancellata (recuperabile dal cestino).');
    }

    public function restore(string $noteId)
    {
        $instructor = $this->instructor();
        $note = InstructorNote::onlyTrashed()
            ->where('instructor_id', $instructor->id)
            ->findOrFail($noteId);
        $note->restore();

        return back()->with('success', 'Nota ripristinata.');
    }

    public function uploadImage(Request $request)
    {
        $this->instructor();

        $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg,webp,gif|max:5120',
        ]);

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "instructor-notes/{$filename}";

        Storage::disk('public')->putFileAs('instructor-notes', $file, $filename);

        $url = Storage::disk('public')->url($path);

        return response()->json(['url' => $url, 'markdown' => "![]({$url})"]);
    }

    private function applyFilters($query, Request $request, string $instructorId): void
    {
        if ($q = $request->q) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ILIKE', "%$q%")
                  ->orWhere('body_markdown', 'ILIKE', "%$q%");
            });
        }

        if ($request->kind) {
            $query->where('kind', $request->kind);
        }

        if ($request->course_id) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->module_id) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->tag) {
            $query->whereJsonContains('tags', $request->tag);
        }

        if ($request->mine_only) {
            $query->where('instructor_id', $instructorId);
        }

        if ($request->trashed) {
            $query->onlyTrashed()->where('instructor_id', $instructorId);
        }
    }

    private function validateNote(Request $request): array
    {
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
            'module_id' => 'nullable|uuid|exists:modules,id',
            'instructor_manual_section_id' => 'nullable|uuid|exists:instructor_manual_sections,id',
            'kind' => 'required|in:' . implode(',', array_keys(InstructorNote::KINDS)),
            'title' => 'required|string|max:200',
            'body_markdown' => 'required|string|max:10000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:30',
            'is_shared' => 'nullable|boolean',
        ]);

        if (!empty($data['tags'])) {
            $data['tags'] = array_values(array_unique(array_map(fn($t) =>
                strtolower(trim($t)), $data['tags']
            )));
            $data['tags'] = array_values(array_filter($data['tags'], fn($t) => mb_strlen($t) > 0));
        }

        $data['is_shared'] = (bool) ($data['is_shared'] ?? false);

        return $data;
    }

    private function collectTags(string $instructorId): array
    {
        $allNotes = InstructorNote::visibleTo($instructorId)
            ->whereNotNull('tags')->get(['tags']);

        $tags = [];
        foreach ($allNotes as $n) {
            foreach ($n->tags ?? [] as $t) $tags[$t] = true;
        }
        ksort($tags);
        return array_keys($tags);
    }

    public function tagSuggest(Request $request)
    {
        $instructor = $this->instructor();
        $q = strtolower(trim($request->q ?? ''));
        $allTags = $this->collectTags($instructor->id);
        $matching = $q
            ? array_filter($allTags, fn($t) => str_contains($t, $q))
            : $allTags;
        return response()->json(array_slice(array_values($matching), 0, 10));
    }

    public function modulesByCourse(string $courseId)
    {
        $this->instructor();
        return response()->json(
            Module::where('course_id', $courseId)
                ->orderBy('sort_order')
                ->get(['id', 'sort_order', 'title'])
                ->map(fn($m) => ['id' => $m->id, 'label' => "[{$m->sort_order}] {$m->title}"])
        );
    }

    public function sectionsByCourse(string $courseId)
    {
        $this->instructor();
        return response()->json(
            InstructorManualSection::where('course_id', $courseId)
                ->orderBy('sort_order')
                ->get(['id', 'title'])
                ->map(fn($s) => ['id' => $s->id, 'label' => $s->title])
        );
    }
}
