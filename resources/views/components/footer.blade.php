<footer class="bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            {{-- Col 1: Brand --}}
            <div>
                <a href="{{ route('home') }}" class="inline-block mb-4">
                    <img src="/images/logo.png" alt="{{ atheneum_setting('instance_name', 'Atheneum') }}" class="h-10 w-auto brightness-0 invert">
                </a>
                <p class="text-accent-400 text-sm font-medium mb-2">Formazione AI per le PMI italiane</p>
                <p class="text-gray-400 text-sm leading-relaxed">
                    L'Officina The Glitch World offre programmi formativi su intelligenza artificiale, knowledge management e trasformazione digitale. Percorsi pratici per imprenditori, manager e team operativi.
                </p>
            </div>

            {{-- Col 2: Programmi --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-300 mb-4">Programmi</h3>
                <ul class="space-y-2">
                    <li><a href="{{ route('consilium') }}" class="text-gray-400 hover:text-white text-sm transition-colors">Interferenza</a></li>
                    <li><a href="{{ route('initium') }}" class="text-gray-400 hover:text-white text-sm transition-colors">Segnale</a></li>
                    <li><a href="{{ route('structura') }}" class="text-gray-400 hover:text-white text-sm transition-colors">Circuito</a></li>
                    <li><a href="{{ route('ai-demystified') }}" class="text-gray-400 hover:text-white text-sm transition-colors">AI Demystified</a></li>
                    <li><a href="{{ route('second-brain') }}" class="text-gray-400 hover:text-white text-sm transition-colors">Second Brain</a></li>
                </ul>
            </div>

            {{-- Col 3: Contatti --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-300 mb-4">Contatti</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <a href="mailto:{{ atheneum_setting('contact_email', 'info@noscite.it') }}" class="hover:text-white transition-colors">{{ atheneum_setting('contact_email', 'info@noscite.it') }}</a>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                        <span>atheneum.noscite.it</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Milano, Italia</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-10 pt-6 border-t border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-gray-500 text-xs">&copy; {{ date('Y') }} {{ atheneum_setting('platform_owner', 'Noscite') }}. Tutti i diritti riservati.</p>
            <a href="{{ route('privacy') }}" class="text-gray-500 hover:text-gray-300 text-xs transition-colors">Privacy Policy</a>
        </div>
    </div>
</footer>
