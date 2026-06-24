<div>
    @if($sent)
        <div class="bg-primary-50 border border-primary-200 rounded-xl p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-primary-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="text-lg font-semibold text-primary-700">Messaggio inviato!</h3>
            <p class="mt-1 text-sm text-gray-600">Ti risponderemo entro 24 ore.</p>
        </div>
    @else
        <form wire:submit.prevent="submit" class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                <input wire:model="name" type="text" id="name" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Il tuo nome">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input wire:model="email" type="email" id="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="la.tua@email.it">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                    <input wire:model="phone" type="tel" id="phone" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="+39 02 1234567">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="company" class="block text-sm font-medium text-gray-700 mb-1">Azienda</label>
                    <input wire:model="company" type="text" id="company" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Nome azienda">
                    @error('company') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Messaggio *</label>
                <textarea wire:model="message" id="message" rows="5" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-y" placeholder="Raccontaci la tua esigenza..."></textarea>
                @error('message') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input wire:model="privacy_accepted" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-600">Ho letto e accetto la <a href="{{ route('privacy') }}" target="_blank" class="text-primary-600 underline hover:text-primary-700">Privacy Policy</a> *</span>
                </label>
                @error('privacy_accepted') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="w-full px-6 py-3 text-sm font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 transition-colors shadow-lg shadow-primary-600/25">
                <span wire:loading.remove>Invia richiesta</span>
                <span wire:loading class="inline-flex items-center gap-2"><svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Invio...</span>
            </button>
        </form>
    @endif
</div>
