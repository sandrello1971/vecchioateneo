<?php

namespace App\Services\Schola;

use App\Models\ChatMessage;
use App\Models\StudentGeneratedArtifact;
use Illuminate\Support\Carbon;

/**
 * Limiti di consumo AI giornalieri per studente (decisione SPEC §8.2): controllo
 * costi su auto-generazioni e messaggi alla Minerva di classe. Soglie da
 * settings. Conteggio su tabelle reali (interrogabili/auditabili) anziché in
 * cache: niente reset accidentali e il docente vede comunque tutto.
 *
 * "Il giorno dopo riparte": il conteggio è per data odierna (whereDate).
 */
class ScholaUsage
{
    public function generationLimit(): int
    {
        return max(0, (int) atheneum_setting('schola.student_daily_generations', 10));
    }

    public function chatLimit(): int
    {
        return max(0, (int) atheneum_setting('schola.student_daily_chat_messages', 50));
    }

    public function generationsToday(string $studentId): int
    {
        return StudentGeneratedArtifact::where('student_id', $studentId)
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    public function chatMessagesToday(string $studentId): int
    {
        // Solo i messaggi dell'utente nelle conversazioni di CLASSE (school_class_id non null).
        return ChatMessage::where('role', 'user')
            ->whereDate('chat_messages.created_at', Carbon::today())
            ->whereHas('conversation', fn ($q) => $q
                ->where('student_id', $studentId)
                ->whereNotNull('school_class_id'))
            ->count();
    }

    /** @return array{allowed: bool, used: int, limit: int, remaining: int} */
    public function generationStatus(string $studentId): array
    {
        return $this->status($this->generationsToday($studentId), $this->generationLimit());
    }

    /** @return array{allowed: bool, used: int, limit: int, remaining: int} */
    public function chatStatus(string $studentId): array
    {
        return $this->status($this->chatMessagesToday($studentId), $this->chatLimit());
    }

    private function status(int $used, int $limit): array
    {
        return [
            'allowed' => $used < $limit,
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
        ];
    }

    /** Messaggio gentile a soglia raggiunta. */
    public function limitMessage(string $kind): string
    {
        return $kind === 'chat'
            ? 'Hai raggiunto il numero massimo di domande a Minerva per oggi. Riprova domani — nel frattempo rivedi i materiali della classe. 🙂'
            : 'Hai raggiunto il numero massimo di generazioni per oggi. Riprova domani: gli artefatti già creati restano disponibili. 🙂';
    }
}
