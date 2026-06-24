<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Header di sicurezza HTTP applicati a tutte le risposte del gruppo 'web'.
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS solo su connessione sicura: non forzare HTTPS in locale/dev http.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content-Security-Policy: NON attivata di default (rischio di rompere
        // Alpine/Vite/inline script/KaTeX). Proposta di base da valutare e attivare
        // con consapevolezza:
        // $response->headers->set('Content-Security-Policy',
        //     "default-src 'self'; "
        //     . "script-src 'self' 'unsafe-inline'; "
        //     . "style-src 'self' 'unsafe-inline'; "
        //     . "img-src 'self' data:; "
        //     . "font-src 'self' data:; "
        //     . "connect-src 'self'; "
        //     . "frame-ancestors 'self'; "
        //     . "base-uri 'self'; "
        //     . "form-action 'self'");

        return $response;
    }
}
