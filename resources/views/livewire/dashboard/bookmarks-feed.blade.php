<?php

use App\Models\Post;
use App\Models\User;
use App\Notifications\UserTagged;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

new class extends Component {
    use WithFileUploads;

    public $posts = [];

    public $repostingPostId = null;
    public $repostContent = '';
    public $repostImage = null;
    public $repostVideo = null;
    public $showRepostModal = false;

    public function mount()
    {
        $this->loadPosts();
    }

    public function loadPosts()
    {
        $this->posts = Post::with([
            'user',
            'repostOf.user',
            'likes' => function ($query) {
                $query->where('user_id', auth()->id());
            },
            'bookmarks' => function ($query) {
                $query->where('user_id', auth()->id());
            }
        ])
            ->whereHas('bookmarks', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->withCount(['likes', 'allComments'])
            ->latest('created_at') // Get latest bookmarks
            ->take(20)
            ->get();
    }

    #[Livewire\Attributes\On('comment-added')]
    #[Livewire\Attributes\On('post-liked')]
    #[Livewire\Attributes\On('post-bookmarked')]
    public function refreshBookmarks()
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

            // Notify post owner
            if ($post->user_id !== $user->id) {
                $post->user->notify(new \App\Notifications\PostLiked($post, $user));
            }
        }

        $this->loadPosts();
        $this->dispatch('post-liked'); // Sync with other feeds
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
        $this->dispatch('post-bookmarked'); // Sync with other feeds
    }

    public function openRepostModal($postId)
    {
        if (!auth()->user()->isArtisan()) {
            $this->dispatch('toast', type: 'error', title: 'Permission Denied', message: 'Only artisans can repost.');
            return;
        }
        $this->repostingPostId = $postId;
        $this->repostContent = '';
        $this->repostImage = null;
        $this->repostVideo = null;
        $this->showRepostModal = true;
    }

    public function createRepost()
    {
        $this->validate([
            'repostContent' => 'nullable|string|max:1000',
            'repostImage' => 'nullable|image|max:5120',
            'repostVideo' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:20480',
        ]);

        $imagePath = $this->repostImage ? $this->repostImage->store('posts/images', 'public') : null;
        $videoPath = $this->repostVideo ? $this->repostVideo->store('posts/videos', 'public') : null;

        $post = Post::create([
            'user_id' => auth()->id(),
            'repost_of_id' => $this->repostingPostId,
            'content' => $this->repostContent,
            'images' => $imagePath,
            'video' => $videoPath,
        ]);

        $this->notifyMentionedUsers($post);

        $this->showRepostModal = false;
        $this->loadPosts();
        $this->dispatch('toast', type: 'success', title: 'Reposted!', message: 'Your repost has been published.');
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
                $user->notify(new UserTagged($post, auth()->user()));
            }
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('Your Saved Items') }}</h2>
        <span class="text-sm text-zinc-500">{{ count($posts) }} {{ trans_choice('item|items', count($posts)) }}</span>
    </div>

    @forelse($posts as $post)
        <div
            class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 relative">
            @if($post->repost_of_id)
                <div class="absolute left-[10px] top-[75px] bottom-[60px] w-0.5 bg-[#6a11cb] opacity-50 z-0"></div>
            @endif
            <div @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })" class="cursor-pointer relative z-10">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        <a @if(auth()->id() !== $post->user_id) href="{{ route('artisan.profile', $post->user) }}"
                        wire:navigate @endif @click.stop
                            class="size-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-sm overflow-hidden @if(auth()->id() !== $post->user_id) cursor-pointer hover:ring-2 hover:ring-[var(--color-brand-purple)] transition-all @endif">
                            @if($post->user->profile_picture_url)
                                <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                            @else
                                {{ $post->user->initials() }}
                            @endif
                        </a>
                        <div>
                            <div class="flex items-center gap-2 text-sm">
                                <h3 @if(auth()->id() !== $post->user_id)
                                @click.stop="window.location.href='{{ route('artisan.profile', $post->user) }}'" @endif
                                    class="font-bold text-zinc-900 dark:text-zinc-100 @if(auth()->id() !== $post->user_id) hover:text-[var(--color-brand-purple)] cursor-pointer @endif">
                                    {{ $post->user->name }}
                                </h3>
                                @if($post->repost_of_id)
                                    <div
                                        class="flex items-center gap-1 text-[10px] text-zinc-500 font-medium bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded-full">
                                        <flux:icon name="arrow-path-rounded-square" class="size-3" />
                                        <span>reposted work</span>
                                    </div>
                                @else
                                    <span
                                        class="bg-purple-100 text-[var(--color-brand-purple)] text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">
                                        {{ $post->user->work ?? __('Artisan') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>

                @if($post->content)
                    @if(Str::length($post->content) > 300)
                        <div x-data="{ expanded: false }">
                            <p x-show="!expanded"
                                class="text-zinc-700 dark:text-zinc-300 mb-2 text-sm leading-relaxed whitespace-pre-line">
                                {!! $post->formatted_content_summary !!}</p>
                            <p x-show="expanded"
                                class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                                {!! $post->formatted_content !!}</p>
                            <button x-show="!expanded" @click.stop="expanded = true"
                                class="text-sm font-medium text-[var(--color-brand-purple)] hover:underline mb-4">{{ __('See More') }}</button>
                        </div>
                    @else
                        <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                            {!! $post->formatted_content !!}</p>
                    @endif
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

                <!-- Original Post Preview (Repost) -->
                @if($post->repostOf)
                    <div @click.stop="$dispatch('open-post-detail', { postId: {{ $post->repost_of_id }} })"
                        class="mb-4 p-4 border border-zinc-100 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer ring-1 ring-transparent hover:ring-[var(--color-brand-purple)]/30">
                        <div class="flex items-center gap-2 mb-2">
                            <div
                                class="size-6 rounded-full bg-purple-50 flex items-center justify-center text-[10px] overflow-hidden">
                                @if($post->repostOf->user->profile_picture_url)
                                    <img src="{{ $post->repostOf->user->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    {{ $post->repostOf->user->initials() }}
                                @endif
                            </div>
                            <span
                                class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $post->repostOf->user->name }}</span>
                            <span class="text-[10px] text-zinc-400">• {{ $post->repostOf->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2 mb-2 whitespace-pre-wrap">
                            {!! $post->repostOf->formatted_content !!}
                        </p>
                        @if($post->repostOf->images)
                            @php $originImages = array_filter(explode(',', $post->repostOf->images)); @endphp
                            @if(count($originImages) > 0)
                                <div class="h-32 rounded-lg overflow-hidden border border-zinc-200/50">
                                    <img src="{{ route('images.show', ['path' => trim($originImages[0])]) }}"
                                        class="size-full object-cover">
                                </div>
                            @endif
                        @elseif($post->repostOf->video)
                            <div
                                class="h-32 rounded-lg overflow-hidden bg-black flex items-center justify-center border border-zinc-200/50">
                                <video src="{{ route('images.show', ['path' => $post->repostOf->video]) }}"
                                    class="w-full h-full object-cover" controls></video>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-6">
                    <button wire:click="toggleLike({{ $post->id }})"
                        @click="new Audio('{{ asset('assets/mixkit-cartoon-toy-whistle-616.wav') }}').play()"
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
                    @if(auth()->user()->isArtisan())
                        <button wire:click="openRepostModal({{ $post->id }})"
                            class="flex items-center gap-1.5 text-zinc-500 hover:text-green-500 transition-colors"
                            title="{{ __('Repost Work') }}">
                            <flux:icon name="arrow-path-rounded-square" class="size-5" />
                        </button>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="toggleBookmark({{ $post->id }})" class="text-[var(--color-brand-purple)]">
                        <svg class="size-5 fill-current" viewBox="0 0 24 24">
                            <path d="M5 4c0-1.1.9-2 2-2h10a2 2-2v18l-7-3-7 3V4z" />
                        </svg>
                    </button>
                    @if($post->user_id !== auth()->id())
                        <a href="{{ route('user.profile', $post->user) }}" wire:navigate
                            class="border border-[var(--color-brand-purple)] text-[var(--color-brand-purple)] px-4 py-1.5 rounded-full text-xs font-semibold hover:bg-[var(--color-brand-purple)] hover:text-white transition-all">
                            {{ __('Contact') }}
                        </a>
                    @endif
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
                {{ __('Posts you save will appear here. Start exploring and bookmark your favorite inspirations!') }}
            </p>
            <a href="{{ route('dashboard') }}"
                class="inline-block mt-6 px-6 py-2 bg-[var(--color-brand-purple)] text-white rounded-full text-sm font-bold">
                {{ __('Explore Feed') }}
            </a>
        </div>
    @endforelse

    <!-- Repost Modal -->
    <flux:modal name="repost-modal" wire:model="showRepostModal" class="sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Repost Work') }}</flux:heading>
                <flux:subheading>{{ __('Share this work and add your own progress or feedback.') }}</flux:subheading>
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
                    @if($repostImage)
                        <div class="relative group size-20 rounded-lg overflow-hidden border border-zinc-200 shadow-sm">
                            <img src="{{ $repostImage->temporaryUrl() }}" class="size-full object-cover">
                            <button type="button" wire:click="$set('repostImage', null)"
                                class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <flux:icon name="x-mark" class="size-3" />
                            </button>
                        </div>
                    @endif

                    @if($repostVideo)
                        <div
                            class="relative group size-20 rounded-lg overflow-hidden border border-zinc-200 flex items-center justify-center bg-zinc-100 shadow-sm">
                            <flux:icon name="video-camera" class="size-8 text-zinc-400" />
                            <button type="button" wire:click="$set('repostVideo', null)"
                                class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <flux:icon name="x-mark" class="size-3" />
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
                    <span wire:loading wire:target="createRepost text-xs" class="flex items-center gap-2">
                        {{ __('Publishing...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Reuse Post Detail Drawer -->
    <livewire:dashboard.post-detail />
</div>