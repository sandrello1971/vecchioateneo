<header class="fixed top-0 left-0 right-0 z-50 bg-white shadow-sm" x-data="{ mobileOpen: false, programmi: false, scholaris: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex-shrink-0 flex items-center gap-2">
                <img src="/images/logo.png" alt="{{ atheneum_setting('instance_name', 'Atheneum') }}" class="h-10 w-auto">
                <span class="hidden sm:block text-sm font-semibold text-gray-900">Officina</span>
            </a>

            {{-- Nav desktop --}}
            <nav class="hidden lg:flex items-center space-x-1">

                <a href="{{ route('home') }}"
                   class="px-3 py-2 text-sm font-medium rounded-md transition-colors
                          {{ request()->is('/') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
                    Home
                </a>

                {{-- Programmi dropdown --}}
                <div class="relative" @mouseenter="programmi = true" @mouseleave="programmi = false">
                    <button class="px-3 py-2 text-sm font-medium rounded-md transition-colors inline-flex items-center gap-1
                                   {{ request()->is('consilium') || request()->is('initium') || request()->is('structura') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
                        Programmi
                        <svg class="h-4 w-4 transition-transform" :class="programmi ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="programmi" x-cloak
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
                         class="absolute left-0 top-full mt-1 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50">
                        <a href="{{ route('consilium') }}" class="block px-4 py-2.5 text-sm hover:bg-primary-50 hover:text-primary-600 transition-colors {{ request()->is('consilium') ? 'text-primary-600 bg-primary-50' : 'text-gray-700' }}">
                            <span class="font-medium">Interferenza</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Strategia AI per CEO</span>
                        </a>
                        <a href="{{ route('initium') }}" class="block px-4 py-2.5 text-sm hover:bg-primary-50 hover:text-primary-600 transition-colors {{ request()->is('initium') ? 'text-primary-600 bg-primary-50' : 'text-gray-700' }}">
                            <span class="font-medium">Segnale</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Fondamenti digitali</span>
                        </a>
                        <a href="{{ route('structura') }}" class="block px-4 py-2.5 text-sm hover:bg-primary-50 hover:text-primary-600 transition-colors {{ request()->is('structura') ? 'text-primary-600 bg-primary-50' : 'text-gray-700' }}">
                            <span class="font-medium">Circuito</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Second Brain aziendale</span>
                        </a>
                    </div>
                </div>

                {{-- Pro Scholaris dropdown --}}
                <div class="relative" @mouseenter="scholaris = true" @mouseleave="scholaris = false">
                    <button class="px-3 py-2 text-sm font-medium rounded-md transition-colors inline-flex items-center gap-1
                                   {{ request()->is('ai-demystified') || request()->is('second-brain') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
                        Pro Scholaris
                        <svg class="h-4 w-4 transition-transform" :class="scholaris ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="scholaris" x-cloak
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
                         class="absolute left-0 top-full mt-1 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50">
                        <a href="{{ route('ai-demystified') }}" class="block px-4 py-2.5 text-sm hover:bg-primary-50 hover:text-primary-600 transition-colors {{ request()->is('ai-demystified') ? 'text-primary-600 bg-primary-50' : 'text-gray-700' }}">
                            <span class="font-medium">AI Demystified</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Etica e bias algoritmici</span>
                        </a>
                        <a href="{{ route('second-brain') }}" class="block px-4 py-2.5 text-sm hover:bg-primary-50 hover:text-primary-600 transition-colors {{ request()->is('second-brain') ? 'text-primary-600 bg-primary-50' : 'text-gray-700' }}">
                            <span class="font-medium">Second Brain</span>
                            <span class="block text-xs text-gray-400 mt-0.5">PKM con Obsidian</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('risorse') }}"
                   class="px-3 py-2 text-sm font-medium rounded-md transition-colors
                          {{ request()->is('risorse') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
                    Risorse
                </a>

                <a href="{{ route('setm-lab') }}"
                   class="px-3 py-2 text-sm font-medium rounded-md transition-colors
                          {{ request()->is('setm-lab') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }}">
                    SETM Lab
                </a>

                <a href="{{ route('contatti') }}"
                   class="ml-2 inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                    Contatti
                </a>
            </nav>

            {{-- Hamburger --}}
            <button @click="mobileOpen = !mobileOpen" class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-primary-600 hover:bg-gray-100 transition-colors" aria-label="Menu">
                <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile nav --}}
    <div x-show="mobileOpen" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
         class="lg:hidden bg-white border-t border-gray-100">
        <nav class="px-4 py-3 space-y-1">
            <a href="{{ route('home') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('/') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:bg-gray-50' }}">Home</a>

            <div class="px-3 py-2">
                <p class="text-xs font-bold uppercase text-gray-400 tracking-wider mb-2">Programmi</p>
                <a href="{{ route('consilium') }}" class="block py-1.5 text-sm {{ request()->is('consilium') ? 'text-primary-600 font-medium' : 'text-gray-700' }}">Interferenza</a>
                <a href="{{ route('initium') }}" class="block py-1.5 text-sm {{ request()->is('initium') ? 'text-primary-600 font-medium' : 'text-gray-700' }}">Segnale</a>
                <a href="{{ route('structura') }}" class="block py-1.5 text-sm {{ request()->is('structura') ? 'text-primary-600 font-medium' : 'text-gray-700' }}">Circuito</a>
            </div>

            <div class="px-3 py-2">
                <p class="text-xs font-bold uppercase text-gray-400 tracking-wider mb-2">Pro Scholaris</p>
                <a href="{{ route('ai-demystified') }}" class="block py-1.5 text-sm {{ request()->is('ai-demystified') ? 'text-primary-600 font-medium' : 'text-gray-700' }}">AI Demystified</a>
                <a href="{{ route('second-brain') }}" class="block py-1.5 text-sm {{ request()->is('second-brain') ? 'text-primary-600 font-medium' : 'text-gray-700' }}">Second Brain</a>
            </div>

            <a href="{{ route('risorse') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('risorse') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:bg-gray-50' }}">Risorse</a>
            <a href="{{ route('setm-lab') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('setm-lab') ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:bg-gray-50' }}">SETM Lab</a>
            <a href="{{ route('contatti') }}" class="block px-3 py-2 mt-2 rounded-lg text-center text-base font-semibold text-white bg-primary-600 hover:bg-primary-700 transition-colors">Contatti</a>
        </nav>
    </div>
</header>

<div class="h-16"></div>
