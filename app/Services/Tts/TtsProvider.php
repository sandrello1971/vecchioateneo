<?php

namespace App\Services\Tts;

/**
 * Astrazione TTS parametrica (DESIGN_tts_parametrico): il provider concreto è scelto
 * da config('services.tts.provider'). Cambiare fornitore = aggiungere una classe che
 * implementa questa interfaccia + una riga in config/services.php + .env. synthesize()
 * ritorna i BYTE dell'audio MP3.
 *
 * @phpstan-type TtsOptions array{voice_id?: string}
 */
interface TtsProvider
{
    /**
     * @param array{voice_id?: string} $options
     */
    public function synthesize(string $text, array $options = []): string;
}
