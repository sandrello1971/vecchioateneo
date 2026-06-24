<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ResolvesScholaAccess;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentArtifactView;

class StudentClassController extends Controller
{
    use ResolvesScholaAccess;

    public function index()
    {
        $student = Student::findOrFail(session('student_id'));

        $classes = $student->schoolClasses()
            ->with('subject', 'teacher')
            ->wherePivot('status', '!=', 'removed')
            ->orderBy('name')
            ->get();

        return view('student.classi.index', compact('classes'));
    }

    /**
     * Feed cronologico delle pubblicazioni della classe + accesso alla chat.
     * Solo iscrizione ATTIVA.
     */
    public function show(SchoolClass $class)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);

        // Pubblicazioni della classe (artefatto pronto), più recenti prima.
        $publications = $class->publications()
            ->whereHas('artifact', fn ($q) => $q->where('status', 'ready'))
            ->with('artifact:id,type,title,teaching_document_id')
            ->orderByDesc('published_at')
            ->get();

        // Stato "visto/nuovo" per lo studente (una query, niente N+1).
        $views = StudentArtifactView::where('student_id', $student->id)
            ->whereIn('artifact_publication_id', $publications->pluck('id'))
            ->pluck('last_viewed_at', 'artifact_publication_id');

        // Lezioni pubblicate (P20b): organizzate per Argomento → Lezione (non lista
        // piatta). Solo lezioni pronte pubblicate su QUESTA classe.
        $lessons = \App\Models\Lesson::whereHas('publications', fn ($q) => $q->where('school_class_id', $class->id))
            ->where('generation_status', 'ready')
            ->with('topic')
            ->get();
        $lessonsByTopic = $lessons
            ->sortBy(fn ($l) => [$l->topic?->position ?? 0, $l->position])
            ->groupBy(fn ($l) => $l->topic?->name ?? 'Senza argomento');

        return view('student.classi.show', compact('class', 'student', 'publications', 'views', 'lessonsByTopic'));
    }
}
