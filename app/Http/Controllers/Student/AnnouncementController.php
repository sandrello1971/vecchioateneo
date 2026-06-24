<?php

namespace App\Http\Controllers\Student;

use App\Events\AnnouncementSent;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    private function currentUser(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    /**
     * Lista annunci visibili all'utente:
     *  - Come studente: annunci dei corsi a cui e' iscritto attivo
     *  - Come formatore: annunci che lui stesso ha pubblicato (i suoi corsi)
     *
     * Mostra entrambi i lati (un utente puo' essere studente di un corso e
     * formatore di un altro).
     */
    public function index()
    {
        $user = $this->currentUser();

        $enrolledCourseIds = DB::table('student_course')
            ->where('student_id', $user->id)
            ->where('is_active', true)
            ->pluck('course_id');

        $teachingCourseIds = DB::table('course_instructor')
            ->where('instructor_id', $user->id)
            ->pluck('course_id');

        $visibleCourseIds = $enrolledCourseIds->merge($teachingCourseIds)->unique()->all();

        // addSelect subquery: is_read = true se esiste una row in announcement_reads
        // per (annuncio corrente, utente loggato). Single query, niente N+1.
        $announcements = Announcement::query()
            ->whereIn('course_id', $visibleCourseIds)
            ->with(['course', 'instructor'])
            ->addSelect([
                'is_read' => DB::table('announcement_reads')
                    ->selectRaw('1')
                    ->whereColumn('announcement_id', 'announcements.id')
                    ->where('student_id', $user->id)
                    ->limit(1),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('student.announcements.index', compact('announcements'));
    }

    /**
     * Form per nuovo annuncio. Disponibile solo a chi insegna almeno 1 corso.
     */
    public function create()
    {
        $user = $this->currentUser();

        $teachingCourses = Course::query()
            ->whereIn('id', DB::table('course_instructor')
                ->where('instructor_id', $user->id)
                ->pluck('course_id'))
            ->orderBy('name')
            ->get();

        if ($teachingCourses->isEmpty()) {
            abort(403, 'Solo i formatori possono pubblicare annunci.');
        }

        return view('student.announcements.create', compact('teachingCourses'));
    }

    public function store(Request $request)
    {
        $user = $this->currentUser();

        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
            'subject'   => 'required|string|min:3|max:200',
            'body'      => 'required|string|min:1|max:5000',
        ]);

        // Verifica che l'utente sia davvero formatore di quel corso
        $teaches = DB::table('course_instructor')
            ->where('instructor_id', $user->id)
            ->where('course_id', $data['course_id'])
            ->exists();
        if (!$teaches) {
            abort(403, 'Non sei formatore di questo corso.');
        }

        $announcement = Announcement::create([
            'course_id'     => $data['course_id'],
            'instructor_id' => $user->id,
            'subject'       => $data['subject'],
            'body'          => $data['body'],
        ]);

        // Risolvi i destinatari: studenti iscritti attivi al corso
        $recipientIds = DB::table('student_course')
            ->where('course_id', $data['course_id'])
            ->where('is_active', true)
            ->pluck('student_id')
            ->all();

        // Broadcast live a ogni studente (badge sidebar bumpa). Se ci sono
        // destinatari ma il broadcast fallisce, log e prosegui — l'annuncio
        // resta accessibile via /learn/annunci (consistency over availability).
        if (!empty($recipientIds)) {
            try {
                $announcement->load(['course', 'instructor']);
                broadcast(new AnnouncementSent($announcement, $recipientIds))->toOthers();
            } catch (\Throwable $e) {
                Log::error('Broadcast AnnouncementSent fallito', [
                    'announcement_id' => $announcement->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Annuncio pubblicato', [
            'announcement_id' => $announcement->id,
            'course_id' => $data['course_id'],
            'instructor_id' => $user->id,
            'recipients_count' => count($recipientIds),
        ]);

        return redirect()->route('student.announcements.show', $announcement)
            ->with('success', "Annuncio pubblicato. Notificati " . count($recipientIds) . " discenti.");
    }

    public function show(Announcement $announcement)
    {
        $user = $this->currentUser();

        // Visibile se: studente iscritto attivo OR formatore del corso
        $enrolled = DB::table('student_course')
            ->where('student_id', $user->id)
            ->where('course_id', $announcement->course_id)
            ->where('is_active', true)
            ->exists();
        $teaches = DB::table('course_instructor')
            ->where('instructor_id', $user->id)
            ->where('course_id', $announcement->course_id)
            ->exists();

        if (!$enrolled && !$teaches) {
            abort(403);
        }

        $announcement->load(['course', 'instructor']);

        // Mark-as-read SOLO se l'utente e' uno studente destinatario, non se
        // e' il formatore che ha pubblicato (le sue letture non hanno senso
        // nelle statistiche "letto da X discenti").
        if ($enrolled && $user->id !== $announcement->instructor_id) {
            $announcement->markReadBy($user);
        }

        return view('student.announcements.show', compact('announcement'));
    }
}
