<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * P26.2 — Topic di un corso, pesato: weight=primary (dominio dominante, uno solo) | secondary
 * (domini toccati). È la FONTE DI VERITÀ dei topic del corso (sostituisce il singolo
 * course_freshness_configs.topic, mantenuto solo per retrocompat). Lo Scout cerca nelle fonti
 * di TUTTI i topic del corso.
 */
class CourseTopic extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['course_id', 'topic', 'weight'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Transizione retrocompat: copia i topic singoli già impostati su course_freshness_configs
     * in course_topics come 'primary' (non perde i topic esistenti). Idempotente. Usata sia dalla
     * migrazione sia testabile a parte.
     */
    public static function backfillFromConfigs(): int
    {
        $count = 0;
        foreach (CourseFreshnessConfig::whereNotNull('topic')->get() as $cfg) {
            $slug = Str::slug((string) $cfg->topic);
            if ($slug === '') {
                continue;
            }
            if (static::where('course_id', $cfg->course_id)->where('topic', $slug)->exists()) {
                continue;
            }
            static::create(['course_id' => $cfg->course_id, 'topic' => $slug, 'weight' => 'primary']);
            $count++;
        }

        return $count;
    }
}
