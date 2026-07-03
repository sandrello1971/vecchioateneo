<?php

namespace Tests\Feature\Schola;

use App\Services\Tts\ElevenLabsTtsProvider;
use App\Services\Tts\TtsProvider;
use Tests\TestCase;

/**
 * Il provider TTS si risolve da config('services.tts.provider') — non cablato.
 * Aggiungere un fornitore = una classe + una voce in config + TTS_PROVIDER in .env.
 */
class TtsProviderResolverTest extends TestCase
{
    public function test_default_risolve_elevenlabs(): void
    {
        config(['services.tts.provider' => 'elevenlabs']);
        $this->assertInstanceOf(ElevenLabsTtsProvider::class, app(TtsProvider::class));
    }

    public function test_provider_sconosciuto_lancia(): void
    {
        config(['services.tts.provider' => 'inesistente']);
        $this->expectException(\RuntimeException::class);
        app(TtsProvider::class);
    }

    public function test_provider_personalizzato_via_config(): void
    {
        config([
            'services.tts.provider' => 'fake',
            'services.tts.providers.fake' => FakeTtsProviderForResolver::class,
        ]);
        $this->assertInstanceOf(FakeTtsProviderForResolver::class, app(TtsProvider::class));
    }
}

class FakeTtsProviderForResolver implements TtsProvider
{
    public function synthesize(string $text, array $options = []): string
    {
        return 'fake-audio';
    }
}
