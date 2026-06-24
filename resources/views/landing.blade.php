{{--
    Landing pubblica GLITCH (P24) — pagina pre-login servita su "/".
    Linguaggio visivo del sito madre theglitchworld.it: nero profondo,
    avorio caldo, accento cremisi. Standalone: NON usa layouts.app né alcun
    asset dell'app interna. Font self-hosted (vedi resources/css/glitch-landing.css).
--}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Officina trasforma appunti, lezioni e documenti in materiale di studio vivo. Per chi studia, per chi insegna, per chi manda avanti una scuola.">
    <title>GLITCH / Officina — L'officina di chi impara</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/css/glitch-landing.css'])
</head>
<body class="bg-glitch-black text-glitch-ivory font-mono antialiased selection:bg-glitch-red selection:text-glitch-black">

    <a href="#contenuto"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-glitch-red focus:text-glitch-black focus:px-4 focus:py-2 glitch-navlink">
        Salta al contenuto
    </a>

    {{-- ===================== NAV ===================== --}}
    <header class="border-b border-glitch-ivory/10">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8"
             aria-label="Navigazione principale">
            <a href="/" class="glitch-navlink text-glitch-ivory">
                GLITCH <span class="text-glitch-red">/</span> OFFICINA
            </a>
            <div class="flex items-center gap-5 sm:gap-8">
                <a href="#studenti" class="glitch-navlink text-glitch-ivory hidden sm:inline">Studenti</a>
                <a href="#docenti"  class="glitch-navlink text-glitch-ivory hidden sm:inline">Docenti</a>
                <a href="#scuole"   class="glitch-navlink text-glitch-ivory hidden sm:inline">Scuole</a>
                <a href="{{ route('student.login') }}" class="glitch-navlink text-glitch-red">Accedi</a>
            </div>
        </nav>
    </header>

    <main id="contenuto">

        {{-- ===================== HERO ===================== --}}
        <section class="mx-auto max-w-6xl px-5 pt-20 pb-24 sm:px-8 sm:pt-28 sm:pb-32"
                 aria-labelledby="manifesto">
            <p class="glitch-tag mb-8">Officina · theglitchworld.it</p>
            <h1 id="manifesto" class="glitch-manifesto text-glitch-ivory max-w-4xl">
                L'officina di chi impara<span class="text-glitch-red">.</span>
            </h1>
            <p class="glitch-body mt-10 max-w-2xl text-glitch-ivory/80">
                Officina trasforma appunti, lezioni e documenti in materiale di studio
                vivo. Per chi studia, per chi insegna, per chi manda avanti una scuola.
            </p>
        </section>

        {{-- ===================== SEZIONI NUMERATE ===================== --}}
        <section id="studenti" class="border-t border-glitch-ivory/10" aria-labelledby="studenti-titolo">
            <div class="mx-auto grid max-w-6xl gap-6 px-5 py-20 sm:grid-cols-[auto_1fr] sm:gap-12 sm:px-8 sm:py-24">
                <span class="glitch-numeral" aria-hidden="true">01</span>
                <div class="max-w-2xl">
                    <p class="glitch-tag mb-4">Studenti</p>
                    <h2 id="studenti-titolo" class="glitch-section-title text-glitch-ivory">Per chi studia.</h2>
                    <p class="glitch-body mt-6 text-glitch-ivory/80">
                        Carichi appunti, registrazioni, PDF. Officina li riorganizza in
                        sintesi, mappe e quiz, e risponde alle tue domande restando dentro
                        i materiali della tua classe. Niente risposte inventate: solo ciò
                        che è stato pubblicato per te.
                    </p>
                </div>
            </div>
        </section>

        <section id="docenti" class="border-t border-glitch-ivory/10" aria-labelledby="docenti-titolo">
            <div class="mx-auto grid max-w-6xl gap-6 px-5 py-20 sm:grid-cols-[auto_1fr] sm:gap-12 sm:px-8 sm:py-24">
                <span class="glitch-numeral" aria-hidden="true">02</span>
                <div class="max-w-2xl">
                    <p class="glitch-tag mb-4">Docenti</p>
                    <h2 id="docenti-titolo" class="glitch-section-title text-glitch-ivory">Per chi insegna.</h2>
                    <p class="glitch-body mt-6 text-glitch-ivory/80">
                        Prepari una lezione una volta sola. Diventa materiale di studio per
                        ogni studente — sintesi, mappe concettuali, esercizi — sempre
                        coerente con quello che hai spiegato. Tu decidi cosa pubblicare e a
                        quale classe.
                    </p>
                </div>
            </div>
        </section>

        <section id="scuole" class="border-t border-glitch-ivory/10" aria-labelledby="scuole-titolo">
            <div class="mx-auto grid max-w-6xl gap-6 px-5 py-20 sm:grid-cols-[auto_1fr] sm:gap-12 sm:px-8 sm:py-24">
                <span class="glitch-numeral" aria-hidden="true">03</span>
                <div class="max-w-2xl">
                    <p class="glitch-tag mb-4">Scuole</p>
                    <h2 id="scuole-titolo" class="glitch-section-title text-glitch-ivory">Per chi tiene insieme tutto.</h2>
                    <p class="glitch-body mt-6 text-glitch-ivory/80">
                        Una sede unica per le classi dell'istituto. Ogni docente lavora nel
                        proprio spazio; la scuola governa accessi, dati e conformità. Tutto
                        resta dove deve restare.
                    </p>
                </div>
            </div>
        </section>

        {{-- ===================== CALLOUT + CTA ===================== --}}
        <section class="border-t border-glitch-ivory/10" aria-labelledby="chiusura">
            <div class="mx-auto max-w-6xl px-5 py-24 sm:px-8 sm:py-32">
                <p id="chiusura" class="glitch-callout max-w-3xl pl-6">
                    La conoscenza che non diventa competenza non cambia niente.
                </p>
                <div class="mt-12 flex flex-wrap items-center gap-6">
                    <a href="{{ route('student.login') }}" class="glitch-cta inline-block px-8 py-4">
                        Entra in Officina
                    </a>
                    <span class="glitch-body text-sm text-glitch-ivory/60">
                        Hai già un accesso? Vai alla piattaforma.
                    </span>
                </div>
            </div>
        </section>
    </main>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="border-t border-glitch-ivory/10">
        <div class="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:grid-cols-3 sm:px-8">
            <p class="glitch-body text-sm text-glitch-ivory/60">
                officina.theglitchworld.it
                <span class="text-glitch-red">·</span>
                MMXXVI
            </p>
            <p class="glitch-body text-sm text-glitch-ivory/60 sm:text-center">
                Un progetto <span class="text-glitch-ivory">GLITCH</span> — theglitchworld.it
            </p>
            <p class="glitch-body text-sm text-glitch-ivory/60 sm:text-right">
                <a href="{{ route('contatti') }}" class="glitch-navlink text-glitch-ivory">Contatti</a>
            </p>
        </div>
    </footer>

</body>
</html>
