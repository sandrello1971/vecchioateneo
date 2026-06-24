<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Impostazioni che il formatore puo' gestire per i corsi in cui insegna.
 * Per ora un'unica setting: accepts_dm (per corso). Punto di estensione per
 * future setting (es. response_time_hint, auto_reply_message, ecc.).
 */
class InstructorSettingsController extends Controller
{
    private function currentUser(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    public function index()
    {
        $user = $this->currentUser();

        // Solo formatori effettivi (= presenti nella pivot course_instructor)
        $teachingRows = DB::table('course_instructor')
            ->join('courses', 'course_instructor.course_id', '=', 'courses.id')
            ->where('course_instructor.instructor_id', $user->id)
            ->select(
                'courses.id as course_id',
                'courses.name as course_name',
                'courses.slug as course_slug',
                'course_instructor.accepts_dm'
            )
            ->orderBy('courses.name')
            ->get();

        if ($teachingRows->isEmpty()) {
            abort(403, 'Questa pagina e\' riservata ai formatori dei corsi.');
        }

        return view('student.instructor_settings.index', compact('teachingRows'));
    }

    public function updateDm(Request $request)
    {
        $user = $this->currentUser();

        $data = $request->validate([
            'accepts_dm'   => 'array',
            'accepts_dm.*' => 'in:0,1',
        ]);

        // Carico tutti i corsi insegnati dall'utente per validare le chiavi
        $teachingCourseIds = DB::table('course_instructor')
            ->where('instructor_id', $user->id)
            ->pluck('course_id')
            ->all();

        if (empty($teachingCourseIds)) {
            abort(403);
        }

        $submitted = $data['accepts_dm'] ?? [];

        // Update massivo: per ogni corso insegnato, accepts_dm = true se
        // checkbox spuntata, false altrimenti. Checkbox NON inviata = unchecked.
        DB::transaction(function () use ($user, $teachingCourseIds, $submitted) {
            foreach ($teachingCourseIds as $courseId) {
                $newValue = (bool) (($submitted[$courseId] ?? '0') === '1');
                DB::table('course_instructor')
                    ->where('instructor_id', $user->id)
                    ->where('course_id', $courseId)
                    ->update(['accepts_dm' => $newValue]);
            }
        });

        Log::info('Formatore aggiornato accepts_dm', [
            'instructor_id' => $user->id,
            'submitted_keys' => array_keys($submitted),
        ]);

        return redirect()->route('student.instructor_settings.index')
            ->with('success', 'Preferenze aggiornate.');
    }
}
