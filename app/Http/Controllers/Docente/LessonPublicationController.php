<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\IngestLessonRagJob;
use App\Jobs\PurgeWithdrawnLessonPublicationJob;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

// Pubblicazione di una LEZIONE 'ready' su una o più classi del docente, e ritiro.
// Criterio (P15): classe LIBERA → proprietà (fetta 1 invariata); classe di SCUOLA
// → cattedra (TeacherClassAccess). L'ingestion RAG scope='class' è asincrona:
// feedback UX via rag_status + polling. abort_unless owner ovunque.
class LessonPublicationController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    public function store(Request $request, Lesson $lesson)
    {
        abort_unless($lesson->teacher_id === $this->teacherId(), 403);
        abort_unless($lesson->generation_status === 'ready' && !empty($lesson->content), 422,
            'Solo una lezione pronta (corpo composto) può essere pubblicata.');

        $data = $request->validate([
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'uuid',
            'students_can_generate' => 'sometimes|boolean',
        ]);

        $access = app(\App\Services\Schola\TeacherClassAccess::class);
        $candidateIds = array_values(array_unique($data['class_ids']));
        $classes = SchoolClass::whereIn('id', $candidateIds)->get();
        abort_if($classes->count() !== count($candidateIds), 403, 'Classe non valida.');

        // VINCOLO: classe di scuola → richiede cattedra; classe libera → proprietà.
        foreach ($classes as $class) {
            abort_unless($access->canTeach($this->teacherId(), $class), 403, 'Non hai cattedra in questa classe.');
        }

        $outOfCattedra = [];
        $subjectId = $lesson->topic?->subject_id;
        foreach ($classes as $class) {
            $publication = LessonPublication::updateOrCreate(
                ['lesson_id' => $lesson->id, 'school_class_id' => $class->id],
                [
                    'students_can_generate' => (bool) ($data['students_can_generate'] ?? true),
                    'published_at' => now(),
                    'rag_status' => 'pending',
                    'rag_failure_reason' => null,
                ]
            );

            IngestLessonRagJob::dispatch($publication->id)->afterResponse();

            if (!$access->subjectCoveredByCattedra($this->teacherId(), $class, $subjectId)) {
                $outOfCattedra[] = $class->name;
            }
        }

        $redirect = redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Pubblicazione avviata: indicizzazione in corso…');

        if ($outOfCattedra) {
            $redirect->with('warning', 'Attenzione: la materia della lezione non corrisponde a una tua cattedra in: '
                . implode(', ', $outOfCattedra) . '.');
        }

        return $redirect;
    }

    public function destroy(LessonPublication $publication)
    {
        $lesson = $publication->lesson;
        abort_unless($lesson && $lesson->teacher_id === $this->teacherId(), 403);

        $publicationId = $publication->id;
        $publication->delete();

        // Pulizia RAG dei chunk class (idempotente, per lesson_publication_id).
        PurgeWithdrawnLessonPublicationJob::dispatch($publicationId)->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Pubblicazione ritirata.');
    }

    public function status(Lesson $lesson)
    {
        abort_unless($lesson->teacher_id === $this->teacherId(), 403);

        $publications = $lesson->publications()->with('schoolClass')->get()->map(fn ($p) => [
            'id' => $p->id,
            'school_class_id' => $p->school_class_id,
            'class_name' => $p->schoolClass?->name,
            'rag_status' => $p->rag_status,
            'rag_failure_reason' => $p->rag_failure_reason,
        ]);

        return response()->json(['publications' => $publications]);
    }
}
