<div>
    <form wire:submit.prevent="submit" class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 space-y-6">
        {{-- Honeypot: invisibile a utenti, visibile e tentante per i bot --}}
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label for="lm-website">Sito web (lascia vuoto)</label>
            <input wire:model="website" type="text" id="lm-website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="lm-name" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
            <input wire:model="name" type="text" id="lm-name"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal focus:border-teal outline-none"
                   placeholder="Il tuo nome">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="lm-email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
            <input wire:model="email" type="email" id="lm-email"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal focus:border-teal outline-none"
                   placeholder="la.tua@email.it">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="lm-company" class="block text-sm font-medium text-gray-700 mb-1">Azienda *</label>
            <input wire:model="company" type="text" id="lm-company"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal focus:border-teal outline-none"
                   placeholder="Nome azienda">
            @error('company') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input wire:model="privacy_accepted" type="checkbox"
                       class="mt-0.5 h-5 w-5 rounded border-gray-300 text-teal focus:ring-teal">
                <span class="text-sm text-gray-600">
                    Ho letto e accetto la
                    <a href="{{ route('privacy') }}" target="_blank" class="text-teal-dark underline hover:text-teal">Privacy Policy</a>
                    e acconsento a ricevere il PDF e comunicazioni informative da The Glitch World. *
                </span>
            </label>
            @error('privacy_accepted') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full px-6 py-3 text-sm font-semibold text-white bg-teal hover:bg-teal-dark rounded-lg disabled:opacity-50 transition-colors shadow-lg"
                style="box-shadow: 0 10px 25px -5px rgba(85,177,174,0.35);">
            <span wire:loading.remove>Scarica la mappa</span>
            <span wire:loading class="inline-flex items-center gap-2">
                <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Invio...
            </span>
        </button>

        <p class="text-xs text-gray-500 text-center">
            Riceverai il PDF via email entro pochi minuti.
        </p>
    </form>
</div>
