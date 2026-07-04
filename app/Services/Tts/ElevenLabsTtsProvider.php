<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Provider TTS di default: ElevenLabs (text-to-speech API). Unico punto che parla con
 * ElevenLabs; ritorna i byte MP3. Chiave in config('services.elevenlabs.key').
 */
class ElevenLabsTtsProvider implements TtsProvider
{
    private const BASE = 'https://api.elevenlabs.io/v1/text-to-speech';

    public function synthesize(string $text, array $options = []): string
    {
        $key = config('services.elevenlabs.key');
        if (empty($key)) {
            throw new RuntimeException('ELEVENLABS_API_KEY non configurata.');
        }

        $voiceId = $options['voice_id']
            ?? config('services.tts.voice_id')
            ?? config('services.elevenlabs.voice_id');

        $response = Http::withHeaders([
            'xi-api-key' => $key,
            'accept' => 'audio/mpeg',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::BASE . '/' . $voiceId, [
            'text' => $text,
            'model_id' => config('services.elevenlabs.model', 'eleven_multilingual_v2'),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore ElevenLabs: ' . $response->status());
        }

        $audio = $response->body();
        if ($audio === '') {
            throw new RuntimeException('ElevenLabs ha restituito audio vuoto.');
        }

        return $audio;
    }
}
