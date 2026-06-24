<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\CourseConceptMap;
use App\Models\Student;
use App\Models\StudentConceptMap;
use Illuminate\Http\Request;

class ConceptMapController extends Controller
{
    use DeterminesTeachingMode;

    public function index(Course $course)
    {
        $student = $this->checkAccess($course);

        $maps = $course->conceptMaps()
            ->published()
            ->ordered()
            ->get();

        $forkedMapIds = StudentConceptMap::where('student_id', $student->id)
            ->whereIn('course_concept_map_id', $maps->pluck('id'))
            ->pluck('course_concept_map_id')
            ->all();

        return view('student.concept-maps.index', [
            'course' => $course,
            'maps' => $maps,
            'forkedMapIds' => $forkedMapIds,
        ]);
    }

    public function show(Course $course, CourseConceptMap $concept_map)
    {
        $student = $this->checkAccess($course);
        $this->ensureBelongsAndPublished($concept_map, $course);

        $hasFork = StudentConceptMap::where('student_id', $student->id)
            ->where('course_concept_map_id', $concept_map->id)
            ->exists();

        return view('student.concept-maps.show', [
            'course' => $course,
            'map' => $concept_map,
            'hasFork' => $hasFork,
        ]);
    }

    public function fork(Request $request, Course $course, CourseConceptMap $concept_map)
    {
        $student = $this->checkAccess($course);
        $this->ensureBelongsAndPublished($concept_map, $course);

        StudentConceptMap::firstOrCreate(
            [
                'student_id' => $student->id,
                'course_concept_map_id' => $concept_map->id,
            ],
            [
                'data' => $concept_map->data,
                'forked_at' => now(),
            ],
        );

        return redirect()->route('student.course.concept-map.my', [$course->slug, $concept_map]);
    }

    public function editFork(Course $course, CourseConceptMap $concept_map)
    {
        $student = $this->checkAccess($course);
        $this->ensureBelongsAndPublished($concept_map, $course);

        $fork = StudentConceptMap::where('student_id', $student->id)
            ->where('course_concept_map_id', $concept_map->id)
            ->first();

        if (! $fork) {
            return redirect()->route('student.course.concept-map.show', [$course->slug, $concept_map]);
        }

        return view('student.concept-maps.edit-fork', [
            'course' => $course,
            'map' => $concept_map,
            'fork' => $fork,
        ]);
    }

    public function saveFork(Request $request, Course $course, CourseConceptMap $concept_map)
    {
        $student = $this->checkAccess($course);
        $this->ensureBelongsAndPublished($concept_map, $course);

        $data = $request->validate([
            'data' => 'required|array',
            'data.nodes' => 'required|array',
            'data.nodes.*.id' => 'required|string|max:64',
            'data.nodes.*.label' => 'required|string|max:120',
            'data.edges' => 'required|array',
            'data.edges.*.id' => 'required|string|max:64',
            'data.edges.*.from' => 'required|string|max:64',
            'data.edges.*.to' => 'required|string|max:64',
            'data.edges.*.label' => 'required|string|max:60',
        ]);

        $fork = StudentConceptMap::where('student_id', $student->id)
            ->where('course_concept_map_id', $concept_map->id)
            ->firstOrFail();

        $fork->update([
            'data' => $data['data'],
            'last_edited_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'last_edited_at' => $fork->last_edited_at?->toIso8601String()]);
        }

        return back()->with('success', 'Mappa personalizzata salvata.');
    }

    private function ensureBelongsAndPublished(CourseConceptMap $map, Course $course): void
    {
        if ($map->course_id !== $course->id) {
            abort(404);
        }
        if (! $map->isPublished()) {
            abort(404);
        }
    }

    /**
     * Verifica enrollment / teaching / auto_enroll. Pattern replicato da
     * Student\CourseController::checkAccess() (DRY: helper estratto a posteriori).
     */
    private function checkAccess(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));

        if ($student->auto_enroll_all_courses && $course->is_active) {
            return $student;
        }

        $enrolled = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();

        if ($enrolled) {
            return $student;
        }

        if ($this->teaches($student, $course) && $course->is_active) {
            return $student;
        }

        abort(403, 'Non sei iscritto a questo corso.');
    }
}
