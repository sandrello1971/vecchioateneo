<?php

namespace App\Support;

use App\Models\Student;

/**
 * R5 — gate compliance per i materiali Schola che passano da SUB-PROCESSORI ESTERNI
 * (audio/video → Whisper/Groq, foto → Vision/Anthropic). In ambito scolastico (docente
 * con scuola) servono il consenso DPA registrato sulla scuola. I contenuti GENERATI
 * dalla piattaforma NON passano di qui (solo embedding locali) → nessun gate.
 *
 * Officina business: nessun blocco (nessun minore; comportamento attuale invariato).
 */
class VideoAiConsent
{
    public const MESSAGE = 'Per elaborare materiali audio/video o foto in ambito scolastico serve il consenso al trattamento tramite sub-processori esterni (audio/video → Whisper, immagini → Vision). Configurare il DPA video-AI della scuola prima di procedere.';

    /** Tipi sorgente che inviano contenuto a sub-processori esterni. */
    public static function externalSourceTypes(): array
    {
        return (array) config('services.videoai.dpa_required_source_types', ['audio', 'youtube', 'photos']);
    }

    /**
     * Il docente è di una SCUOLA senza consenso DPA video-AI? → nei form di upload
     * audio/video/foto vanno disabilitati. Centralizzato per riuso (materiali/lezione/argomento).
     */
    public static function dpaMissing(?Student $teacher): bool
    {
        return (bool) ($teacher && $teacher->school_id && !optional($teacher->school)->hasVideoAiDpa());
    }

    /**
     * L'elaborazione di questo materiale è BLOCCATA per mancanza di consenso DPA?
     * True solo se: docente di una SCUOLA + tipo che usa sub-processori esterni +
     * scuola senza consenso DPA video-AI.
     */
    public static function blocked(?Student $teacher, string $sourceType): bool
    {
        if (!$teacher || !$teacher->school_id) {
            return false; // non Schola → libero (Officina business)
        }
        if (!in_array($sourceType, self::externalSourceTypes(), true)) {
            return false; // locale (pdf/docx/text) → nessun sub-processore esterno
        }

        return !optional($teacher->school)->hasVideoAiDpa();
    }
}
