<?php

namespace App\Services\Freshness;

use Illuminate\Http\Client\Response;

/**
 * Costruisce un messaggio d'errore LEGGIBILE da una risposta non-ok dell'API Anthropic,
 * includendo il corpo (`error.message`) — es. "Your credit balance is too low…".
 * Senza questo, l'agente registrava in `failure_reason` solo "HTTP 400", inutile a schermo.
 */
class AnthropicError
{
    public static function message(Response $response, string $phase): string
    {
        $base = "Anthropic API errore {$phase}: HTTP " . $response->status();

        $detail = $response->json('error.message');
        if (is_string($detail) && trim($detail) !== '') {
            return $base . ' — ' . trim($detail);
        }

        return $base;
    }
}
