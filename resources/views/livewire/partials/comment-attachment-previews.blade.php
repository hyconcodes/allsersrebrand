{{-- Selected comment attachment previews (images + video) --}}
@if (! empty($commentImages) || $commentVideo)
    <div class="flex flex-wrap items-center gap-2 px-1 pb-1.5" @click.stop>
        @foreach ($commentImages as $index => $image)
            <div class="relative">
                <img src="{{ $image->temporaryUrl() }}" class="h-14 w-14 rounded-lg object-cover border border-zinc-200 dark:border-zinc-800">
                <button type="button" wire:click="removeCommentImage({{ $index }})"
                    class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-red-500 text-white shadow">
                    <flux:icon name="x-mark" class="size-3" />
                </button>
            </div>
        @endforeach
        @if ($commentVideo)
            <div class="relative flex items-center gap-1.5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-900 px-2 py-1">
                <flux:icon name="video-camera" class="size-4 text-zinc-500" />
                <span class="max-w-[120px] truncate text-[11px] text-zinc-500">{{ __('Video attached') }}</span>
                <button type="button" wire:click="removeCommentVideo" class="text-red-500 hover:text-red-400">
                    <flux:icon name="x-mark" class="size-3.5" />
                </button>
            </div>
        @endif
    </div>
    <div wire:loading wire:target="commentImages,commentVideo" class="px-1 pb-1.5 text-[11px] text-zinc-500">{{ __('Uploading...') }}</div>
    @error('commentImages.*')
        <span class="block px-1 pb-1.5 text-[11px] text-red-500">{{ $message }}</span>
    @enderror
    @error('commentVideo')
        <span class="block px-1 pb-1.5 text-[11px] text-red-500">{{ $message }}</span>
    @enderror
@endif
