<!-- Repost Modal -->
<flux:modal name="repost-modal" wire:model="showRepostModal" class="sm:max-w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Repost Work') }}</flux:heading>
            <flux:subheading>{{ __('Repost this work and add your own progress or feedback.') }}</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:textarea wire:model="repostContent" placeholder="{{ __('Add your thoughts or progress...') }}"
                rows="3" />

            <div class="flex items-center gap-4">
                <label
                    class="flex items-center gap-2 text-zinc-500 hover:text-[var(--color-brand-purple)] text-sm font-medium transition-colors cursor-pointer">
                    <flux:icon name="photo" class="size-5" />
                    <span>{{ __('Add Image') }}</span>
                    <input type="file" wire:model="repostImage" accept="image/*" class="hidden">
                </label>
                <label
                    class="flex items-center gap-2 text-zinc-500 hover:text-[var(--color-brand-purple)] text-sm font-medium transition-colors cursor-pointer">
                    <flux:icon name="video-camera" class="size-5" />
                    <span>{{ __('Add Video') }}</span>
                    <input type="file" wire:model="repostVideo" accept="video/*" class="hidden">
                </label>
            </div>

            <div class="flex gap-4">
                @if ($repostImage)
                    <div class="relative group size-20 rounded-lg overflow-hidden border border-zinc-200">
                        <img src="{{ $repostImage->temporaryUrl() }}" class="size-full object-cover">
                        <button type="button" wire:click="$set('repostImage', null)"
                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                @endif

                @if ($repostVideo)
                    <div
                        class="relative group size-20 rounded-lg overflow-hidden border border-zinc-200 flex items-center justify-center bg-zinc-100">
                        <flux:icon name="video-camera" class="size-8 text-zinc-400" />
                        <button type="button" wire:click="$set('repostVideo', null)"
                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                @endif
            </div>

            <div wire:loading wire:target="repostImage,repostVideo" class="text-xs text-zinc-500 italic">
                {{ __('Uploading asset...') }}
            </div>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="createRepost" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="createRepost">{{ __('Repost') }}</span>
                <span wire:loading wire:target="createRepost" class="flex items-center gap-2">
                    {{ __('Publishing...') }}
                </span>
            </flux:button>
        </div>
    </div>
</flux:modal>

<!-- Report Post Modal -->
<flux:modal name="report-post-modal" wire:model="showReportModal" class="sm:max-w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Report Post</flux:heading>
            <flux:subheading>Help us understand what's wrong with this post</flux:subheading>
        </div>

        <div class="space-y-4">
            <div>
                <flux:label>Reason for Report *</flux:label>
                <flux:textarea wire:model="reportReason" rows="4"
                    placeholder="Please describe why you're reporting this post (minimum 10 characters)..." />
                @error('reportReason')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 text-sm text-zinc-600 dark:text-zinc-400">
                <p class="font-medium mb-2">Common reasons for reporting:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li>Spam or misleading content</li>
                    <li>Harassment or hate speech</li>
                    <li>Violence or dangerous content</li>
                    <li>Inappropriate or offensive material</li>
                    <li>Copyright infringement</li>
                </ul>
            </div>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="submitReport">Submit Report</flux:button>
        </div>
    </div>
</flux:modal>
