<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'name',
        'company',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'ip_address',
        'user_agent',
        'privacy_accepted',
        'privacy_accepted_at',
        'email_sent',
        'email_sent_at',
        'email_error',
        'student_id',
        'metadata',
    ];

    protected $casts = [
        'privacy_accepted' => 'boolean',
        'privacy_accepted_at' => 'datetime',
        'email_sent' => 'boolean',
        'email_sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Lo Student a cui il lead è stato collegato (se è diventato studente).
     * Nullable: la maggior parte dei lead non diventerà mai studente.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Collega retroattivamente tutti i lead esistenti (con student_id NULL)
     * a uno Student appena creato, matchando via email case-insensitive.
     *
     * Da chiamare nel signup flow studenti subito dopo Student::create(),
     * così lo storico dei lead di quella email viene agganciato al nuovo
     * account (utile per attribution e conversion tracking).
     *
     * @return int Numero di lead aggiornati
     */
    public static function linkToStudent(Student $student): int
    {
        return self::whereRaw('LOWER(email) = ?', [strtolower($student->email)])
            ->whereNull('student_id')
            ->update(['student_id' => $student->id]);
    }
}
