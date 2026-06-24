<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Certificate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'student_id',
        'course_id',
        'quiz_attempt_id',
        'code',
        'score',
        'issued_at',
        'certification_name',
        'metadata',
        'unsigned_pdf_path',
        'signed_pdf_path',
        'signed_at',
        'signed_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'metadata' => 'array',
        'signed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function quizAttempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /**
     * True se il certificato ha un PDF firmato persistito su disco.
     *
     * Verifica sia la presenza del path nel DB sia l'effettiva esistenza
     * del file: ci protegge da casi in cui il file sia stato eliminato
     * accidentalmente dal filesystem ma il record DB non aggiornato.
     */
    public function isSigned(): bool
    {
        return $this->signed_pdf_path !== null
            && Storage::disk('local')->exists($this->signed_pdf_path);
    }

    /**
     * True se il certificato è stato generato (PDF non firmato presente)
     * ma non è ancora stato firmato dal legale rappresentante.
     *
     * Usato dall'admin UI per la lista "Certificati in attesa di firma".
     */
    public function pendingSignature(): bool
    {
        return $this->unsigned_pdf_path !== null
            && $this->signed_pdf_path === null;
    }

    /**
     * Restituisce il path relativo (rispetto a Storage::disk('local'))
     * del PDF "migliore" disponibile per questo certificato:
     *  - signed_pdf_path se firmato e file presente su disco
     *  - unsigned_pdf_path se generato ma non firmato (e file presente)
     *  - null se nessun PDF persistito (certificato legacy creato prima
     *    del refactor → fallback alla generazione on-the-fly nel controller)
     */
    public function effectivePdfPath(): ?string
    {
        if ($this->isSigned()) {
            return $this->signed_pdf_path;
        }
        if ($this->unsigned_pdf_path !== null
            && Storage::disk('local')->exists($this->unsigned_pdf_path)) {
            return $this->unsigned_pdf_path;
        }
        return null;
    }

    /**
     * Genera un codice univoco formato ATH-XXXX-XXXX-XXXX.
     * 80 bit di entropia (base32-friendly), retry su collisione.
     */
    public static function generateCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $raw = random_bytes(10);
            $b32 = strtoupper(str_replace(['+', '/', '='], '', base64_encode($raw)));
            $b32 = substr($b32, 0, 12);
            $code = 'ATH-' . substr($b32, 0, 4) . '-' . substr($b32, 4, 4) . '-' . substr($b32, 8, 4);
            if (!self::where('code', $code)->exists()) {
                return $code;
            }
        }
        throw new \RuntimeException('Impossibile generare un codice certificato univoco dopo 5 tentativi.');
    }
}
