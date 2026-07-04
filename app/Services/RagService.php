<?php

namespace App\Services;

use App\Jobs\EmbedDocumentChunksJob;
use App\Models\Course;
use App\Models\DocumentRag;
use App\Models\Module;
use App\Models\Student;
use App\Support\PgVector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RagService
{
    public function __construct(private EmbeddingService $embeddings) {}

    public function indexDocument(
        string $text,
        string $title,
        ?string $courseId,
        ?string $moduleId,
        ?string $filePath,
        bool $isInstructorOnly = false
    ): void {
        $chunks = $this->chunkText($text, 1000, 200);

        $created = [];
        foreach ($chunks as $index => $chunk) {
            $created[] = DocumentRag::create([
                'title' => $title,
                'content' => $chunk,
                'course_id' => $courseId,
                'module_id' => $moduleId,
                'file_path' => $filePath,
                'chunk_index' => $index,
                'is_instructor_only' => $isInstructorOnly,
                'metadata' => [
                    'chunks_total' => count($chunks),
                    'source_title' => $title,
                ],
            ]);
        }

        // Embed-on-create: i nuovi chunk vengono vettorizzati subito (best
        // effort). Se videoai è giù nascono senza embedding e una coda di
        // recupero li completa: l'ingestion NON si blocca mai.
        $this->embedNewChunks(collect($created));
    }

    /**
     * Vettorizza i chunk appena creati. Non solleva mai: in caso di errore
     * accoda EmbedDocumentChunksJob (recupero asincrono) e prosegue.
     */
    private function embedNewChunks(\Illuminate\Support\Collection $rows): void
    {
        if ($rows->isEmpty() || !$this->embeddingColumnExists()) {
            return;
        }

        try {
            $vectors = $this->embeddings->embed($rows->map(fn ($r) => (string) $r->content)->all());

            DB::transaction(function () use ($rows, $vectors) {
                foreach ($rows->values() as $i => $row) {
                    DB::update(
                        'UPDATE documents_rag SET embedding = ?::vector WHERE id = ?',
                        [PgVector::toLiteral($vectors[$i]), $row->id]
                    );
                }
            });
        } catch (Throwable $e) {
            Log::warning('[rag] embed-on-create fallito, accodo recupero', [
                'count' => $rows->count(),
                'error' => $e->getMessage(),
            ]);
            EmbedDocumentChunksJob::dispatch($rows->pluck('id')->all());
        }
    }

    public function search(string $query, ?string $courseId = null, int $limit = 5)
    {
        return $this->searchInCourses(
            $query,
            $courseId ? [$courseId] : null,
            $limit,
            true,   // includePlatform
            false   // includeInstructorOnly — esclude di default
        );
    }

    public function searchInCourses(
        string $query,
        ?array $courseIds = null,
        int $limit = 5,
        bool $includePlatform = true,
        bool $includeInstructorOnly = false
    ) {
        $scope = function (Builder $q) use ($courseIds, $includePlatform, $includeInstructorOnly) {
            // Separazione dei mondi: il mondo corsi vede SOLO platform/instructor_only.
            // I chunk Schola (class, teacher_private) hanno course_id NULL e
            // altrimenti rientrerebbero nel bucket "platform" (orWhereNull).
            $q->whereIn('scope', ['platform', 'instructor_only']);
            if (!$includeInstructorOnly) {
                $q->where('is_instructor_only', false);
            }
            if (is_array($courseIds)) {
                $q->where(function ($w) use ($courseIds, $includePlatform) {
                    if (!empty($courseIds)) $w->whereIn('course_id', $courseIds);
                    if ($includePlatform) $w->orWhereNull('course_id');
                });
            }
        };

        return $this->runRetrieval($query, $scope, $limit, $this->vectorEnabledCorsi());
    }

    /**
     * Ricerca scoping-aware: i documenti instructor-only sono ammessi
     * SOLO per i corsi insegnati ($instructorScopedCourseIds), mai
     * globalmente. I documenti studente (non instructor-only) sono
     * limitati ai $courseIds navigabili, più i doc di piattaforma
     * (course_id IS NULL) — decisione §8.3: inclusi sempre.
     */
    public function searchScoped(
        string $query,
        array $courseIds,
        array $instructorScopedCourseIds = [],
        int $limit = 5
    ) {
        $scope = function (Builder $q) use ($courseIds, $instructorScopedCourseIds) {
            // Separazione dei mondi: mai chunk Schola (class/teacher_private) qui.
            $q->whereIn('scope', ['platform', 'instructor_only']);
            $q->where(function ($w) use ($courseIds, $instructorScopedCourseIds) {
                $w->where(function ($s) use ($courseIds) {
                    $s->where('is_instructor_only', false)
                      ->where(function ($c) use ($courseIds) {
                          if (!empty($courseIds)) {
                              $c->whereIn('course_id', $courseIds);
                          }
                          $c->orWhereNull('course_id');
                      });
                });
                if (!empty($instructorScopedCourseIds)) {
                    $w->orWhere(function ($i) use ($instructorScopedCourseIds) {
                        $i->where('is_instructor_only', true)
                          ->whereIn('course_id', $instructorScopedCourseIds);
                    });
                }
            });
        };

        return $this->runRetrieval($query, $scope, $limit, $this->vectorEnabledCorsi());
    }

    public function searchForUser(
        string $query,
        array $courseIds,
        bool $isInstructor,
        int $limit = 5
    ) {
        $scope = function (Builder $q) use ($courseIds, $isInstructor) {
            // Separazione dei mondi: il mondo corsi (studenti e instructor) vede
            // SOLO platform/instructor_only, mai i chunk Schola (course_id NULL).
            $q->whereIn('scope', ['platform', 'instructor_only']);
            if ($isInstructor) {
                // Instructor: no filter on course_id, no filter on is_instructor_only.
                // Coerente con auto_enroll_all_courses=true.
                return;
            }
            $q->where('is_instructor_only', false);
            $q->where(function ($w) use ($courseIds) {
                if (!empty($courseIds)) {
                    $w->whereIn('course_id', $courseIds);
                }
                $w->orWhereNull('course_id');
            });
        };

        return $this->runRetrieval($query, $scope, $limit, $this->vectorEnabledCorsi());
    }

    /**
     * Retrieval Schola per studente di classe (vincolo AI §5): SOLO chunk
     * scope='class' delle classi attive passate, più — se valorizzato — i
     * teacher_private del docente. Usa il percorso vettoriale con soglia
     * quando abilitato (default per Schola); altrimenti ILIKE.
     *
     * Restituzione vuota = "non è nei materiali della classe" (gate §5): il
     * chiamante (pacchetto 6) registra unanswered_question e NON chiama il modello.
     */
    public function searchClassScoped(
        string $query,
        array $classIds,
        ?string $teacherId = null,
        int $limit = 5,
        ?string $subjectId = null,
        bool $connect = false
    ) {
        $classIds = array_values(array_filter($classIds));
        if (empty($classIds) && $teacherId === null) {
            return collect(); // nessuno scope → nessun risultato (sicurezza)
        }

        $scope = function (Builder $q) use ($classIds, $teacherId, $subjectId, $connect) {
            $q->where(function ($w) use ($classIds, $teacherId, $subjectId, $connect) {
                $this->orClassTeacherScopes($w, $classIds, $teacherId, $subjectId, $connect);
            });
        };

        return $this->runRetrieval($query, $scope, $limit, $this->vectorEnabledSchola());
    }

    /**
     * Rami di scope leggibili in ambito Schola, condivisi da searchClassScoped[Scored]:
     *  - class: chunk delle classi passate;
     *  - teacher_private: i chunk del docente stesso;
     *  - teacher_shared: materiali CONDIVISI da altri docenti visibili a questo, secondo
     *    l'ambito nel metadata (share_scope='all', oppure 'subject' con stessa materia +
     *    stessa scuola verificate su professor_subjects).
     */
    private function orClassTeacherScopes(Builder $w, array $classIds, ?string $teacherId, ?string $subjectId = null, bool $connect = false): void
    {
        // Filtro materia (default): il chunk è della materia richiesta, oppure non
        // classificato (fallback prudente). In modalità "connect" (collegamenti
        // cross-materia) il filtro si disattiva → entrano tutte le materie in scope.
        $bySubject = function ($q) use ($subjectId, $connect) {
            if ($subjectId !== null && !$connect) {
                $q->where(function ($s) use ($subjectId) {
                    $s->where('subject_id', $subjectId)->orWhereNull('subject_id');
                });
            }
        };

        if (!empty($classIds)) {
            $w->orWhere(function ($c) use ($classIds, $bySubject) {
                $c->where('scope', 'class')->whereIn('school_class_id', $classIds);
                $bySubject($c);
            });
        }

        if ($teacherId !== null) {
            $teacherSchoolId = Student::whereKey($teacherId)->value('school_id');

            $w->orWhere(function ($t) use ($teacherId, $bySubject) {
                $t->where('scope', 'teacher_private')->where('teacher_id', $teacherId);
                $bySubject($t);
            });

            $w->orWhere(function ($ts) use ($teacherId, $teacherSchoolId, $bySubject) {
                $ts->where('scope', 'teacher_shared')
                    ->where('teacher_id', '!=', $teacherId) // i propri già in teacher_private
                    ->where(function ($rule) use ($teacherId, $teacherSchoolId) {
                        // Tutta la scuola (o materiale admin di scuola): stesso school_id.
                        $rule->where(function ($all) use ($teacherSchoolId) {
                            $all->where('metadata->share_scope', 'all')
                                ->where('metadata->school_id', $teacherSchoolId);
                        })
                            ->orWhere(function ($subj) use ($teacherId) {
                                $subj->where('metadata->share_scope', 'subject')
                                    ->whereExists(function ($ex) use ($teacherId) {
                                        $ex->select(DB::raw('1'))
                                            ->from('professor_subjects as ps')
                                            ->where('ps.teacher_id', $teacherId)
                                            ->whereRaw("ps.subject_id::text = documents_rag.metadata->>'subject_id'")
                                            ->whereRaw("ps.school_id::text = documents_rag.metadata->>'school_id'");
                                    });
                            });
                    });
                $bySubject($ts);
            });
        }
    }

    /**
     * Variante "scored" del retrieval di classe per il gate §5 (pacchetto 6b):
     * oltre ai documenti sopra soglia, ritorna la MIGLIOR similarità trovata
     * nello scope (anche se sotto soglia) — serve a unanswered_questions — e se
     * il percorso usato è vettoriale.
     *
     *  - $artifactId: pre-filtro opzionale sul documento sorgente (apertura
     *    chat dal contesto di un artefatto: si interroga "prima di tutto" quello).
     *
     * @return array{docs: \Illuminate\Support\Collection, best_similarity: float|null, vector: bool}
     */
    public function searchClassScopedScored(
        string $query,
        array $classIds,
        ?string $teacherId = null,
        int $limit = 6,
        ?string $artifactId = null,
        ?string $lessonId = null,
        ?string $subjectId = null,
        bool $connect = false
    ): array {
        $classIds = array_values(array_filter($classIds));
        if (empty($classIds) && $teacherId === null) {
            return ['docs' => collect(), 'best_similarity' => null, 'vector' => false];
        }

        $scope = function (Builder $q) use ($classIds, $teacherId, $artifactId, $lessonId, $subjectId, $connect) {
            $q->where(function ($w) use ($classIds, $teacherId, $subjectId, $connect) {
                $this->orClassTeacherScopes($w, $classIds, $teacherId, $subjectId, $connect);
            });
            if ($artifactId !== null) {
                $q->where('metadata->artifact_id', $artifactId);
            }
            // Minerva di lezione (P20b): pre-filtro sui chunk di QUELLA lezione.
            if ($lessonId !== null) {
                $q->where('metadata->lesson_id', $lessonId);
            }
        };

        if ($this->vectorEnabledSchola() && $this->embeddingColumnExists()) {
            try {
                $vector = $this->embeddings->embedOne($query);
            } catch (Throwable $e) {
                Log::warning('[rag] embedding query (class) fallito, fallback ILIKE', ['error' => $e->getMessage()]);

                return ['docs' => $this->ilikeSearch($query, $scope, $limit), 'best_similarity' => null, 'vector' => false];
            }

            $literal = PgVector::toLiteral($vector);
            $minSim = $this->minSimilarity();

            $q = DocumentRag::query()->whereNotNull('embedding');
            $scope($q);
            $rows = $q->select('documents_rag.*')
                ->selectRaw('1 - (embedding <=> ?::vector) AS _similarity', [$literal])
                ->orderByRaw('embedding <=> ?::vector', [$literal])
                ->limit(max($limit, 1))
                ->get();

            $best = $rows->isNotEmpty() ? (float) $rows->first()->_similarity : null;
            $docs = $rows->filter(fn ($r) => (float) $r->_similarity >= $minSim)->take($limit)->values();

            return ['docs' => $docs, 'best_similarity' => $best, 'vector' => true];
        }

        return ['docs' => $this->ilikeSearch($query, $scope, $limit), 'best_similarity' => null, 'vector' => false];
    }

    // ===== Motore di retrieval (vettoriale con fallback ILIKE) =====

    /**
     * Esegue il retrieval applicando lo scope dato. Se $vectorEnabled e il
     * percorso vettoriale è praticabile (colonna embedding presente, query
     * embeddabile), usa la similarità coseno con soglia; altrimenti ILIKE.
     */
    private function runRetrieval(string $query, \Closure $scope, int $limit, bool $vectorEnabled)
    {
        if ($vectorEnabled) {
            $hit = $this->vectorSearch($query, $scope, $limit);
            if ($hit !== null) {
                return $hit; // percorso vettoriale praticabile (anche vuoto = gate §5)
            }
            // null = percorso non praticabile (videoai giù / colonna assente) → fallback ILIKE
        }

        return $this->ilikeSearch($query, $scope, $limit);
    }

    /**
     * Retrieval vettoriale: coseno con soglia minima (schola.rag_min_similarity).
     * Ritorna null se non praticabile (così il chiamante fa fallback ILIKE);
     * ritorna una collection (eventualmente vuota) se il percorso ha funzionato.
     */
    private function vectorSearch(string $query, \Closure $scope, int $limit): ?\Illuminate\Support\Collection
    {
        if (!$this->embeddingColumnExists()) {
            return null;
        }

        try {
            $vector = $this->embeddings->embedOne($query);
        } catch (Throwable $e) {
            Log::warning('[rag] embedding query fallito, fallback ILIKE', ['error' => $e->getMessage()]);
            return null;
        }

        $literal = PgVector::toLiteral($vector);
        $maxDistance = 1.0 - $this->minSimilarity(); // distanza coseno = 1 - similarità

        $q = DocumentRag::query()->whereNotNull('embedding');
        $scope($q);
        $q->whereRaw('embedding <=> ?::vector < ?', [$literal, $maxDistance])
          ->orderByRaw('embedding <=> ?::vector', [$literal])
          ->limit($limit);

        return $q->get();
    }

    /**
     * Retrieval keyword/ILIKE storico (immutato come comportamento).
     */
    private function ilikeSearch(string $query, \Closure $scope, int $limit)
    {
        $terms = array_filter(array_map('trim', preg_split('/\s+/', $query)), fn ($t) => mb_strlen($t) >= 3);
        if (empty($terms)) $terms = [$query];

        $q = DocumentRag::query();
        $scope($q);
        $q->where(function ($w) use ($terms) {
            foreach ($terms as $term) {
                $w->orWhere('content', 'ILIKE', '%' . $term . '%')
                  ->orWhere('title', 'ILIKE', '%' . $term . '%');
            }
        });

        return $q->limit($limit)->get();
    }

    // ===== Flag e soglia (settings) =====

    private function vectorEnabledCorsi(): bool
    {
        // Default FALSE: il mondo corsi resta su ILIKE finché non lo validiamo
        // (regola di separazione dei mondi).
        return filter_var(atheneum_setting('rag_vector_enabled_corsi', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function vectorEnabledSchola(): bool
    {
        return filter_var(atheneum_setting('rag_vector_enabled_schola', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function minSimilarity(): float
    {
        return (float) atheneum_setting('schola.rag_min_similarity', 0.30);
    }

    private ?bool $embeddingColumnCache = null;

    private function embeddingColumnExists(): bool
    {
        if ($this->embeddingColumnCache !== null) {
            return $this->embeddingColumnCache;
        }

        try {
            $this->embeddingColumnCache = PgVector::available()
                && Schema::hasColumn('documents_rag', 'embedding');
        } catch (Throwable) {
            $this->embeddingColumnCache = false;
        }

        return $this->embeddingColumnCache;
    }

    public function searchVideos(string $query, ?string $courseId = null, ?string $moduleId = null, int $limit = 3): array
    {
        try {
            // Build the video corpus: courses + modules by scope.
            $modules = collect();
            $courses = collect();

            if ($moduleId) {
                $m = Module::whereNotNull('video_ai_id')->where('id', $moduleId)->with('course')->first();
                if ($m) $modules = collect([$m]);
            } elseif ($courseId) {
                $modules = Module::whereNotNull('video_ai_id')
                    ->where('course_id', $courseId)
                    ->where('is_active', true)
                    ->with('course')
                    ->get();
                $c = Course::whereNotNull('video_ai_id')->where('id', $courseId)->first();
                if ($c) $courses = collect([$c]);
            } else {
                $modules = Module::whereNotNull('video_ai_id')->with('course')->get();
                $courses = Course::whereNotNull('video_ai_id')->get();
            }

            $videoIds = array_values(array_unique(array_merge(
                $modules->pluck('video_ai_id')->toArray(),
                $courses->pluck('video_ai_id')->toArray(),
            )));

            if (empty($videoIds)) return [];

            $videoAI = app(VideoAIService::class);
            $results = $videoAI->search($query, $videoIds);

            $formatted = [];
            $seen = [];
            foreach (array_slice($results, 0, $limit * 3) as $result) {
                $vid = $result['video_id'] ?? null;
                if (!$vid) continue;

                $module = $modules->firstWhere('video_ai_id', $vid);
                $course = $module ? $module->course : $courses->firstWhere('video_ai_id', $vid);
                $isCourseVideo = !$module && $course;

                foreach (array_slice($result['matches'] ?? [], 0, 2) as $match) {
                    $tsSeconds = isset($match['timestamp_seconds'])
                        ? (int) $match['timestamp_seconds']
                        : (isset($match['start']) ? (int) $match['start'] : 0);
                    $tsStr = $match['timestamp_str'] ?? $this->formatTimestamp($tsSeconds);

                    $dedupeKey = $vid . '|' . $tsSeconds;
                    if (isset($seen[$dedupeKey])) continue;
                    $seen[$dedupeKey] = true;

                    $courseSlug = $course?->slug;
                    $deepLink = null;
                    if ($courseSlug) {
                        $deepLink = $isCourseVideo
                            ? "/learn/course/{$courseSlug}?t={$tsSeconds}"
                            : "/learn/course/{$courseSlug}/module/{$module->id}?t={$tsSeconds}";
                    }

                    $title = $isCourseVideo
                        ? '🎬 Video corso: ' . ($course->name ?? 'Corso')
                        : '🎬 Video modulo: ' . ($module->title ?? 'Modulo');

                    $formatted[] = [
                        'content' => $match['text'] ?? '',
                        'text' => $match['text'] ?? '',
                        'title' => $title,
                        'type' => 'video',
                        'scope' => $isCourseVideo ? 'course' : 'module',
                        'timestamp' => $tsStr,
                        'timestamp_str' => $tsStr,
                        'timestamp_seconds' => $tsSeconds,
                        'video_ai_id' => $vid,
                        'module_id' => $module?->id,
                        'course_id' => $course?->id,
                        'course_slug' => $courseSlug,
                        'deep_link' => $deepLink,
                    ];
                }
            }

            return array_slice($formatted, 0, $limit);
        } catch (\Exception $e) {
            Log::error('VideoAI search error: ' . $e->getMessage());
            return [];
        }
    }

    private function formatTimestamp(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }

    private function chunkText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $text = trim($text);
        $length = mb_strlen($text, 'UTF-8');
        if ($length <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;

        while ($start < $length) {
            $chunks[] = mb_substr($text, $start, $chunkSize, 'UTF-8');
            $start += $chunkSize - $overlap;
        }

        return $chunks;
    }
}
