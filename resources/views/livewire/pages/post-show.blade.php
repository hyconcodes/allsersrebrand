<?php

use App\Models\Post;
use App\Models\Comment;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component {
    public Post $post;
    public string $commentContent = '';
    public ?int $replyToId = null;
    public string $replyToName = '';
    public $showReportModal = false;
    public $reportReason = '';

    public function mount(Post $post)
    {
        $this->post = $post->load(['user', 'repostOf.user', 'comments.user', 'comments.replies.user'])->loadCount(['likes', 'allComments']);

        if (auth()->check()) {
            $this->post->load([
                'bookmarks' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
            ]);
        }
    }

    public function addComment()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (empty(trim($this->commentContent))) {
            return;
        }

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'post_id' => $this->post->id,
            'parent_id' => $this->replyToId,
            'content' => $this->commentContent,
        ]);

        // Notify
        $user = auth()->user();
        if ($this->replyToId) {
            $parentComment = Comment::find($this->replyToId);
            if ($parentComment && $parentComment->user_id !== $user->id) {
                $parentComment->user->notify(new \App\Notifications\NewReply($parentComment, $user, $comment));
            }
        } elseif ($this->post->user_id !== $user->id) {
            $this->post->user->notify(new \App\Notifications\CommentAdded($this->post, $user, $comment));
        }

        $this->commentContent = '';
        $this->replyToId = null;
        $this->replyToName = '';

        // Refresh post data
        $this->mount($this->post);
    }

    public function setReplyTo($commentId)
    {
        $this->replyToId = $commentId;
        $comment = Comment::with('user')->find($commentId);
        $this->replyToName = $comment ? $comment->user->name : '';
    }

    public function cancelReply()
    {
        $this->replyToId = null;
        $this->replyToName = '';
    }

    public function toggleBookmark()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $existingBookmark = $this->post->bookmarks()->where('user_id', $user->id)->first();

        if ($existingBookmark) {
            $existingBookmark->delete();
        } else {
            try {
                $this->post->bookmarks()->create(['user_id' => $user->id]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        $this->mount($this->post);
    }

    public function toggleLike()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $existingLike = $this->post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
        } else {
            try {
                $this->post->likes()->create(['user_id' => $user->id]);

                if ($this->post->user_id !== $user->id) {
                    $this->post->user->notify(new \App\Notifications\PostLiked($this->post, $user));
                }
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        $this->mount($this->post);
    }

    public function deletePost()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if ($this->post && $this->post->user_id === auth()->id()) {
            $this->post->delete();
            return redirect()
                ->route('dashboard')
                ->with('toast', ['type' => 'success', 'title' => 'Deleted', 'message' => 'Post has been deleted.']);
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'You cannot delete this post.');
        }
    }

    public function openReportModal()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        $this->showReportModal = true;
        $this->reportReason = '';
    }

    public function submitReport()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $throttleKey = 'report:' . auth()->id();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $this->dispatch('toast', type: 'error', title: 'Too Many Reports', message: 'Please wait before submitting another report.');
            return;
        }
        RateLimiter::hit($throttleKey, 3600);

        $this->validate([
            'reportReason' => 'required|string|min:10|max:500',
        ]);

        \App\Models\Report::create([
            'user_id' => auth()->id(),
            'post_id' => $this->post->id,
            'reason' => $this->reportReason,
            'status' => 'pending',
        ]);

        $this->showReportModal = false;
        $this->dispatch('toast', type: 'success', title: 'Report Submitted', message: 'Thank you for reporting. We will review this post.');
    }

    public function deleteComment($commentId)
    {
        if (!auth()->check()) {
            return;
        }

        $comment = Comment::find($commentId);
        if ($comment && $comment->user_id === auth()->id()) {
            $comment->delete();
            $this->mount($this->post);
            $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Comment deleted.');
        }
    }

    public function sendQuickComment($emoji)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $this->commentContent = $emoji;
        $this->addComment();
    }
    public function rendering(\Illuminate\View\View $view)
    {
        $title = $this->post->user->name . ' on Allsers: "' . Str::limit($this->post->content, 50) . '"';
        $description = Str::limit($this->post->content, 160);

        $image = null;
        if ($this->post->images) {
            $imageArray = is_array($this->post->images) ? $this->post->images : array_filter(explode(',', (string) $this->post->images));
            if (count($imageArray) > 0) {
                $image = route('images.show', ['path' => trim($imageArray[0])]);
            }
        }

        $view->layout('components.layouts.app', [
            'title' => $title,
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaImage' => $image,
            'metaUrl' => route('posts.show', $this->post->post_id),
            'metaType' => 'article',
        ]);
    }
}; ?>


<div>
    <div class="max-w-2xl mx-auto">
        <livewire:dashboard.navigation />

        <!-- Original Post -->
        <div class="border-b border-zinc-200 dark:border-zinc-800 px-4 py-3">
            @if ($post?->repost_of_id)
                <div class="flex items-center gap-2 text-xs text-zinc-500 font-medium mb-2">
                    <flux:icon name="arrow-path-rounded-square" class="size-3.5" />
                    <span>{{ $post->user->name }} reposted</span>
                </div>
            @endif
            <div class="flex justify-between items-start mb-3">
                <div class="flex items-center gap-3">
                    <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate
                        class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 font-bold text-sm overflow-hidden cursor-pointer shrink-0">
                        @if ($post->user->profile_picture_url)
                            <img loading="lazy" src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $post->user->initials() }}
                        @endif
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-zinc-100 hover:text-purple-500 cursor-pointer">
                                <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate>{{ $post->user->username }}</a>
                            </h3>
                        </div>
                        <p class="text-xs text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                <flux:dropdown>
                    <button class="text-zinc-500 hover:text-zinc-300">
                        <flux:icon name="ellipsis-horizontal" class="size-5" />
                    </button>
                    <flux:menu>
                        @auth
                            @if ($post->user_id === auth()->id())
                                <flux:menu.item wire:click.stop="deletePost"
                                    wire:confirm="{{ __('Are you sure you want to delete this post?') }}" icon="trash"
                                    variant="danger">{{ __('Delete') }}</flux:menu.item>
                            @else
                                <flux:menu.item wire:click.stop="openReportModal" icon="flag">{{ __('Report') }}
                                </flux:menu.item>
                            @endif
                        @endauth
                        <flux:menu.item x-data="{
                            copied: false,
                            share() {
                                const shareData = {
                                    title: 'Post by {{ $post->user->username }}',
                                    text: 'Check out this post on Allsers',
                                    url: window.location.href
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
                        }" @click="share()" icon="share">
                            <span x-show="!copied">{{ __('Share') }}</span>
                            <span x-show="copied" class="text-green-500">{{ __('Link Copied!') }}</span>
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>

            {{-- Price Range Badge --}}
            @if ($post->price_min || $post->price_max)
                <div class="mb-3">
                    <div
                        class="inline-flex items-center gap-1.5 border border-purple-500/50 text-purple-500 px-3 py-1 rounded-full text-xs font-semibold">
                        <flux:icon name="currency-dollar" class="size-3.5" />
                        <span>
                            @if ($post->price_min && $post->price_max)
                                {{ $post->user->currency_symbol }}{{ number_format($post->price_min, 0) }} - {{ $post->user->currency_symbol }}{{ number_format($post->price_max, 0) }}
                            @elseif ($post->price_min)
                                {{ $post->user->currency_symbol }}{{ number_format($post->price_min, 0) }}
                            @else
                                {{ __('Up to') }} {{ $post->user->currency_symbol }}{{ number_format($post->price_max, 0) }}
                            @endif
                        </span>
                    </div>
                </div>
            @endif

            <p class="text-zinc-700 dark:text-zinc-300 text-sm leading-relaxed whitespace-pre-line break-words mb-3">
                {!! $post->formatted_content !!}
            </p>

            <!-- Images -->
            @if ($post->images)
                @php
                    $imageArray = is_array($post->images) ? $post->images : array_filter(explode(',', (string)$post->images));
                    $imageUrls = array_map(fn($img) => route('images.show', ['path' => trim($img)]), $imageArray);
                @endphp
                @if (count($imageArray) > 0)
                    <div class="space-y-2 mb-3">
                        @foreach ($imageArray as $index => $image)
                            <div class="rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 cursor-pointer"
                                @click="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: {{ $index }} })">
                                <img loading="lazy" src="{{ route('images.show', ['path' => trim($image)]) }}"
                                    class="w-full h-auto object-cover">
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            <!-- Video -->
            @if ($post->video)
                <div class="rounded-xl overflow-hidden h-80 border border-zinc-200 dark:border-zinc-800 mb-3">
                    <video src="{{ route('videos.show', ['path' => $post->video]) }}"
                        class="w-full h-full object-cover" controls controlsList="nodownload" playsinline
                        preload="metadata"></video>
                </div>
            @endif

            <!-- Original Post Preview (Repost) -->
            @if ($post->repostOf)
                <div class="mb-3 border border-zinc-200 dark:border-zinc-800 rounded-xl p-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors cursor-pointer">
                    <a href="{{ route('posts.show', $post->repostOf) }}" wire:navigate class="block">
                        <div class="flex items-center gap-2 mb-2">
                            <div
                                class="size-6 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-xs overflow-hidden shrink-0">
                                @if ($post->repostOf->user->profile_picture_url)
                                    <img loading="lazy" src="{{ $post->repostOf->user->profile_picture_url }}"
                                        class="size-full object-cover">
                                @else
                                    {{ $post->repostOf->user->initials() }}
                                @endif
                            </div>
                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $post->repostOf->user->name }}</span>
                            <span class="text-xs text-zinc-500">&bull; {{ $post->repostOf->created_at->diffForHumans() }}</span>
                        </div>
                        @if ($post->repostOf->price_min || $post->repostOf->price_max)
                            <div class="flex items-center gap-1.5 mb-2">
                                <div
                                    class="inline-flex items-center gap-1 border border-purple-500/50 text-purple-500 px-2 py-0.5 rounded-full text-xs font-semibold">
                                    <flux:icon name="currency-dollar" class="size-3" />
                                    <span>
                                        @if ($post->repostOf->price_min && $post->repostOf->price_max)
                                            {{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_min, 0) }} - {{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_max, 0) }}
                                        @elseif ($post->repostOf->price_min)
                                            From {{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_min, 0) }}
                                        @else
                                            Up to {{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_max, 0) }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endif
                        <p class="text-xs text-zinc-400 line-clamp-2 mb-2 whitespace-pre-wrap break-words">
                            {!! $post->repostOf->formatted_content !!}
                        </p>
                        @if ($post->repostOf->images)
                            @php
                                $originImages = is_array($post->repostOf->images) ? $post->repostOf->images : array_filter(explode(',', (string)$post->repostOf->images));
                                $originUrls = array_map(fn($o) => route('images.show', ['path' => trim($o)]), $originImages);
                            @endphp
                            @if (count($originImages) > 0)
                                <div class="h-32 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 cursor-pointer"
                                    @click="$dispatch('open-lightbox', { images: {{ Js::from($originUrls) }}, index: 0 })">
                                    <img loading="lazy" src="{{ route('images.show', ['path' => trim($originImages[0])]) }}"
                                        class="size-full object-cover">
                                </div>
                            @endif
                        @elseif($post->repostOf->video)
                            <div class="h-32 rounded-xl overflow-hidden bg-black flex items-center justify-center border border-zinc-200 dark:border-zinc-800">
                                <video src="{{ route('videos.show', ['path' => $post->repostOf->video]) }}"
                                    class="w-full h-full object-cover" controls controlsList="nodownload"
                                    playsinline preload="metadata"></video>
                            </div>
                        @endif
                    </a>
                </div>
            @endif

            <!-- Actions -->
            <div class="flex items-center justify-between max-w-md pt-1">
                <button wire:click="toggleLike"
                    class="flex items-center gap-1.5 transition-colors {{ auth()->check() && $post->isLikedBy(auth()->user()) ? 'text-red-500' : 'text-zinc-500 hover:text-red-500' }}">
                    @if (auth()->check() && $post->isLikedBy(auth()->user()))
                        <svg class="size-[18px] fill-current" viewBox="0 0 24 24">
                            <path
                                d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                        </svg>
                    @else
                        <flux:icon name="heart" class="size-[18px]" />
                    @endif
                    <span class="text-xs">{{ $post->likes_count }}</span>
                </button>

                <span class="flex items-center gap-1.5 text-zinc-500">
                    <flux:icon name="chat-bubble-left" class="size-[18px]" />
                    <span class="text-xs">{{ $post->all_comments_count }}</span>
                </span>

                <button class="flex items-center gap-1.5 text-zinc-500 hover:text-emerald-500 transition-colors">
                    <flux:icon name="arrow-path-rounded-square" class="size-[18px]" />
                </button>

                <button x-data="{ copied: false }" @click="
                    const d = { title: 'Post by {{ $post->user->username }}', text: 'Check out this post on Allsers', url: window.location.href };
                    if (navigator.share) { navigator.share(d).catch(console.error); }
                    else { navigator.clipboard.writeText(d.url).then(() => { copied = true; setTimeout(() => copied = false, 2000); }); }
                "
                    class="flex items-center gap-1.5 transition-colors text-zinc-500 hover:text-green-500 relative"
                    :class="copied && 'text-green-500'">
                    <flux:icon name="share" class="size-[18px]" />
                    <span x-show="copied" x-transition
                        class="absolute -top-8 left-1/2 -translate-x-1/2 bg-zinc-800 text-white text-xs px-2 py-1 rounded shadow-lg whitespace-nowrap z-50">
                        {{ __('Copied!') }}
                    </span>
                </button>

                <button wire:click="toggleBookmark"
                    class="flex items-center gap-1.5 text-zinc-500 hover:text-purple-500 transition-colors">
                    <flux:icon name="bookmark" class="size-[18px]" />
                </button>

                @if (auth()->check() && $post->user_id !== auth()->id())
                    <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate
                        class="flex items-center gap-1.5 text-zinc-500 hover:text-purple-500 transition-colors">
                        <flux:icon name="chat-bubble-left-right" class="size-[18px]" />
                    </a>
                @endif
            </div>
        </div>

        <!-- Comments Section -->
        <div>
            @forelse($post->comments as $comment)
                <div class="flex gap-3 px-4 py-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="flex flex-col items-center">
                        <div
                            class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 font-bold text-sm shrink-0 overflow-hidden">
                            @if ($comment->user->profile_picture_url)
                                <img loading="lazy" src="{{ $comment->user->profile_picture_url }}"
                                    class="size-full object-cover">
                            @else
                                {{ $comment->user->initials() }}
                            @endif
                        </div>
                        @if (!$loop->last)
                            <div class="w-0.5 flex-1 bg-zinc-200 dark:bg-zinc-800 mt-2"></div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $comment->user->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $comment->content }}</p>
                        @if (!$post->challenge_id && auth()->check())
                            <div class="flex items-center gap-4 mt-2">
                                <button wire:click="setReplyTo({{ $comment->id }})"
                                    class="text-xs text-zinc-500 hover:text-purple-500 transition-colors">
                                    {{ __('Reply') }}
                                </button>
                                @if (auth()->id() === $comment->user_id)
                                    <button wire:click="deleteComment({{ $comment->id }})"
                                        wire:confirm="{{ __('Delete comment?') }}"
                                        class="text-xs text-red-500 hover:underline">
                                        {{ __('Delete') }}
                                    </button>
                                @endif
                            </div>
                        @endif

                        @if ($comment->replies->count() > 0)
                            <div class="mt-3 space-y-3 pl-4 border-l-2 border-zinc-200 dark:border-zinc-800">
                                @foreach ($comment->replies as $reply)
                                    <div class="flex gap-2">
                                        <div
                                            class="size-7 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 font-bold text-xs shrink-0 overflow-hidden">
                                            @if ($reply->user->profile_picture_url)
                                                <img loading="lazy" src="{{ $reply->user->profile_picture_url }}"
                                                    class="size-full object-cover">
                                            @else
                                                {{ $reply->user->initials() }}
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $reply->user->name }}</span>
                                                <span class="text-xs text-zinc-500">{{ $reply->created_at->diffForHumans() }}</span>
                                            </div>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $reply->content }}</p>
                                            @if (auth()->id() === $reply->user_id)
                                                <button wire:click="deleteComment({{ $reply->id }})"
                                                    wire:confirm="{{ __('Delete reply?') }}"
                                                    class="text-xs text-red-500 hover:underline mt-1">
                                                    {{ __('Delete') }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 border-b border-zinc-200 dark:border-zinc-800 text-center">
                    <p class="text-sm text-zinc-500">
                        {{ __('No comments yet. Be the first to share your thoughts!') }}
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Comment Composer -->
        <div class="sticky bottom-0 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-4 py-3">
            @auth
                @if ($replyToId)
                    <div class="flex items-center justify-between mb-2 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-900 rounded-lg">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Replying to') }} <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ $replyToName }}</span>
                        </span>
                        <button wire:click="cancelReply" class="text-zinc-500 hover:text-red-500">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                @endif
                <div class="flex items-center gap-3">
                    <div
                        class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 text-xs font-bold shrink-0 overflow-hidden">
                        @if (auth()->user()->profile_picture_url)
                            <img loading="lazy" src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ auth()->user()->initials() }}
                        @endif
                    </div>
                    <div class="flex-1 flex items-center gap-2 bg-zinc-100 dark:bg-zinc-900 rounded-full px-4 py-2 border border-zinc-200 dark:border-zinc-800 focus-within:border-purple-500 transition-colors">
                        <textarea wire:model="commentContent" placeholder="{{ __('Post a reply...') }}" rows="1"
                            class="flex-1 bg-transparent border-0 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:ring-0 resize-none outline-none min-h-[24px] max-h-[120px] scrollbar-hide"
                            wire:keydown.enter.prevent="addComment"></textarea>
                        <button wire:click="addComment"
                            class="text-purple-500 hover:text-purple-400 transition-colors p-1 shrink-0">
                            <flux:icon name="paper-airplane" class="size-4" />
                        </button>
                    </div>
                </div>
            @else
                <div class="text-center text-sm text-zinc-500 py-2">
                    <a href="{{ route('login') }}"
                        class="font-bold text-purple-500 hover:underline">{{ __('Log in') }}</a>
                    {{ __('to reply') }}
                </div>
            @endauth
        </div>
    </div>

    <!-- Report Post Modal -->
    <flux:modal name="report-post-detail-modal" wire:model="showReportModal" class="sm:max-w-lg">
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

                <div class="bg-zinc-100 dark:bg-zinc-900 rounded-lg p-4 text-sm text-zinc-600 dark:text-zinc-400">
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
</div>
