<?php

use App\Models\Post;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    public $posts = [];

    public function mount()
    {
        $this->loadPosts();
    }

    public function loadPosts()
    {
        $this->posts = Post::whereNotNull('video')
            ->where('video', '!=', '')
            ->with([
                'user',
                'repostOf.user',
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

    public function toggleLike($postId)
    {
        $post = Post::find($postId);
        if (!$post)
            return;

        $user = auth()->user();
        $existingLike = $post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
        } else {
            $post->likes()->create(['user_id' => $user->id]);
            if ($post->user_id !== $user->id) {
                $post->user->notify(new \App\Notifications\PostLiked($post, $user));
            }
        }
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
}; ?>

<div
    class="h-[calc(100vh-5rem)] sm:h-[calc(100vh-6rem)] snap-y snap-mandatory overflow-y-scroll no-scrollbar -mx-4 sm:mx-auto max-w-md w-full relative sm:rounded-[2rem] overflow-hidden bg-black">
    @forelse($posts as $post)
        <div class="snap-start snap-always relative w-full h-full flex items-center justify-center bg-black overflow-hidden"
            x-data="{ 
                             playing: false,
                             init() {
                                 let observer = new IntersectionObserver((entries) => {
                                     entries.forEach(entry => {
                                         if (entry.isIntersecting) {
                                             this.$refs.video.play().then(() => {
                                                 this.playing = true;
                                             }).catch(() => {
                                                 this.playing = false;
                                             });
                                         } else {
                                             this.$refs.video.pause();
                                             this.playing = false;
                                         }
                                     });
                                 }, { threshold: 0.6 });
                                 observer.observe(this.$el);
                             },
                             togglePlay() {
                                 if (this.$refs.video.paused) {
                                     this.$refs.video.play();
                                     this.playing = true;
                                 } else {
                                     this.$refs.video.pause();
                                     this.playing = false;
                                 }
                             }
                         }">

            <!-- Video -->
            <video x-ref="video" src="{{ route('images.show', ['path' => $post->video]) }}"
                class="absolute inset-0 w-full h-full object-cover cursor-pointer" loop playsinline
                @click="togglePlay"></video>

            <!-- Play/Pause Indicator Overlay -->
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center" x-show="!playing">
                <div class="bg-black/30 rounded-full p-4 backdrop-blur-sm">
                    <flux:icon name="play" class="size-12 text-white fill-current" />
                </div>
            </div>

            <!-- Overlay Gradient -->
            <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-black/10 via-transparent to-black/60">
            </div>

            <!-- Right Actions Side Bar -->
            <div class="absolute right-3 bottom-20 flex flex-col items-center gap-3 z-20">
                <!-- Like -->
                <button wire:click="toggleLike({{ $post->id }})" class="flex flex-col items-center gap-0.5 group">
                    <div
                        class="bg-zinc-800/50 backdrop-blur-md p-2 rounded-full transition-all group-hover:scale-110 {{ $post->isLikedBy(auth()->user()) ? 'text-red-500' : 'text-white' }}">
                        @if($post->isLikedBy(auth()->user()))
                            <svg class="size-6 fill-current" viewBox="0 0 24 24">
                                <path
                                    d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                            </svg>
                        @else
                            <flux:icon name="heart" class="size-6" />
                        @endif
                    </div>
                    <span
                        class="text-[10px] font-bold text-white shadow-black drop-shadow-md">{{ $post->likes_count }}</span>
                </button>

                <!-- Comment -->
                <button @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                    class="flex flex-col items-center gap-0.5 group">
                    <div
                        class="bg-zinc-800/50 backdrop-blur-md p-2 rounded-full text-white transition-all group-hover:scale-110 group-hover:bg-blue-500/50">
                        <flux:icon name="chat-bubble-left" class="size-6" />
                    </div>
                    <span
                        class="text-[10px] font-bold text-white shadow-black drop-shadow-md">{{ $post->all_comments_count }}</span>
                </button>

                <!-- Bookmark -->
                <button wire:click="toggleBookmark({{ $post->id }})" class="flex flex-col items-center gap-0.5 group">
                    <div
                        class="bg-zinc-800/50 backdrop-blur-md p-2 rounded-full transition-all group-hover:scale-110 {{ $post->isBookmarkedBy(auth()->user()) ? 'text-[var(--color-brand-purple)]' : 'text-white' }}">
                        @if($post->isBookmarkedBy(auth()->user()))
                            <svg class="size-6 fill-current" viewBox="0 0 24 24">
                                <path d="M6 2c-1.1 0-2 .9-2 2v18l8-3.5 8 3.5V4c0-1.1-.9-2-2-2H6z" />
                            </svg>
                        @else
                            <flux:icon name="bookmark" class="size-6" />
                        @endif
                    </div>
                </button>

                <!-- More Options -->
                <flux:dropdown position="left" align="end">
                    <button
                        class="bg-zinc-800/50 backdrop-blur-md p-2 rounded-full text-white transition-all hover:bg-zinc-700">
                        <flux:icon name="ellipsis-horizontal" class="size-5" />
                    </button>
                    <flux:menu>
                        <flux:menu.item icon="flag">{{ __('Report') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <!-- Creator Avatar (Navigates to profile) -->
                <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate class="relative mt-2">
                    <div class="size-10 rounded-full border-2 border-white overflow-hidden bg-zinc-800">
                        @if($post->user->profile_picture_url)
                            <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                        @else
                            <span
                                class="flex items-center justify-center h-full w-full text-white text-[10px] font-bold">{{ $post->user->initials() }}</span>
                        @endif
                    </div>
                    <div
                        class="absolute -bottom-1 left-1/2 -translate-x-1/2 bg-[var(--color-brand-purple)] text-white rounded-full p-0.5">
                        <flux:icon name="plus" class="size-3" />
                    </div>
                </a>
            </div>

            <!-- Bottom Info Area -->
            <div class="absolute bottom-4 left-4 right-16 z-20 text-white pb-safe">
                <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate
                    class="flex items-center gap-2 mb-2 hover:opacity-80 transition-opacity w-fit">
                    <h3 class="font-bold text-lg drop-shadow-md text-sm">{{ $post->user->name }}</h3>
                    <span
                        class="bg-[var(--color-brand-purple)] text-white text-[8px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide border border-white/20">
                        {{ $post->user->work ?? __('Artisan') }}
                    </span>
                    <span class="text-xs text-white/80">• {{ $post->created_at->diffForHumans() }}</span>
                </a>

                @if($post->content)
                    <div class="max-h-24 overflow-y-auto pr-2 no-scrollbar">
                        <p class="text-xs text-white/90 drop-shadow-md font-medium leading-relaxed whitespace-pre-line">
                            {!! $post->formatted_content !!}
                        </p>
                    </div>
                @endif
            </div>

        </div>
    @empty
        <div class="flex flex-col items-center justify-center h-full text-zinc-500">
            <div class="bg-zinc-100 dark:bg-zinc-800 p-6 rounded-full mb-4">
                <flux:icon name="video-camera" class="size-10" />
            </div>
            <h3 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('No Clips Yet') }}</h3>
            <p>{{ __('Check back later for new videos!') }}</p>
        </div>
    @endforelse

    <livewire:dashboard.post-detail />

    <style>
        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
        }

        .pb-safe {
            padding-bottom: env(safe-area-inset-bottom, 20px);
        }
    </style>
</div>