<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\InstructorNote;
use App\Models\Student;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $query = InstructorNote::with(['course', 'module', 'section', 'instructor', 'images']);

        if ($q = $request->q) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ILIKE', "%$q%")
                  ->orWhere('body_markdown', 'ILIKE', "%$q%");
            });
        }
        if ($request->kind) $query->where('kind', $request->kind);
        if ($request->course_id) $query->where('course_id', $request->course_id);
        if ($request->module_id) $query->where('module_id', $request->module_id);
        if ($request->tag) $query->whereJsonContains('tags', $request->tag);
        if ($request->instructor_id) $query->where('instructor_id', $request->instructor_id);
        if ($request->trashed) $query->onlyTrashed();

        $notes = $query->latest()->paginate(20);
        $courses = Course::orderBy('name')->get(['id', 'name', 'slug']);

        $allTags = [];
        foreach (InstructorNote::whereNotNull('tags')->get(['tags']) as $n) {
            foreach ($n->tags ?? [] as $t) $allTags[$t] = true;
        }
        ksort($allTags);

        $instructors = Student::where(fn ($q) => $q->where('role', 'instructor')->orWhere('is_instructor', true))
            ->whereHas('instructorNotes')->orderBy('email')->get(['id', 'email']);

        return view('admin.knowledge_base.index', [
            'notes' => $notes,
            'courses' => $courses,
            'allTags' => array_keys($allTags),
            'instructors' => $instructors,
            'kinds' => InstructorNote::KINDS,
            'filters' => $request->only(['q', 'kind', 'course_id', 'module_id', 'tag', 'instructor_id', 'trashed']),
        ]);
    }
}
