<div>
    @if($subscribed)
        <div class="flex items-center justify-center gap-2 py-3 px-4 bg-primary-50 border border-primary-200 rounded-lg">
            <svg class="h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span class="text-sm font-medium text-primary-700">Iscrizione confermata!</span>
        </div>
    @else
        <form wire:submit.prevent="subscribe" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input wire:model="email" type="email" placeholder="La tua email" class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-semibold text-gray-900 bg-accent-500 rounded-lg hover:bg-accent-400 disabled:opacity-50 transition-colors whitespace-nowrap">
                <span wire:loading.remove>Iscriviti</span>
                <span wire:loading>...</span>
            </button>
        </form>
    @endif
</div>
