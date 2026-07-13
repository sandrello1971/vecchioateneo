<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'slug', 'description', 'short_description', 'color',
        'icon', 'duration_hours', 'certification_name', 'is_active', 'sort_order',
        'video_ai_id', 'video_filename', 'video_status',
        'exam_prep_html', 'modality',
    ];

    /** Modalità di erogazione: governa il calcolo delle ore nel registro. */
    public const MODALITY_ASYNC = 'async'; // FAD asincrona → contano le ore FAD
    public const MODALITY_SYNC = 'sync';   // aula/webinar → contano le presenze

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    // P29 — documento PDF generato dell'INTERO corso (uno per corso).
    public function document()
    {
        return $this->hasOne(CourseDocument::class);
    }

    /**
     * Hash aggregato del contenuto del corso, per lo stale del documento-corso.
     * Concatena i moduli ORDINATI per sort_order: id|sort_order|title|hash(content).
     * Cambia se un modulo cambia contenuto/titolo, viene aggiunto, rimosso o riordinato.
     * Il title è incluso: fa parte del documento (intestazione di sezione).
     */
    public function currentContentHash(): string
    {
        $parts = $this->modules()
            ->orderBy('sort_order')
            ->get(['id', 'sort_order', 'title', 'content'])
            ->map(fn (Module $m) => $m->id . '|' . $m->sort_order . '|' . (string) $m->title . '|' . $m->currentContentHash());

        return md5($parts->implode("\n"));
    }

    public function instructorMaterials()
    {
        return $this->hasMany(Material::class)
            ->where('is_instructor_only', true)
            ->orderBy('sort_order');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function documentsRag()
    {
        return $this->hasMany(DocumentRag::class);
    }

    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_course')
            ->withPivot('enrolled_at', 'expires_at', 'completed_at', 'is_active', 'notes', 'instructor_id')
            ->withTimestamps();
    }

    public function instructors()
    {
        return $this->belongsToMany(Student::class, 'course_instructor', 'course_id', 'instructor_id')
            ->withTimestamps();
    }

    public function hasMultipleInstructors(): bool
    {
        return $this->instructors()->count() > 1;
    }

    /** Corso FAD asincrono: nel registro contano solo le ore FAD. */
    public function isAsync(): bool
    {
        return $this->modality === self::MODALITY_ASYNC;
    }

    /** Corso in aula/webinar: nel registro contano solo le presenze alle sessioni. */
    public function isSync(): bool
    {
        return $this->modality === self::MODALITY_SYNC;
    }

    /** Etichetta leggibile della modalità di erogazione. */
    public function modalityLabel(): string
    {
        return match ($this->modality) {
            self::MODALITY_ASYNC => 'Asincrono (FAD)',
            self::MODALITY_SYNC => 'Sincrono (aula/webinar)',
            default => 'Non impostata',
        };
    }

    public function sessions()
    {
        return $this->hasMany(CourseSession::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function conceptMaps()
    {
        return $this->hasMany(CourseConceptMap::class)->orderBy('sort_order')->orderBy('created_at');
    }

    // P25.1 — sorgenti strutturati versionati (Course Freshness Agent).
    public function sources()
    {
        return $this->hasMany(CourseSource::class)->orderByDesc('created_at');
    }

    // P25.B-a — sorgente studente versionato (modules.content), per versioning/rollback.
    public function studentSourceVersions()
    {
        return $this->hasMany(StudentSourceVersion::class)->orderByDesc('created_at');
    }

    // P26 — gap di copertura candidati (Scout).
    public function coverageGaps()
    {
        return $this->hasMany(CoverageGap::class);
    }

    // P26.2 — topic pesati del corso (fonte di verità multi-topic).
    public function courseTopics()
    {
        return $this->hasMany(CourseTopic::class);
    }

    /** @return list<string> tutti gli slug dei topic del corso */
    public function topicSlugs(): array
    {
        return $this->courseTopics()->pluck('topic')->all();
    }

    /** Lo slug del topic primary, o null. */
    public function primaryTopic(): ?string
    {
        return $this->courseTopics()->where('weight', 'primary')->value('topic');
    }

    /**
     * Topic effettivi del corso per lo Scout: la pivot (verità). Retrocompat: se la pivot è vuota
     * ma c'è il vecchio singolo course_freshness_configs.topic, lo tratta come unico 'primary'.
     *
     * @return \Illuminate\Support\Collection<int,array{topic:string,weight:string}>
     */
    public function effectiveTopics(): \Illuminate\Support\Collection
    {
        $pivot = $this->courseTopics()->get(['topic', 'weight']);
        if ($pivot->isNotEmpty()) {
            return $pivot->map(fn ($t) => ['topic' => $t->topic, 'weight' => $t->weight]);
        }
        $legacy = optional($this->freshnessConfig)->topic;

        return $legacy ? collect([['topic' => $legacy, 'weight' => 'primary']]) : collect();
    }

    // P25.2 — esecuzioni dell'agente e config per corso (Course Freshness Agent).
    public function freshnessRuns()
    {
        return $this->hasMany(FreshnessRun::class)->orderByDesc('started_at');
    }

    public function freshnessConfig()
    {
        return $this->hasOne(CourseFreshnessConfig::class);
    }

    // P25.3 — proposte di aggiornamento (coda HITL).
    public function updateProposals()
    {
        return $this->hasMany(UpdateProposal::class)->orderByDesc('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
