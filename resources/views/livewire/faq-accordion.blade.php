<div class="space-y-3">
    @foreach($faqs as $index => $faq)
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button wire:click="toggle({{ $index }})" class="w-full flex items-center justify-between px-6 py-4 text-left transition-colors {{ $openIndex === $index ? 'bg-primary-50' : 'bg-white hover:bg-gray-50' }}">
                <span class="text-sm font-semibold text-gray-900 pr-4">{{ $faq['question'] }}</span>
                <svg class="h-5 w-5 text-primary-600 flex-shrink-0 transition-transform duration-200 {{ $openIndex === $index ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            @if($openIndex === $index)
                <div class="px-6 py-4 bg-white border-t border-gray-200">
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $faq['answer'] }}</p>
                </div>
            @endif
        </div>
    @endforeach
</div>
