<?php
use Livewire\Volt\Component;

new class extends Component {
    // No specific logic needed, purely navigational
}; ?>

<div class="flex items-center gap-6 mb-1 border-b border-zinc-200/50 dark:border-zinc-800/50 sticky top-0 z-30 bg-white dark:bg-zinc-950">
    <a href="{{ route('dashboard', ['tab' => 'for-you']) }}"
        class="relative py-3 text-sm font-bold transition-all {{ request('tab', 'for-you') === 'for-you' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
        {{ __('For you') }}
        @if (request('tab', 'for-you') === 'for-you')
            <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full">
            </div>
        @endif
    </a>
    <a href="{{ route('dashboard', ['tab' => 'local']) }}"
        class="relative py-3 text-sm font-bold transition-all {{ request('tab') === 'local' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
        {{ __('Local') }}
        @if (request('tab') === 'local')
            <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full">
            </div>
        @endif
    </a>
</div>
