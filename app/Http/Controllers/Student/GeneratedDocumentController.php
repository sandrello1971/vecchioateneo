<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Module;
use App\Models\ModuleDocument;
use App\Models\Student;
use App\Services\CourseDocumentService;
use App\Services\ModuleDocumentService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * P29 Fase 3 — download lato STUDENTE/INSTRUCTOR del PDF generato (documento di
 * modulo e dispensa di corso). Il documento è un ModuleDocument/CourseDocument
 * (non un material): generazione ON-ACCESS sincrona se manca/stale (lock anti-race
 * nei service). Accesso = stesso gate del corso (CourseController::checkAccess:
 * auto_enroll OR iscritto attivo OR teaches), demo escluso. File da storage
 * PRIVATO, mai URL diretto.
 */
class GeneratedDocumentController extends Controller
{
    use DeterminesTeachingMode;

    public function __construct(
        private ModuleDocumentService $moduleService,
        private CourseDocumentService $courseService,
    ) {}

    public function module(Course $course, Module $module)
    {
        $this->authorizeCourseAccess($course);
        abort_unless($module->course_id === $course->id, 404);
        // Niente contenuto → nessun documento (coerente con la vista che non lo offre).
        abort_unless(trim(strip_tags((string) $module->content)) !== '', 404);

        $document = ModuleDocument::firstOrCreate(['module_id' => $module->id], ['status' => 'pending']);
        $document = $this->resolveOnAccess(
            fn () => $this->moduleService->ensureReadyAndFresh($document),
            $document
        );

        return $this->streamPdf($document, Str::slug($module->title));
    }

    public function course(Course $course)
    {
        $this->authorizeCourseAccess($course);
        abort_unless($this->courseHasContent($course), 404);

        $document = CourseDocument::firstOrCreate(['course_id' => $course->id], ['status' => 'pending']);
        $document = $this->resolveOnAccess(
            fn () => $this->courseService->ensureReadyAndFresh($document),
            $document
        );

        return $this->streamPdf($document, Str::slug($course->name));
    }

    /**
     * Gate identico a CourseController::checkAccess (auto_enroll OR iscritto
     * attivo OR teaches), più blocco demo. Il caso instructor passa via teaches()
     * (DeterminesTeachingMode), la stessa logica di InstructorMaterialController.
     */
    private function authorizeCourseAccess(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));

        // Demo: documento lucchettato, mai servito (difesa in profondità oltre al middleware).
        abort_if($student->is_demo, 403, 'Funzione non disponibile in modalità demo');

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

    /** True se almeno un modulo del corso ha contenuto reale (corpo della dispensa). */
    private function courseHasContent(Course $course): bool
    {
        return $course->modules()
            ->get(['content'])
            ->contains(fn ($m) => trim(strip_tags((string) $m->content)) !== '');
    }

    /**
     * Esegue la generazione on-access; se un altro accesso tiene il lock troppo a
     * lungo, serve il file esistente (anche stale) come fallback, altrimenti 503.
     *
     * @template T of ModuleDocument|CourseDocument
     * @param  callable():T  $ensure
     * @param  T  $document
     * @return T
     */
    private function resolveOnAccess(callable $ensure, $document)
    {
        try {
            return $ensure();
        } catch (LockTimeoutException $e) {
            $document->refresh();
            if ($document->status === 'ready' && $document->file_path
                && Storage::disk('local')->exists($document->file_path)) {
                return $document; // un altro accesso sta rigenerando: servo l'ultima versione
            }
            abort(503, 'Documento in preparazione, riprova tra qualche secondo.');
        }
    }

    /** Scarica il PDF dallo storage PRIVATO (mai URL diretto). */
    private function streamPdf(ModuleDocument|CourseDocument $document, string $fallbackSlug)
    {
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        $filename = $document->generation_meta['filename'] ?? ($fallbackSlug . '.pdf');

        return response()->download(Storage::disk('local')->path($document->file_path), $filename);
    }
}
