<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'course_id', 'title', 'description',
        'passing_score', 'time_limit_minutes', 'max_attempts',
        'randomize_questions', 'questions_per_attempt', 'show_results_immediately', 'is_active',
        'is_demo',
    ];

    protected $casts = [
        'randomize_questions' => 'boolean',
        'questions_per_attempt' => 'integer',
        'show_results_immediately' => 'boolean',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    // Schola: un quiz può essere l'output di un teaching_artifact (module_id NULL).
    public function teachingArtifact()
    {
        return $this->hasOne(TeachingArtifact::class);
    }

    /**
     * Estrae gli id delle domande da somministrare in UN tentativo.
     *  - questions_per_attempt = K (con 0 < K < pool) → K id casuali dal pool;
     *  - null o K >= pool → TUTTE le domande (retrocompat), nell'ordine sort_order
     *    o mescolate se randomize_questions.
     * L'ordine restituito è quello effettivo di somministrazione.
     *
     * @return list<string>
     */
    public function buildAttemptQuestionIds(): array
    {
        $ids = $this->questions()->orderBy('sort_order')->pluck('id')->all();
        $k = $this->questions_per_attempt;

        if ($k !== null && $k > 0 && $k < count($ids)) {
            shuffle($ids);              // estrazione casuale del sottoinsieme
            return array_slice($ids, 0, $k);
        }

        if ($this->randomize_questions) {
            shuffle($ids);              // tutte, ma in ordine casuale (come oggi)
        }

        return $ids;
    }

    /** Domande effettivamente somministrate per K (display): questions_per_attempt o l'intero pool. */
    public function effectiveQuestionCount(): int
    {
        $pool = $this->questions()->count();

        return ($this->questions_per_attempt !== null && $this->questions_per_attempt < $pool)
            ? $this->questions_per_attempt
            : $pool;
    }
}
