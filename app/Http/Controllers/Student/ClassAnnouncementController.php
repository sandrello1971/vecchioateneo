<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ResolvesScholaAccess;
use App\Models\ClassAnnouncement;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

// Annunci di classe lato STUDENTE (P22): sola lettura. Rispecchia il lato
// studente di AnnouncementController (is_read subquery, mark-as-read all'apertura).
class ClassAnnouncementController extends Controller
{
    use ResolvesScholaAccess;

    public function index(SchoolClass $class)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);

        $announcements = ClassAnnouncement::where('school_class_id', $class->id)
            ->with('teacher')
            ->addSelect([
                'is_read' => DB::table('class_announcement_reads')
                    ->selectRaw('1')
                    ->whereColumn('class_announcement_id', 'class_announcements.id')
                    ->where('student_id', $student->id)
                    ->limit(1),
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('student.classi.annunci.index', compact('class', 'announcements'));
    }

    public function show(SchoolClass $class, ClassAnnouncement $announcement)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        abort_unless($announcement->school_class_id === $class->id, 404);

        $announcement->load('teacher');
        $announcement->markReadBy($student); // ricevuta di lettura idempotente

        return view('student.classi.annunci.show', compact('class', 'announcement'));
    }
}
