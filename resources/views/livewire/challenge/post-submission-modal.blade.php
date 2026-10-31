
<?php

use App\Models\Challenge;
use App\Models\Post;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    public ?int $challengeId = null;

    public string $challengeTitle = '';

    public string $hashtag = '';

    public string $content = '';

    public array $images = [];

    public $video = null;

    #[On('open-challenge-post-modal')]
    public function openModal(int $challengeId, ?string $hashtag = null): void
    {
        $challenge = Challenge::findOrFail($challengeId);

        if (! auth()->check() || ! $challenge->isParticipant(auth()->user())) {
            $this->dispatch('toast', type: 'error', title: 'Not a Participant', message: 'Join this challenge before submitting a post.');

            return;
        }

        if ($challenge->hasEnded() && ! $challenge->joinedBeforeEnd(auth()->user())) {
            $this->dispatch('toast', type: 'error', title: 'Challenge Unavailable', message: 'Only members who joined before the challenge ended can still post.');

            return;
        }

        $this->challengeId = $challenge->id;
        $this->challengeTitle = $challenge->title;
        $this->hashtag = $challenge->hashtag;
        $this->showModal = true;
    }

    public function submit(): void
    {
        $challenge = Challenge::findOrFail($this->challengeId);

        abort_unless(
            auth()->check() && $challenge->canPost(auth()->user()),
            403,
        );

        if (empty(trim($this->content)) && empty($this->images) && ! $this->video) {
            $this->addError('content', 'Add text, an image, or a video to your submission.');

            return;
        }

        try {
            $validated = $this->validate([
                'content' => 'nullable|string|max:1000',
                'images' => 'nullable|array|max:4',
                'images.*' => ['image', 'max:30720'],
                'video' => ['nullable', 'file', 'mimes:mp4,mov,avi,wmv', 'max:30720'],
            ], [
                'images.*.max' => __('Each image must be 30 MB or less.'),
                'video.max' => __('The video must be 30 MB or less.'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->toastSubmissionOversize($e);

            throw $e;
        }

        $content = trim($validated['content'] ?? '');
        $hashtagPattern = '/(^|\s)#' . preg_quote($challenge->hashtag, '/') . '(?=\s|$)/i';

        if (! preg_match($hashtagPattern, $content)) {
            $content = trim($content . ' #' . $challenge->hashtag);
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'challenge_id' => $challenge->id,
            'content' => $content,
        ]);

        if (! empty($validated['images'])) {
            $imagePaths = [];

            foreach ($validated['images'] as $image) {
                $imagePaths[] = $image->store('posts/images', 'public');
            }

            $post->images = implode(',', $imagePaths);
        }

        if ($this->video) {
            $post->video = $this->video->store('posts/videos', 'public');
        }

        $post->save();

        $this->reset(['content', 'images', 'video']);
        $this->showModal = false;
        $this->dispatch('post-created');
        $this->dispatch('toast', type: 'success', title: 'Submission Posted', message: 'Your work has been submitted to the challenge.');
    }

    /**
     * Warn with a toast when a submission attachment exceeds the 30MB cap.
     */
    protected function toastSubmissionOversize(\Illuminate\Validation\ValidationException $e): void
    {
        foreach ($e->validator->failed() as $field => $rules) {
            if ((str_starts_with($field, 'images') || $field === 'video') && isset($rules['Max'])) {
                $this->dispatch('toast', type: 'warning', title: 'File Too Large', message: 'Challenge images and videos must be 30MB or less.');

                return;
            }
        }
    }

    public function removeImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function removeVideo(): void
    {
        $this->video = null;
    }
}; ?>

<div>
    <flux:modal wire:model="showModal" class="w-full sm:max-w-xl">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Submit to :challenge', ['challenge' => $challengeTitle]) }}</flux:heading>
                <flux:subheading>{{ __('Your post will be added to the challenge feed with #:hashtag.', ['hashtag' => $hashtag]) }}</flux:subheading>
            </div>

            <form wire:submit="submit" class="space-y-4">
                <textarea wire:model="content" placeholder="{{ __('Show the community what you created...') }}"
                    class="w-full min-h-28 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:border-[var(--color-brand-purple)] focus:outline-none focus:ring-1 focus:ring-[var(--color-brand-purple)]"
                    maxlength="1000"></textarea>
                @error('content')
                    <span class="block text-xs text-red-500">{{ $message }}</span>
                @enderror

                @if (!empty($images))
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($images as $index => $image)
                            <div class="relative">
                                <img src="{{ $image->temporaryUrl() }}" class="h-24 w-full rounded-lg object-cover">
                                <button type="button" wire:click="removeImage({{ $index }})"
                                    class="absolute right-1 top-1 rounded-full bg-red-500 p-1 text-white">
                                    <flux:icon name="x-mark" class="size-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($video)
                    <div class="relative">
                        <video src="{{ $video->temporaryUrl() }}" class="h-32 w-full rounded-lg object-cover" controls></video>
                        <button type="button" wire:click="removeVideo"
                            class="absolute right-1 top-1 rounded-full bg-red-500 p-1 text-white">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-zinc-500 hover:text-[var(--color-brand-purple)]">
                            <flux:icon name="photo" class="size-4" />
                            {{ __('Photos') }}
                            <input type="file" wire:model="images" multiple accept="image/*" class="hidden"
                                @disabled(! empty($video)) x-on:change="guardFileSize($el, 30720)">
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-zinc-500 hover:text-[var(--color-brand-purple)]">
                            <flux:icon name="video-camera" class="size-4" />
                            {{ __('Video') }}
                            <input type="file" wire:model="video" accept="video/*" class="hidden"
                                @disabled(! empty($images)) x-on:change="guardFileSize($el, 30720)">
                        </label>
                    </div>
                    <button type="submit"
                        class="rounded-full bg-[var(--color-brand-purple)] px-5 py-2 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">{{ __('Submit Post') }}</span>
                        <span wire:loading wire:target="submit">{{ __('Submitting...') }}</span>
                    </button>
                </div>
                <div wire:loading wire:target="images,video" class="text-xs text-zinc-500">{{ __('Uploading...') }}</div>
                @error('images.*')
                    <span class="block text-xs text-red-500">{{ $message }}</span>
                @enderror
                @error('video')
                    <span class="block text-xs text-red-500">{{ $message }}</span>
                @enderror
            </form>
        </div>
    </flux:modal>
</div>
