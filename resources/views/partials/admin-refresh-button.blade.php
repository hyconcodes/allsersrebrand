@php $action = $action ?? 'refresh'; @endphp
<!-- Refresh: text button on desktop, icon-only on mobile -->
<button wire:click="{{ $action }}" wire:loading.attr="disabled"
    class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-xs font-bold uppercase tracking-wide hover:bg-zinc-800 dark:hover:bg-zinc-100 transition-colors disabled:opacity-50">
    <flux:icon name="arrow-path" class="size-4" wire:loading.remove wire:target="{{ $action }}" />
    <span wire:loading wire:target="{{ $action }}" class="size-4 border-2 border-white/30 dark:border-zinc-900/20 border-t-white dark:border-t-zinc-900 rounded-full animate-spin"></span>
    <span wire:loading.remove wire:target="{{ $action }}">{{ __('Refresh') }}</span>
    <span wire:loading wire:target="{{ $action }}">{{ __('Refreshing...') }}</span>
</button>
<button wire:click="{{ $action }}" wire:loading.attr="disabled" aria-label="{{ __('Refresh') }}"
    class="sm:hidden size-10 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 flex items-center justify-center hover:bg-zinc-800 dark:hover:bg-zinc-100 transition-colors disabled:opacity-50 shrink-0">
    <flux:icon name="arrow-path" class="size-5" wire:loading.remove wire:target="{{ $action }}" />
    <span wire:loading wire:target="{{ $action }}" class="size-5 border-2 border-white/30 dark:border-zinc-900/20 border-t-white dark:border-t-zinc-900 rounded-full animate-spin"></span>
</button>
