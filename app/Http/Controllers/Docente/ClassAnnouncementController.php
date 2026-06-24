<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\ClassAnnouncement;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Schola\ClassMessagingAccess;
use Illuminate\Http\Request;

// Annunci del docente a tutta la classe (P22, broadcast). Rispecchia
// AnnouncementController del mondo corsi, scoping per cattedra.
class ClassAnnouncementController extends Controller
{
    public function __construct(private ClassMessagingAccess $access) {}

    private function teacher(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    private function authorizeClass(SchoolClass $class, Student $teacher): void
    {
        abort_unless($this->access->teacherTeaches($teacher->id, $class), 403,
            'Non hai accesso a questa classe.');
    }

    public function index(SchoolClass $class)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);

        $announcements = ClassAnnouncement::where('school_class_id', $class->id)
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('created_at')
            ->get();

        return view('docente.annunci.index', compact('class', 'announcements'));
    }

    public function create(SchoolClass $class)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);

        return view('docente.annunci.create', compact('class'));
    }

    public function store(Request $request, SchoolClass $class)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);

        $data = $request->validate([
            'subject' => 'required|string|min:3|max:200',
            'body' => 'required|string|min:1|max:5000',
        ]);

        $announcement = ClassAnnouncement::create([
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        $recipients = $this->access->activeStudentsOf($class)->count();

        return redirect()->route('docente.classi.annunci.show', [$class, $announcement])
            ->with('success', "Annuncio pubblicato per {$recipients} studenti.");
    }

    public function show(SchoolClass $class, ClassAnnouncement $announcement)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);
        abort_unless($announcement->school_class_id === $class->id
            && $announcement->teacher_id === $teacher->id, 403);

        $announcement->load('teacher');
        $readsCount = $announcement->readsCount();
        $recipientsCount = $announcement->recipientsCount();

        return view('docente.annunci.show', compact('class', 'announcement', 'readsCount', 'recipientsCount'));
    }
}
