<?php

use App\Models\Post;
use App\Models\User;
use App\Notifications\UserTagged;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Traits\HandlesPostActions;

new class extends Component {
    use WithFileUploads, HandlesPostActions;

    public $showModal = false;

    #[Validate('nullable|string|max:1000')]
    public $content = '';

    #[Validate('nullable|array|max:4')]
    public $images = [];

    #[Validate('nullable|file|mimes:mp4,mov,avi,wmv|max:10240')]
    public $video = null;

    #[Validate('nullable|numeric|min:0')]
    public $price_min = null;

    #[Validate('nullable|numeric|min:0|gte:price_min')]
    public $price_max = null;

    public $showPrice = false;

    #[On('open-create-post')]
    public function openModal()
    {
        if (!auth()->user()->isArtisan()) {
            $this->dispatch('toast', type: 'error', title: 'Artisans Only', message: 'Only artisans can create posts.');
            return;
        }
        $this->showModal = true;
    }

    public function createPost()
    {
        if (!auth()->user()->isArtisan()) {
            $this->addError('permission', 'Only artisans can create posts.');
            return;
        }

        if (empty($this->content) && empty($this->images) && empty($this->video)) {
            $this->addError('content', 'Please add content, images, or a video.');
            return;
        }

        try {
            $validated = $this->validate([
                'content' => 'nullable|string|max:1000',
                'images' => 'nullable|array|max:4',
                'images.*' => 'nullable|image|max:10240',
                'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:10240',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->validator->errors()->getMessages() as $field => $messages) {
                if (str_contains($field, 'images') || str_contains($field, 'video')) {
                    if (str_contains(implode(' ', $messages), 'kilobytes') || str_contains(implode(' ', $messages), 'large')) {
                        $this->dispatch('toast', type: 'error', title: 'File Too Large', message: 'Images and videos must be less than 10MB.');
                        break;
                    }
                }
            }
            throw $e;
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'content' => $this->content,
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
        ]);

        if (!empty($this->images)) {
            $imagePaths = [];
            foreach ($this->images as $image) {
                $imagePaths[] = $image->store('posts/images', 'public');
            }
            $post->images = implode(',', $imagePaths);
        }

        if ($this->video) {
            $post->video = $this->video->store('posts/videos', 'public');
        }

        $post->save();

        $this->notifyMentionedUsers($post);

        $this->reset(['content', 'images', 'video', 'price_min', 'price_max', 'showPrice']);

        $this->showModal = false;

        $this->dispatch('post-created');
    }

    public function removeImage($index)
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function removeVideo()
    {
        $this->video = null;
    }

    protected function notifyMentionedUsers(Post $post)
    {
        if (empty($post->content)) {
            return;
        }

        preg_match_all('/@(\w+)/', $post->content, $matches);
        $usernames = array_unique($matches[1] ?? []);

        foreach ($usernames as $username) {
            $mentioned = User::where('username', $username)->first();
            if ($mentioned && $mentioned->id !== auth()->id()) {
                $mentioned->notify(new UserTagged($post));
            }
        }
    }
};

?>

<div x-data x-on:open-create-post.window="$wire.call('openModal')">
    <flux:modal wire:model="showModal" class="w-full sm:max-w-xl">
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="shrink-0 size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 font-bold text-sm overflow-hidden">
                    @if (auth()->user()->profile_picture_url)
                        <img loading="lazy" src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                    @else
                        {{ auth()->user()->initials() }}
                    @endif
                </div>
                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>
            </div>

            <form wire:submit="createPost" class="space-y-3">
                <textarea wire:model="content" placeholder="{{ __('What are you working on?') }}"
                    class="w-full bg-transparent border-none outline-none focus:outline-none focus:ring-0 rounded-none px-0 py-1 text-sm transition-all resize-none placeholder:text-zinc-400 dark:placeholder:text-zinc-600 min-h-[120px]"
                    autofocus></textarea>

                @error('content')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror
                @error('permission')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror

                @if (!empty($images))
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($images as $index => $image)
                            <div class="relative group">
                                <img loading="lazy" src="{{ $image->temporaryUrl() }}" class="w-full h-24 object-cover rounded-lg">
                                <button type="button" wire:click="removeImage({{ $index }})"
                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                    <flux:icon name="x-mark" class="size-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($video)
                    <div class="relative group">
                        <video src="{{ $video->temporaryUrl() }}" class="w-full h-32 object-cover rounded-lg" controls></video>
                        <button type="button" wire:click="removeVideo"
                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                @endif

                <div x-data="{ showPrice: false }" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-1.5 text-zinc-500 hover:text-[var(--color-brand-purple)] text-xs font-medium transition-colors cursor-pointer">
                                <flux:icon name="photo" class="size-4" />
                                <span class="hidden sm:inline">{{ __('Photo') }}</span>
                                <input type="file" wire:model="images" multiple accept="image/*" class="hidden" :disabled="video != null">
                            </label>
                            <label class="flex items-center gap-1.5 text-zinc-500 hover:text-[var(--color-brand-purple)] text-xs font-medium transition-colors cursor-pointer">
                                <flux:icon name="video-camera" class="size-4" />
                                <span class="hidden sm:inline">{{ __('Video') }}</span>
                                <input type="file" wire:model="video" accept="video/*" class="hidden" :disabled="images.length > 0">
                            </label>
                            <button type="button" @click="showPrice = !showPrice"
                                class="flex items-center gap-1.5 text-zinc-500 hover:text-[var(--color-brand-purple)] text-xs font-medium transition-colors">
                                <flux:icon name="currency-dollar" class="size-4" />
                            </button>
                        </div>
                        <button type="submit"
                            class="bg-[var(--color-brand-purple)] text-white px-4 py-1.5 rounded-full text-sm font-semibold hover:opacity-90 transition-opacity disabled:opacity-50 whitespace-nowrap"
                            wire:loading.attr="disabled" wire:target="createPost">
                            <span wire:loading.remove wire:target="createPost">{{ __('Post') }}</span>
                            <span wire:loading wire:target="createPost">{{ __('Posting') }}</span>
                        </button>
                    </div>

                    <div x-show="showPrice" x-collapse class="grid grid-cols-2 gap-2">
                        <div>
                            <input type="number" wire:model="price_min" min="0" step="0.01"
                                class="w-full px-2 py-1.5 text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg focus:ring-1 focus:ring-[var(--color-brand-purple)] focus:border-transparent bg-transparent"
                                placeholder="{{ __('Min price') }}">
                        </div>
                        <div>
                            <input type="number" wire:model="price_max" min="0" step="0.01"
                                class="w-full px-2 py-1.5 text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg focus:ring-1 focus:ring-[var(--color-brand-purple)] focus:border-transparent bg-transparent"
                                placeholder="{{ __('Max price') }}">
                        </div>
                    </div>
                </div>

                <div wire:loading wire:target="images,video" class="text-xs text-zinc-500">
                    {{ __('Uploading...') }}
                </div>
            </form>
        </div>
    </flux:modal>
</div>
