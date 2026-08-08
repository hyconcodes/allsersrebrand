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

    public $posts = [];

    #[Url]
    public $tab = 'for-you';

    public $page = 1;
    public $perPage = 10;
    public $hasMore = true;
    public $loadingMore = false;
    public $latestPostId = null;
    public $newPostsCount = 0;

    public function mount()
    {
        $this->loadPosts(true);
    }

    public function switchTab($tab)
    {
        $this->tab = $tab;
        $this->page = 1;
        $this->hasMore = true;
        $this->newPostsCount = 0;
        $this->latestPostId = null;
        $this->loadPosts(true);
    }

    public function loadMore()
    {
        if ($this->loadingMore || !$this->hasMore) {
            return;
        }

        $this->loadingMore = true;
        $this->page++;
        $this->loadPosts();
        $this->loadingMore = false;
    }

    public function loadPosts($reset = false)
    {
        if ($reset) {
            $this->page = 1;
            $this->hasMore = true;
            $this->posts = [];
            $this->newPostsCount = 0;
        }

        $query = Post::query()
            ->with([
                'user',
                'repostOf.user',
                'comments' => function ($q) {
                    $q->latest()->limit(2)->with('user');
                },
                'likes' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
                'bookmarks' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
            ])
            ->withCount(['likes', 'allComments']);

        if ($this->tab === 'local' && auth()->check() && auth()->user()->latitude && auth()->user()->longitude) {
            $lat = auth()->user()->latitude;
            $lng = auth()->user()->longitude;
            $radiusKm = 50;

            // Bounding box pre-filter to enable index usage
            $latDelta = $radiusKm / 111.32;
            $lngDelta = $radiusKm / (111.32 * cos(deg2rad($lat)));
            $minLat = $lat - $latDelta;
            $maxLat = $lat + $latDelta;
            $minLng = $lng - $lngDelta;
            $maxLng = $lng + $lngDelta;

            $query
                ->join('users', 'posts.user_id', '=', 'users.id')
                ->select('posts.*')
                ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(users.latitude)) * cos(radians(users.longitude) - radians(?)) + sin(radians(?)) * sin(radians(users.latitude)))) AS distance', [$lat, $lng, $lat])
                ->where('posts.user_id', '!=', auth()->id())
                ->where('users.latitude', '>=', $minLat)
                ->where('users.latitude', '<=', $maxLat)
                ->where('users.longitude', '>=', $minLng)
                ->where('users.longitude', '<=', $maxLng)
                ->whereIn('posts.id', function ($q) {
                    $q->selectRaw('max(id)')->from('posts')->groupBy('user_id');
                })
                ->whereNotNull('users.latitude')
                ->having('distance', '<', $radiusKm)
                ->orderBy('distance', 'asc')
                ->orderBy('posts.created_at', 'desc')
                ->orderBy('posts.id', 'desc');
        } else {
            $query->orderBy('posts.created_at', 'desc')->orderBy('posts.id', 'desc');
        }

        $newPosts = $query
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get();

        if ($newPosts->count() < $this->perPage) {
            $this->hasMore = false;
        }

        if ($reset) {
            $this->posts = $newPosts->all();
            if ($newPosts->isNotEmpty()) {
                $this->latestPostId = $newPosts->first()->id;
            }
        } else {
            $this->posts = collect($this->posts)->concat($newPosts)->unique('id')->all();
        }
    }

    #[Livewire\Attributes\On('comment-added')]
    #[Livewire\Attributes\On('post-liked')]
    #[Livewire\Attributes\On('post-bookmarked')]
    #[Livewire\Attributes\On('post-deleted')]
    #[Livewire\Attributes\On('post-created')]
    public function refreshFeed()
    {
        $this->loadPosts(true);
    }

    public function checkNewPosts()
    {
        $currentIds = collect($this->posts)->pluck('id');
        if ($currentIds->isNotEmpty()) {
            $existingCount = Post::whereIn('id', $currentIds)->count();
            if ($existingCount < count($this->posts)) {
                $this->loadPosts(true);
                return;
            }
        }

        if (!$this->latestPostId) {
            return;
        }

        $query = Post::where('id', '>', $this->latestPostId);

        if ($this->tab === 'local') {
            $query->where('user_id', '!=', auth()->id());
        }

        $this->newPostsCount = $query->exists() ? $query->count() : 0;
    }

    public function loadNewPosts()
    {
        $this->loadPosts(true);
    }

    public function createPost()
    {
        // Check if user is an artisan
        if (!auth()->user()->isArtisan()) {
            $this->addError('permission', 'Only artisans can create posts.');
            return;
        }

        // Validate that at least one field is filled
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

        // Handle images - store as comma-separated string
        if (!empty($this->images)) {
            $imagePaths = [];
            foreach ($this->images as $image) {
                $imagePaths[] = $image->store('posts/images', 'public');
            }
            $post->images = implode(',', $imagePaths);
        }

        // Handle video
        if ($this->video) {
            $post->video = $this->video->store('posts/videos', 'public');
        }

        $post->save();

        $this->notifyMentionedUsers($post);

        // Reset form
        $this->reset(['content', 'images', 'video', 'price_min', 'price_max']);

        // Refresh feed completely to show new post at top
        $this->page = 1;
        $this->posts = [];
        $this->hasMore = true;
        $this->loadPosts(true);

        // Dispatch success event
        $this->dispatch('post-created');
    }

    protected function notifyMentionedUsers(Post $post)
    {
        if (empty($post->content)) {
            return;
        }

        preg_match_all('/(?:^|\s)@([a-zA-Z0-9_]+)/', $post->content, $matches);
        $usernames = array_unique($matches[1]);

        if (empty($usernames)) {
            return;
        }

        $users = User::whereIn('username', $usernames)->get();

        foreach ($users as $user) {
            if ($user->id !== auth()->id()) {
                $user->notify(new \App\Notifications\UserTagged($post, auth()->user()));
            }
        }
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
}; ?>

{{-- Note: no divide-y — each post-item already has its own border-b --}}
<div x-data="{
    insertEmoji(emoji) {
        const el = $wire.$el.querySelector('textarea');
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const text = $wire.content;
        $wire.content = text.substring(0, start) + emoji + text.substring(end);
        el.focus();
        setTimeout(() => el.setSelectionRange(start + emoji.length, start + emoji.length), 0);
    }
}">
    <div wire:poll.10s.visible="checkNewPosts"></div>

    <x-top-bar>
        <x-slot:left>
            <div class="flex items-center gap-6">
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
        </x-slot:left>
    </x-top-bar>

    @if ($newPostsCount > 0)
        <div class="px-4 py-2 border-b border-[var(--color-brand-purple)]/20 bg-[var(--color-brand-purple)]/[0.03]">
            <button wire:click="loadNewPosts"
                class="w-full text-center text-sm font-semibold text-[var(--color-brand-purple)] hover:underline flex items-center justify-center gap-1.5 transition-colors">
                <flux:icon name="arrow-up" class="size-4" />
                <span>{{ $newPostsCount }} {{ __('new posts') }}</span>
            </button>
        </div>
    @endif

    @auth
        @if (auth()->user()->isArtisan())
            <div class="px-4 py-3 border-b border-zinc-200/50 dark:border-zinc-800/50">
                <form wire:submit="createPost">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 font-bold text-sm overflow-hidden">
                            @if (auth()->user()->profile_picture_url)
                                <img loading="lazy" src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                            @else
                                {{ auth()->user()->initials() }}
                            @endif
                        </div>
                        <div class="flex-1 min-w-0 space-y-2">
                            <textarea wire:model="content" placeholder="{{ __('Share your work...') }}"
                                class="w-full bg-transparent border-none rounded-none px-0 py-1 text-sm focus:ring-0 transition-all resize-none placeholder:text-zinc-400 dark:placeholder:text-zinc-600"
                                rows="1"></textarea>

                            <div class="flex flex-wrap gap-2">
                                @foreach (['🔥', '✨', '🛠️', '🎨', '🚀', '👏', '🙌'] as $emoji)
                                    <button type="button" @click="insertEmoji('{{ $emoji }}')"
                                        class="text-xs hover:scale-125 transition-transform p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ $emoji }}</button>
                                @endforeach
                            </div>

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

                            <div x-data="{ showPrice: false }" class="space-y-2 pt-1">
                                <div class="flex items-center justify-between gap-2">
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
                        </div>
                    </div>
                </form>
            </div>
        @endif
    @endauth

    <livewire:dashboard.pros-widget :in-feed="true" />

    <div>
        @forelse($posts as $post)
            <livewire:dashboard.post-item :post="$post" :wire:key="'post-'.$post->id" />
        @empty
            <div wire:loading.remove.delay.class="hidden"
                class="px-4 py-12 text-center text-zinc-500 text-sm">
                {{ __('No posts yet. Be the first to share!') }}
            </div>
            <div wire:loading.delay.longer class="px-4 space-y-0 opacity-50 pointer-events-none">
                @for ($i = 0; $i < 3; $i++)
                    <div class="px-4 py-3 border-b border-zinc-200/50 dark:border-zinc-800/50 animate-pulse">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="size-10 rounded-full bg-zinc-200 dark:bg-zinc-800"></div>
                            <div class="space-y-1.5 flex-1">
                                <div class="h-3 w-24 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
                                <div class="h-2.5 w-16 bg-zinc-100 dark:bg-zinc-700 rounded"></div>
                            </div>
                        </div>
                        <div class="space-y-2 pl-13">
                            <div class="h-2.5 w-full bg-zinc-100 dark:bg-zinc-800 rounded"></div>
                            <div class="h-2.5 w-4/5 bg-zinc-100 dark:bg-zinc-800 rounded"></div>
                            <div class="h-2.5 w-3/5 bg-zinc-100 dark:bg-zinc-800 rounded"></div>
                        </div>
                    </div>
                @endfor
            </div>
        @endforelse

        @if ($hasMore)
            <div class="px-4 py-8 text-center">
                <button wire:click="loadMore" wire:loading.remove wire:target="loadMore"
                    class="text-sm font-semibold text-[var(--color-brand-purple)] hover:underline transition-colors">
                    {{ __('Show more') }}
                </button>
                <div wire:loading wire:target="loadMore"
                    class="text-sm font-semibold text-zinc-400 animate-pulse">
                    {{ __('Loading...') }}
                </div>
            </div>
        @endif
    </div>

    @include('partials.post-modals')
</div>