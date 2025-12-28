<?php


use App\Models\Post;
use App\Models\User;
use App\Notifications\UserTagged;
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
            ->withCount(['likes', 'allComments'])
            ->latest()
            ->take(20)
            ->get();
    }

    #[Livewire\Attributes\On('comment-added')]
    #[Livewire\Attributes\On('post-liked')]
    #[Livewire\Attributes\On('post-bookmarked')]
    #[Livewire\Attributes\On('post-deleted')]
    public function refreshFeed()
    {
        $this->loadPosts();
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

        $this->notifyMentionedUsers($post);

        // Reset form
        $this->reset(['content', 'images', 'video']);

        // Reload posts
        $this->loadPosts();

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
                $user->notify(new UserTagged($post, auth()->user()));
            }
        }
    }

    public function removeImage($index)
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function deletePost($postId)
    {
        $post = Post::find($postId);

        if ($post && $post->user_id === auth()->id()) {
            $post->delete();
            $this->loadPosts();
            $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Post has been deleted.');
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'You cannot delete this post.');
        }
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

    public $showReportModal = false;
    public $reportPostId = null;
    public $reportReason = '';

    public function openReportModal($postId)
    {
        $this->reportPostId = $postId;
        $this->showReportModal = true;
        $this->reportReason = '';
    }

    public function submitReport()
    {
        $this->validate([
            'reportReason' => 'required|string|min:10|max:500',
        ]);

        \App\Models\Report::create([
            'user_id' => auth()->id(),
            'post_id' => $this->reportPostId,
            'reason' => $this->reportReason,
            'status' => 'pending',
        ]);

        $this->showReportModal = false;
        $this->dispatch('toast', type: 'success', title: 'Report Submitted', message: 'Thank you for reporting. We will review this post.');
    }
}; ?>

<div class="space-y-6">
    <livewire:global-search />

    @if(auth()->user()->isArtisan())
        <!-- Create Post Widget -->
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-4 shadow-sm border border-zinc-200 dark:border-zinc-800">
            <form wire:submit="createPost">
                <div class="flex items-start gap-4">
                    <div class="shrink-0">
                        <div
                            class="size-10 rounded-full bg-[var(--color-brand-purple)]/10 flex items-center justify-center text-[var(--color-brand-purple)] font-bold overflow-hidden">
                            @if(auth()->user()->profile_picture_url)
                                <img src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                            @else
                                {{ auth()->user()->initials() }}
                            @endif
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
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 relative">
                @if($post->repost_of_id)
                    <div class="absolute left-[10px] top-[75px] bottom-[60px] w-0.5 bg-[#6a11cb] opacity-50 z-0"></div>
                @endif
                <div @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                    class="cursor-pointer relative z-10">
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
                                        @click.stop="window.location.href='{{ route('artisan.profile', $post->user) }}'"
                                    @endif
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
                                            class="bg-purple-100 text-[var(--color-brand-purple)] text-[8px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">
                                            {{ $post->user->work ?? __('Artisan') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <flux:dropdown>
                            <button class="text-zinc-400 hover:text-zinc-600">
                                <flux:icon name="ellipsis-horizontal" class="size-5" />
                            </button>

                            <flux:menu>
                                @if($post->user_id === auth()->id())
                                    <flux:menu.item wire:click="deletePost({{ $post->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this post?') }}" icon="trash"
                                        variant="danger">{{ __('Delete') }}</flux:menu.item>
                                @else
                                    <flux:menu.item wire:click="openReportModal({{ $post->id }})" icon="flag">{{ __('Report') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    @if($post->content)
                        @if(Str::length($post->content) > 300)
                            <div x-data="{ expanded: false }">
                                <p x-show="!expanded"
                                    class="text-zinc-700 dark:text-zinc-300 mb-2 text-sm leading-relaxed whitespace-pre-line">
                                    {!! $post->formatted_content_summary !!}
                                </p>
                                <p x-show="expanded"
                                    class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                                    {!! $post->formatted_content !!}
                                </p>
                                <button x-show="!expanded" @click.stop="expanded = true"
                                    class="text-sm font-medium text-[var(--color-brand-purple)] hover:underline mb-4">{{ __('See More') }}</button>
                            </div>
                        @else
                            <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                                {!! $post->formatted_content !!}
                            </p>
                        @endif
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
                                <span class="text-[10px] text-zinc-400">•
                                    {{ $post->repostOf->created_at->diffForHumans() }}</span>
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
                        <button
                            x-data="{ 
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
                                                                                                                                                                    }"
                            @click.stop="share()" class="flex items-center gap-1.5 transition-colors relative"
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
                class="bg-white dark:bg-zinc-900 rounded-2xl p-8 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
                <p class="text-zinc-500">{{ __('No posts yet. Be the first to share!') }}</p>
            </div>
        @endforelse
    </div>

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

    <!-- Post Detail Drawer -->
    <livewire:dashboard.post-detail />
</div>