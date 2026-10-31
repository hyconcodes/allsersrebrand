{{-- "+" attachment picker for comment composers: asks image or video (30MB max, toast on oversize) --}}
@props(['suffix' => 'single'])
<div x-data="{ attachOpen: false }" class="relative shrink-0" @click.stop>
    <button type="button" @click="attachOpen = !attachOpen" title="{{ __('Attach image or video') }}"
        class="flex items-center justify-center size-7 rounded-full text-zinc-400 hover:text-[var(--color-brand-purple)] hover:bg-purple-500/10 transition-colors">
        <flux:icon name="plus" class="size-4" />
    </button>
    <div x-show="attachOpen" x-transition @click.outside="attachOpen = false"
        class="absolute bottom-9 left-0 z-50 w-40 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl">
        <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800">
            <flux:icon name="photo" class="size-4 text-zinc-400" />
            {{ __('Image') }}
            <input id="ci-{{ $suffix }}" type="file" wire:model="commentImages" multiple accept="image/*" class="hidden"
                x-on:change="guardFileSize($el, 30720)">
        </label>
        <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800">
            <flux:icon name="video-camera" class="size-4 text-zinc-400" />
            {{ __('Video') }}
            <input id="cv-{{ $suffix }}" type="file" wire:model="commentVideo" accept="video/*" class="hidden"
                x-on:change="guardFileSize($el, 30720)">
        </label>
    </div>
</div>
