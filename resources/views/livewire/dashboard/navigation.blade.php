<?php
use Livewire\Volt\Component;

new class extends Component {
    // No specific logic needed, purely navigational
}; ?>

<div
    class="flex items-center gap-6 mb-6 backdrop-blur-md sticky top-0 z-30">
    <a href="{{ route('dashboard', ['tab' => 'for-you']) }}" wire:navigate
        class="relative py-4 text-sm font-black uppercase tracking-widest transition-all {{ request('tab', 'for-you') === 'for-you' ? 'text-[var(--color-brand-purple)]' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
        {{ __('For You') }}
        @if (request('tab', 'for-you') === 'for-you')
            <div
                class="absolute bottom-0 left-0 right-0 h-1 bg-[var(--color-brand-purple)] rounded-t-full shadow-[0_-2px_10px_rgba(109,40,217,0.3)]">
            </div>
        @endif
    </a>
    <a href="{{ route('dashboard', ['tab' => 'local']) }}" wire:navigate
        class="relative py-4 text-sm font-black uppercase tracking-widest transition-all {{ request('tab') === 'local' ? 'text-[var(--color-brand-purple)]' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
        {{ __('Local') }}
        @if (request('tab') === 'local')
            <div
                class="absolute bottom-0 left-0 right-0 h-1 bg-[var(--color-brand-purple)] rounded-t-full shadow-[0_-2px_10px_rgba(109,40,217,0.3)]">
            </div>
        @endif
    </a>

    <div class="ml-auto flex items-center gap-2">
        <a href="{{ route('finder') }}" wire:navigate
            class="size-10 flex items-center justify-center rounded-full text-green-500 hover:text-green-600 transition-all bg-white dark:bg-zinc-800 shadow-sm border border-zinc-200 dark:border-zinc-700">
            <flux:icon name="globe-americas" variant="solid" class="size-6 animate-spin [animation-duration:3s]" />
        </a>
        <a href="{{ route('menu') }}" wire:navigate
            class="size-10 flex items-center justify-center rounded-full text-zinc-500 hover:text-zinc-600 transition-all bg-white dark:bg-zinc-800 shadow-sm border border-zinc-200 dark:border-zinc-700">
            <flux:icon name="cog-6-tooth" variant="solid" class="size-6" />
        </a>
    </div>
</div>
