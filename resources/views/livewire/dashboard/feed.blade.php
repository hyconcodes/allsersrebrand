<?php

use App\Models\Post;
use App\Models\User;
use App\Notifications\UserTagged;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;

new class extends Component {
    use WithFileUploads;

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

    public $repostingPostId = null;
    public $repostContent = '';
    public $repostImage = null;
    public $repostVideo = null;
    public $showRepostModal = false;

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
                    $q->latest()->limit(1)->with('user');
                },
                'likes' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
                'bookmarks' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
            ])
            ->withCount(['likes', 'allComments']);

        if ($this->tab === 'local' && auth()->user()->latitude && auth()->user()->longitude) {
            $lat = auth()->user()->latitude;
            $lng = auth()->user()->longitude;

            $query
                ->join('users', 'posts.user_id', '=', 'users.id')
                ->select('posts.*')
                ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(users.latitude)) * cos(radians(users.longitude) - radians(?)) + sin(radians(?)) * sin(radians(users.latitude)))) AS distance', [$lat, $lng, $lat])
                ->whereNotNull('users.latitude')
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
    public function refreshFeed()
    {
        $this->loadPosts(true);
    }

    public function checkNewPosts()
    {
        if (!$this->latestPostId) return;
        
        // Simple polling: check for any newer posts.
        // For 'local', strictly speaking we should check distance, but ID check is a good enough signal for activity.
        $this->newPostsCount = Post::where('id', '>', $this->latestPostId)->count();
    }

    public function loadNewPosts()
    {
        $this->loadPosts(true);
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
        try {
            $this->validate([
                'repostContent' => 'nullable|string|max:1000',
                'repostImage' => 'nullable|image|max:10240',
                'repostVideo' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:10240',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->validator->errors()->getMessages() as $field => $messages) {
                if (str_contains($field, 'repostImage') || str_contains($field, 'repostVideo')) {
                    if (str_contains(implode(' ', $messages), 'kilobytes') || str_contains(implode(' ', $messages), 'large')) {
                        $this->dispatch('toast', type: 'error', title: 'File Too Large', message: 'Images and videos must be less than 10MB.');
                        break;
                    }
                }
            }
            throw $e;
        }

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
        if (!$post) {
            return;
        }

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
                $user->notify(new UserTagged($post, auth()->user()));
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
    <div wire:poll.15s="checkNewPosts"></div>

    <livewire:dashboard.navigation />

    @if ($newPostsCount > 0)
        <div class="flex justify-center -mt-4 mb-2">
            <button wire:click="loadNewPosts"
                class="px-4 py-2 bg-[var(--color-brand-purple)] text-white text-xs font-bold uppercase tracking-widest rounded-full shadow-lg hover:scale-105 active:scale-95 transition-all flex items-center gap-2 animate-in slide-in-from-top-4 fade-in duration-300 transform">
                <flux:icon name="arrow-up" class="size-4" />
                <span>Show {{ $newPostsCount }} New Posts</span>
            </button>
        </div>
    @endif

    <livewire:global-search />

    @if (auth()->user()->isArtisan())
        <!-- Create Post Widget -->
        <div
            class="bg-white dark:bg-zinc-900 rounded-2xl md:rounded-2xl p-3 md:p-4 shadow-none md:shadow-sm border-b md:border border-zinc-200 dark:border-zinc-800">
            <form wire:submit="createPost">
                <div class="flex items-start gap-3 md:gap-4">
                    <div class="shrink-0">
                        <div
                            class="size-8 md:size-10 rounded-full bg-[var(--color-brand-purple)]/10 flex items-center justify-center text-[var(--color-brand-purple)] font-bold overflow-hidden">
                            @if (auth()->user()->profile_picture_url)
                                <img src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                            @else
                                {{ auth()->user()->initials() }}
                            @endif
                        </div>
                    </div>
                    <div class="flex-1 min-w-0 space-y-2" x-data="{
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
                        <textarea wire:model="content" placeholder="{{ __('Share your work...') }}"
                            class="w-full bg-zinc-50 dark:bg-zinc-800 border-none rounded-xl px-3 py-2 text-xs md:text-sm focus:ring-2 focus:ring-[var(--color-brand-purple)]/20 transition-all resize-none"
                            rows="2"></textarea>

                        <div class="flex flex-wrap gap-2 px-1">
                            @foreach (['🔥', '✨', '🛠️', '🎨', '🚀', '👏', '🙌'] as $emoji)
                                <button type="button" @click="insertEmoji('{{ $emoji }}')"
                                    class="text-xs hover:scale-125 transition-transform p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ $emoji }}</button>
                            @endforeach
                        </div>

                        @error('content')
                            <span class="text-red-500 text-[10px]">{{ $message }}</span>
                        @enderror
                        @error('permission')
                            <span class="text-red-500 text-[10px]">{{ $message }}</span>
                        @enderror

                        <!-- Image Previews -->
                        @if (!empty($images))
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($images as $index => $image)
                                    <div class="relative group">
                                        <img src="{{ $image->temporaryUrl() }}" class="w-full h-24 object-cover rounded-lg">
                                        <button type="button" wire:click="removeImage({{ $index }})"
                                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <flux:icon name="x-mark" class="size-3" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Video Preview -->
                        @if ($video)
                            <div class="relative group">
                                <video src="{{ $video->temporaryUrl() }}" class="w-full h-32 object-cover rounded-lg"
                                    controls></video>
                                <button type="button" wire:click="removeVideo"
                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endif

                        <!-- Price Range (Optional) -->
                        <div x-data="{ showPrice: {{ $price_min || $price_max ? 'true' : 'false' }} }" class="space-y-2">
                            <button type="button" @click="showPrice = !showPrice"
                                class="flex items-center gap-2 text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:text-[var(--color-brand-purple)] transition-colors">
                                <flux:icon name="currency-dollar" class="size-3.5" />
                                <span x-text="showPrice ? '{{ __('Hide') }}' : '{{ __('Price Range') }}'"></span>
                            </button>

                            <div x-show="showPrice" x-collapse class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-medium text-zinc-600 dark:text-zinc-400 mb-1">
                                        {{ __('Min') }} ({{ auth()->user()->currency_symbol }})
                                    </label>
                                    <input type="number" wire:model="price_min" min="0" step="0.01"
                                        class="w-full px-2 py-1.5 text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg focus:ring-2 focus:ring-[var(--color-brand-purple)] focus:border-transparent bg-white dark:bg-zinc-800"
                                        placeholder="0.00">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-zinc-600 dark:text-zinc-400 mb-1">
                                        {{ __('Max') }} ({{ auth()->user()->currency_symbol }})
                                    </label>
                                    <input type="number" wire:model="price_max" min="0" step="0.01"
                                        class="w-full px-2 py-1.5 text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg focus:ring-2 focus:ring-[var(--color-brand-purple)] focus:border-transparent bg-white dark:bg-zinc-800"
                                        placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between px-1 pt-1">
                            <div class="flex items-center gap-3">
                                <label
                                    class="flex items-center gap-1.5 text-zinc-500 hover:text-[var(--color-brand-purple)] text-xs font-medium transition-colors cursor-pointer">
                                    <flux:icon name="photo" class="size-4" />
                                    <span class="hidden sm:inline">{{ __('Image') }}</span>
                                    <input type="file" wire:model="images" multiple accept="image/*" class="hidden"
                                        :disabled="video != null">
                                </label>
                                <label
                                    class="flex items-center gap-1.5 text-zinc-500 hover:text-[var(--color-brand-purple)] text-xs font-medium transition-colors cursor-pointer">
                                    <flux:icon name="video-camera" class="size-4" />
                                    <span class="hidden sm:inline">{{ __('Video') }}</span>
                                    <input type="file" wire:model="video" accept="video/*" class="hidden"
                                        :disabled="images.length > 0">
                                </label>
                            </div>
                            <button type="submit"
                                class="bg-[var(--color-brand-purple)] text-white px-4 py-1.5 rounded-full text-xs md:text-sm font-bold hover:bg-[var(--color-brand-purple)]/90 transition-colors shadow-lg shadow-purple-500/20 disabled:opacity-50"
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

    <!-- Suggested Professionals (In-Feed) -->
    {{-- <div class="w-[calc(100-30)]"> --}}
        <livewire:dashboard.pros-widget :in-feed="true" />
        {{--
    </div> --}}

    <!-- Feed Posts -->
    <div class="space-y-6">
        @forelse($posts as $post)
            <livewire:dashboard.post-item :post="$post" :wire:key="'post-'.$post->id" />
        @empty
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-8 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
                <p class="text-zinc-500">{{ __('No posts yet. Be the first to share!') }}</p>
            </div>
        @endforelse

        <!-- Manual Load More Trigger -->
        @if ($hasMore)
            <div
                class="w-full py-12 flex flex-col items-center justify-center border-t border-dashed border-zinc-100 dark:border-zinc-800">

                {{-- Button --}}
                <button wire:click="loadMore" wire:loading.remove wire:target="loadMore"
                    class="group flex flex-col items-center gap-2 text-zinc-400 hover:text-[var(--color-brand-purple)] transition-colors p-4">
                    <div
                        class="size-10 rounded-full border-2 border-current flex items-center justify-center group-hover:scale-110 transition-transform bg-white dark:bg-zinc-900">
                        <flux:icon name="chevron-down" class="size-5" />
                    </div>
                </button>

                {{-- Loading State --}}
                <div wire:loading wire:target="loadMore" class="flex flex-col gap-6 w-full px-4 items-center">
                    <div class="flex justify-center mb-4">
                        <span
                            class="text-xs font-bold text-[var(--color-brand-purple)] uppercase tracking-widest animate-pulse">
                            {{ __('Loading...') }}
                        </span>
                    </div>
                    {{-- Skeleton Loader --}}
                    <div class="w-full space-y-6 opacity-50">
                        <div
                            class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 animate-pulse">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="size-10 rounded-full bg-zinc-200 dark:bg-zinc-800"></div>
                                <div class="space-y-1">
                                    <div class="h-3 w-24 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
                                    <div class="h-2 w-16 bg-zinc-100 dark:bg-zinc-700 rounded"></div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="h-3 w-full bg-zinc-100 dark:bg-zinc-800 rounded"></div>
                                <div class="h-3 w-4/5 bg-zinc-100 dark:bg-zinc-800 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

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
                        <div class="relative group size-20 rounded-lg overflow-hidden border border-zinc-200 shadow-sm">
                            <img src="{{ $repostImage->temporaryUrl() }}" class="size-full object-cover">
                            <button type="button" wire:click="$set('repostImage', null)"
                                class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <flux:icon name="x-mark" class="size-3" />
                            </button>
                        </div>
                    @endif

                    @if ($repostVideo)
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