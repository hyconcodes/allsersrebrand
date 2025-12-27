<?php

use App\Models\Post;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

new class extends Component {
    use WithFileUploads;

    #[Validate('nullable|string|max:1000')]
    public $content = '';

    #[Validate('nullable|array|max:4')]
    public $images = [];

    #[Validate('nullable|file|mimes:mp4,mov,avi,wmv|max:10240')]
    public $video = null;

    public $posts = [];

    public function mount()
    {
        $this->loadPosts();
    }

    public function loadPosts()
    {
        $this->posts = Post::with([
            'user',
            'likes' => function ($query) {
                $query->where('user_id', auth()->id());
            },
            'bookmarks' => function ($query) {
                $query->where('user_id', auth()->id());
            }
        ])
            ->withCount(['likes', 'allComments'])
            ->latest()
            ->take(20)
            ->get();
    }

    #[Livewire\Attributes\On('comment-added')]
    #[Livewire\Attributes\On('post-liked')]
    #[Livewire\Attributes\On('post-bookmarked')]
    public function refreshFeed()
    {
        $this->loadPosts();
    }

    public function toggleBookmark($postId)
    {
        $post = Post::find($postId);
        if (!$post)
            return;

        $user = auth()->user();
        $existingBookmark = $post->bookmarks()->where('user_id', $user->id)->first();

        if ($existingBookmark) {
            $existingBookmark->delete();
        } else {
            $post->bookmarks()->create(['user_id' => $user->id]);
        }

        $this->loadPosts();
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

        $validated = $this->validate([
            'content' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:4',
            'images.*' => 'nullable|image|max:2048',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:10240',
        ]);

        $post = Post::create([
            'user_id' => auth()->id(),
            'content' => $this->content,
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

        // Reset form
        $this->reset(['content', 'images', 'video']);

        // Reload posts
        $this->loadPosts();

        // Dispatch success event
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

    public function toggleLike($postId)
    {
        $post = Post::find($postId);

        if (!$post) {
            return;
        }

        $user = auth()->user();
        $existingLike = $post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            // Unlike
            $existingLike->delete();
        } else {
            // Like
            $post->likes()->create([
                'user_id' => $user->id,
            ]);

            // Notify post owner
            if ($post->user_id !== $user->id) {
                $post->user->notify(new \App\Notifications\PostLiked($post, $user));
            }
        }

        // Reload posts to update counts
        $this->loadPosts();
    }
}; ?>

<div class="space-y-6">
    @if(auth()->user()->isArtisan())
        <!-- Create Post Widget -->
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-4 shadow-sm border border-zinc-200 dark:border-zinc-800">
            <form wire:submit="createPost">
                <div class="flex items-start gap-4">
                    <div class="shrink-0">
                        <div
                            class="size-10 rounded-full bg-[var(--color-brand-purple)]/10 flex items-center justify-center text-[var(--color-brand-purple)] font-bold">
                            {{ auth()->user()->initials() }}
                        </div>
                    </div>
                    <div class="flex-1 space-y-3">
                        <textarea wire:model="content" placeholder="{{ __('Share your recent work...') }}"
                            class="w-full bg-zinc-50 dark:bg-zinc-800 border-none rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-brand-purple)]/20 transition-all resize-none"
                            rows="3"></textarea>

                        @error('content')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                        @error('permission')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror

                        <!-- Image Previews -->
                        @if(!empty($images))
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($images as $index => $image)
                                    <div class="relative group">
                                        <img src="{{ $image->temporaryUrl() }}" class="w-full h-32 object-cover rounded-lg">
                                        <button type="button" wire:click="removeImage({{ $index }})"
                                            class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <flux:icon name="x-mark" class="size-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Video Preview -->
                        @if($video)
                            <div class="relative group">
                                <video src="{{ $video->temporaryUrl() }}" class="w-full h-48 object-cover rounded-lg"
                                    controls></video>
                                <button type="button" wire:click="removeVideo"
                                    class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <flux:icon name="x-mark" class="size-4" />
                                </button>
                            </div>
                        @endif

                        <div class="flex items-center justify-between px-1">
                            <div class="flex items-center gap-4">
                                <label
                                    class="flex items-center gap-2 text-zinc-500 hover:text-[var(--color-brand-purple)] text-sm font-medium transition-colors cursor-pointer">
                                    <flux:icon name="photo" class="size-5" />
                                    <span>{{ __('Image') }}</span>
                                    <input type="file" wire:model="images" multiple accept="image/*" class="hidden"
                                        :disabled="video != null">
                                </label>
                                <label
                                    class="flex items-center gap-2 text-zinc-500 hover:text-[var(--color-brand-purple)] text-sm font-medium transition-colors cursor-pointer">
                                    <flux:icon name="video-camera" class="size-5" />
                                    <span>{{ __('Video') }}</span>
                                    <input type="file" wire:model="video" accept="video/*" class="hidden"
                                        :disabled="images.length > 0">
                                </label>
                            </div>
                            <button type="submit"
                                class="bg-[var(--color-brand-purple)] text-white px-6 py-1.5 rounded-full text-sm font-medium hover:bg-[var(--color-brand-purple)]/90 transition-colors shadow-lg shadow-purple-500/20 disabled:opacity-50"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>{{ __('Post') }}</span>
                                <span wire:loading>{{ __('Posting...') }}</span>
                            </button>
                        </div>

                        <div wire:loading wire:target="images,video" class="text-xs text-zinc-500">
                            {{ __('Uploading...') }}
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @endif

    <!-- Feed Posts -->
    <div class="space-y-6">
        @forelse($posts as $post)
            <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <div @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })" class="cursor-pointer">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="size-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-sm">
                                {{ $post->user->initials() }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ $post->user->name }}</h3>
                                    <span
                                        class="bg-purple-100 text-[var(--color-brand-purple)] text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">
                                        {{ $post->user->work ?? __('Artisan') }}
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <button class="text-zinc-400 hover:text-zinc-600">
                            <flux:icon name="ellipsis-horizontal" class="size-5" />
                        </button>
                    </div>

                    @if($post->content)
                        <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed">
                            {{ $post->content }}
                        </p>
                    @endif

                    <!-- Images -->
                    @if($post->images)
                        @php
                            $imageArray = array_filter(explode(',', $post->images));
                        @endphp
                        @if(count($imageArray) > 0)
                            @if(count($imageArray) === 1)
                                {{-- Single image --}}
                                <div class="mb-4 rounded-xl overflow-hidden h-80 relative">
                                    <img src="{{ route('images.show', ['path' => trim($imageArray[0])]) }}" alt="Post image"
                                        class="absolute inset-0 size-full object-cover hover:scale-105 transition-transform duration-500">
                                </div>
                            @else
                                {{-- Multiple images --}}
                                <div
                                    class="mb-4 rounded-xl overflow-hidden grid gap-2 @if(count($imageArray) === 2) grid-cols-2 h-64 @elseif(count($imageArray) === 3) grid-cols-2 grid-rows-2 h-[400px] @else grid-cols-2 grid-rows-2 h-[400px] @endif">
                                    @foreach($imageArray as $index => $image)
                                        <div class="relative @if(count($imageArray) === 3 && $index === 0) row-span-2 @endif">
                                            <img src="{{ route('images.show', ['path' => trim($image)]) }}" alt="Post image"
                                                class="absolute inset-0 size-full object-cover hover:scale-105 transition-transform duration-500">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @endif

                    <!-- Video -->
                    @if($post->video)
                        <div class="mb-4 rounded-xl overflow-hidden h-80">
                            <video src="{{ route('images.show', ['path' => $post->video]) }}" class="w-full h-full object-cover"
                                controls></video>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center gap-6">
                        <button wire:click="toggleLike({{ $post->id }})"
                            @click="new Audio('{{ asset('assets/mixkit-little-goat-bleat-319.wav') }}').play()"
                            class="flex items-center gap-1.5 transition-colors group @if($post->isLikedBy(auth()->user())) text-red-500 @else text-zinc-500 hover:text-red-500 @endif">
                            @if($post->isLikedBy(auth()->user()))
                                <svg class="size-5 fill-current group-hover:scale-110 transition-transform" viewBox="0 0 24 24"
                                    fill="currentColor">
                                    <path
                                        d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                                </svg>
                            @else
                                <flux:icon name="heart" class="size-5 group-hover:scale-110 transition-transform" />
                            @endif
                            <span class="text-sm font-medium">{{ $post->likes_count ?? 0 }}</span>
                        </button>
                        <button @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                            class="flex items-center gap-1.5 text-zinc-500 hover:text-blue-500 transition-colors">
                            <flux:icon name="chat-bubble-left" class="size-5" />
                            <span class="text-sm font-medium">{{ $post->all_comments_count ?? 0 }}</span>
                        </button>
                        <button x-data="{ 
                                                        copied: false,
                                                        share() {
                                                            const shareData = {
                                                                title: 'Post by {{ $post->user->name }}',
                                                                text: 'Check out this post on Allsers: {{ Str::limit($post->content, 50) }}',
                                                                url: window.location.origin + '/dashboard?post={{ $post->post_id }}'
                                                            };

                                                            if (navigator.share) {
                                                                navigator.share(shareData).catch(console.error);
                                                            } else {
                                                                navigator.clipboard.writeText(shareData.url).then(() => {
                                                                    this.copied = true;
                                                                    setTimeout(() => this.copied = false, 2000);
                                                                });
                                                            }
                                                        }
                                                    }" @click.stop="share()"
                            class="flex items-center gap-1.5 transition-colors relative"
                            :class="copied ? 'text-green-500' : 'text-zinc-500 hover:text-green-500'">
                            <flux:icon name="share" class="size-5" />
                            <span x-show="copied" x-transition
                                class="absolute -top-8 left-1/2 -translate-x-1/2 bg-zinc-800 text-white text-[10px] px-2 py-1 rounded shadow-lg whitespace-nowrap">
                                {{ __('Link Copied!') }}
                            </span>
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="toggleBookmark({{ $post->id }})"
                            class="transition-colors @if($post->isBookmarkedBy(auth()->user())) text-[var(--color-brand-purple)] @else text-zinc-400 hover:text-[var(--color-brand-purple)] @endif">
                            @if($post->isBookmarkedBy(auth()->user()))
                                <svg class="size-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6 2c-1.1 0-2 .9-2 2v18l8-3.5 8 3.5V4c0-1.1-.9-2-2-2H6z" />
                                </svg>

                            @else
                                <flux:icon name="bookmark" class="size-5" />
                            @endif
                        </button>
                        <a href="{{ route('user.profile', $post->user) }}" wire:navigate
                            class="border border-[var(--color-brand-purple)] text-[var(--color-brand-purple)] px-4 py-1.5 rounded-full text-xs font-semibold hover:bg-[var(--color-brand-purple)] hover:text-white transition-all">
                            {{ __('Contact') }}
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-8 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
                <p class="text-zinc-500">{{ __('No posts yet. Be the first to share!') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Post Detail Drawer -->
    <livewire:dashboard.post-detail />
</div>