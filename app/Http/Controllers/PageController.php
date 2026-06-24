<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        // Atheneum: landing pubblica latina/Noscite (home.blade.php), adottata
        // dal ramo VPS in FASE 2. La vecchia landing GLITCH resta su disco
        // (resources/views/landing.blade.php) ma non è più instradata.
        return view('home');
    }

    public function primus(): View
    {
        return view('primus');
    }

    public function consilium(): View
    {
        return view('consilium');
    }

    public function initium(): View
    {
        return view('initium');
    }

    public function structura(): View
    {
        return view('structura');
    }

    public function aiAgentsMcp(): View
    {
        return view('ai-agents-mcp');
    }

    public function conformitaAiAct(): View
    {
        return view('conformita-ai-act');
    }

    public function risorse(): View
    {
        return view('risorse');
    }

    public function contatti(): View
    {
        return view('contatti');
    }

    public function mappaPercorso(): View
    {
        return view('mappa-percorso');
    }

    public function mappaPercorsoGrazie(): View
    {
        return view('mappa-percorso-grazie');
    }

    public function privacyPolicy(): View
    {
        return view('privacy-policy');
    }

    public function cookiePolicy(): View
    {
        return view('cookie-policy');
    }
}
