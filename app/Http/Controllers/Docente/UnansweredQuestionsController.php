<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\UnansweredQuestion;
use App\Services\Schola\ClassSignalsService;
use Illuminate\Http\Request;

// Vista "Domande scoperte": cluster di domande fuori KB (raggruppate per
// similarità da ClassSignalsService), con azioni addressed/dismissed per
// singola e per cluster. Lo studente NON è anonimo (§8.1).
class UnansweredQuestionsController extends Controller
{
    public function __construct(private ClassSignalsService $signals) {}

    private function authorizeClass(SchoolClass $class): void
    {
        // Proprietà (classe libera) o cattedra (classe di scuola) — P15.
        abort_unless(app(\App\Services\Schola\TeacherClassAccess::class)
            ->canTeach(session('student_id'), $class), 403);
    }

    public function index(SchoolClass $class)
    {
        $this->authorizeClass($class);

        return view('docente.classi.domande', [
            'class' => $class,
            'clusters' => $this->signals->openQuestionClusters($class),
            'addressed' => UnansweredQuestion::where('school_class_id', $class->id)->where('status', 'addressed')->count(),
            'dismissed' => UnansweredQuestion::where('school_class_id', $class->id)->where('status', 'dismissed')->count(),
        ]);
    }

    /** Azione su una singola domanda (SPEC §3.1). */
    public function update(Request $request, UnansweredQuestion $question)
    {
        $class = $question->schoolClass;
        abort_unless($class && app(\App\Services\Schola\TeacherClassAccess::class)
            ->canTeach(session('student_id'), $class), 403);

        $data = $request->validate(['status' => 'required|in:open,addressed,dismissed']);
        $question->update(['status' => $data['status']]);

        return back()->with('success', 'Domanda aggiornata.');
    }

    /** Azione su un intero cluster (lista di domande). */
    public function updateCluster(Request $request, SchoolClass $class)
    {
        $this->authorizeClass($class);

        $data = $request->validate([
            'question_ids' => 'required|array|min:1',
            'question_ids.*' => 'uuid',
            'status' => 'required|in:open,addressed,dismissed',
        ]);

        UnansweredQuestion::where('school_class_id', $class->id)
            ->whereIn('id', $data['question_ids'])
            ->update(['status' => $data['status']]);

        return back()->with('success', 'Cluster aggiornato.');
    }
}
