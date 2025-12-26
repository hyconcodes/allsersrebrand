<?php

use App\Models\Post;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $posts = [];

    public function mount()
    {
        $this->loadPosts();
    }

    public function loadPosts()
    {
        $userId = auth()->id();
        $this->posts = Post::whereHas('bookmarks', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->with([
                'user',
                'likes' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                },
                'bookmarks' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }
            ])
            ->withCount(['likes', 'allComments'])
            ->latest()
            ->get();
    }

    #[On('comment-added')]
    #[On('post-liked')]
    #[On('post-bookmarked')]
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

<div class="space-y-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('Your Saved Items') }}</h2>
        <span class="text-sm text-zinc-500">{{ count($posts) }} {{ trans_choice('item|items', count($posts)) }}</span>
    </div>

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
                </div>

                @if($post->content)
                    <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed">
                        {{ $post->content }}
                    </p>
                @endif

                <!-- Images/Video Logic (Reused from Feed) -->
                @if($post->images)
                    @php $imageArray = array_filter(explode(',', $post->images)); @endphp
                    @if(count($imageArray) > 0)
                        <div
                            class="mb-4 rounded-xl overflow-hidden @if(count($imageArray) === 1) h-80 @else grid grid-cols-2 gap-2 h-64 @endif relative">
                            @foreach($imageArray as $image)
                                <img src="{{ route('images.show', ['path' => trim($image)]) }}" class="w-full h-full object-cover">
                            @endforeach
                        </div>
                    @endif
                @endif

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
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="toggleBookmark({{ $post->id }})" class="text-[var(--color-brand-purple)]">
                        <svg class="size-5 fill-current" viewBox="0 0 24 24">
                            <path d="M5 4c0-1.1.9-2 2-2h10a2 2-2v18l-7-3-7 3V4z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @empty
        <div
            class="bg-white dark:bg-zinc-900 rounded-2xl p-12 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
            <div class="size-16 bg-zinc-50 dark:bg-zinc-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <flux:icon name="bookmark" class="size-8 text-zinc-400" />
            </div>
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('No bookmarks yet') }}</h3>
            <p class="text-zinc-500 max-w-xs mx-auto text-sm">
                {{ __('Posts you save will appear here. Start exploring and bookmark your favorite inspirations!') }}</p>
            <a href="{{ route('dashboard') }}"
                class="inline-block mt-6 px-6 py-2 bg-[var(--color-brand-purple)] text-white rounded-full text-sm font-bold">
                {{ __('Explore Feed') }}
            </a>
        </div>
    @endforelse

    <!-- Reuse Post Detail Drawer -->
    <livewire:dashboard.post-detail />
</div>