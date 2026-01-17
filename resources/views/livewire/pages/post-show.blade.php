<?php

use App\Models\Post;
use App\Models\Comment;
use Livewire\Volt\Component;

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
            $this->post->bookmarks()->create(['user_id' => $user->id]);
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
            $this->post->likes()->create(['user_id' => $user->id]);

            // Notify post owner
            if ($this->post->user_id !== $user->id) {
                $this->post->user->notify(new \App\Notifications\PostLiked($this->post, $user));
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
        $view->layout('components.layouts.app', [
            'title' => $title,
        ]);
    }
}; ?>

@push('head')
    @php
        $description = Str::limit($this->post->content, 160);
        $title = $this->post->user->name . ' on Allsers';
        $image = null;
        if ($this->post->images) {
            $imageArray = array_filter(explode(',', $this->post->images));
            if (count($imageArray) > 0) {
                $image = route('images.show', ['path' => trim($imageArray[0])]);
            }
        }
    @endphp
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if ($image)
        <meta name="twitter:image" content="{{ $image }}">
    @endif
@endpush


<div>
    <div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <livewire:dashboard.navigation />
        <div
            class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 relative">
            <!-- Original Post -->
            <div class="space-y-4 relative">
                @if ($post?->repost_of_id)
                    <div class="absolute left-[-10px] top-12 bottom-[100px] w-0.5 bg-[#6a11cb] opacity-50 z-0">
                    </div>
                @endif
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3 relative z-10">
                        <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate
                            class="size-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-sm overflow-hidden cursor-pointer hover:ring-2 hover:ring-[var(--color-brand-purple)] transition-all">
                            @if ($post->user->profile_picture_url)
                                <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                            @else
                                {{ $post->user->initials() }}
                            @endif
                        </a>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3
                                    class="font-bold text-zinc-900 dark:text-zinc-100 hover:text-[var(--color-brand-purple)] cursor-pointer">
                                    <a href="{{ route('artisan.profile', $post->user) }}"
                                        wire:navigate>{{ $post->user->username }}</a>
                                </h3>
                                @if ($post->repost_of_id)
                                    <div
                                        class="flex items-center gap-1 text-[10px] text-zinc-500 font-medium bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded-full">
                                        <flux:icon name="arrow-path-rounded-square" class="size-3" />
                                        <span>reposted work</span>
                                    </div>
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
                            @auth
                                @if ($post->user_id === auth()->id())
                                    <flux:menu.item wire:click.stop="deletePost"
                                        wire:confirm="{{ __('Are you sure you want to delete this post?') }}"
                                        icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>
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

                {{-- Price Range Badge - Prominent Display --}}
                @if ($post->price_min || $post->price_max)
                    <div class="mb-4">
                        <div
                            class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white px-4 py-2 rounded-full shadow-lg shadow-emerald-500/30">
                            <flux:icon name="currency-dollar" class="size-5" />
                            <div class="flex items-center gap-1.5 font-bold text-sm">
                                @if ($post->price_min && $post->price_max)
                                    <span>{{ $post->user->currency_symbol }}{{ number_format($post->price_min, 0) }}</span>
                                    <span class="opacity-75">-</span>
                                    <span>{{ $post->user->currency_symbol }}{{ number_format($post->price_max, 0) }}</span>
                                @elseif ($post->price_min)
                                    <span>{{ $post->user->currency_symbol }}{{ number_format($post->price_min, 0) }}</span>
                                @else
                                    <span>{{ __('Up to') }}
                                        {{ $post->user->currency_symbol }}{{ number_format($post->price_max, 0) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <p class="text-zinc-700 dark:text-zinc-300 text-sm leading-relaxed whitespace-pre-line">
                    {!! $post->formatted_content !!}
                </p>

                <!-- Images -->
                @if ($post->images)
                    @php $imageArray = array_filter(explode(',', $post->images)); @endphp
                    @if (count($imageArray) > 0)
                        <div class="space-y-2">
                            @foreach ($imageArray as $image)
                                <div class="rounded-xl overflow-hidden border border-zinc-100 dark:border-zinc-800">
                                    <img src="{{ route('images.show', ['path' => trim($image)]) }}"
                                        class="w-full h-auto object-cover">
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                <!-- Video -->
                @if ($post->video)
                    <div class="rounded-xl overflow-hidden h-80 border border-zinc-100 dark:border-zinc-800">
                        <video src="{{ route('videos.show', ['path' => $post->video]) }}"
                            class="w-full h-full object-cover" controls controlsList="nodownload" playsinline
                            preload="metadata"></video>
                    </div>
                @endif

                <!-- Original Post Preview (Repost) -->
                @if ($post->repostOf)
                    <div
                        class="mb-4 p-4 border border-zinc-100 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer ring-1 ring-transparent hover:ring-[var(--color-brand-purple)]/30">
                        <a href="{{ route('posts.show', $post->repostOf) }}" wire:navigate class="block">
                            <div class="flex items-center gap-2 mb-2">
                                <div
                                    class="size-6 rounded-full bg-purple-50 flex items-center justify-center text-[10px] overflow-hidden">
                                    @if ($post->repostOf->user->profile_picture_url)
                                        <img src="{{ $post->repostOf->user->profile_picture_url }}"
                                            class="size-full object-cover">
                                    @else
                                        {{ $post->repostOf->user->initials() }}
                                    @endif
                                </div>
                                <span
                                    class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $post->repostOf->user->name }}</span>
                                <span class="text-[10px] text-zinc-400">•
                                    {{ $post->repostOf->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 mb-2">
                                @if ($post->repostOf->price_min || $post->repostOf->price_max)
                                    <div
                                        class="inline-flex items-center gap-1 bg-emerald-500 text-white px-2 py-0.5 rounded-full text-[10px] font-bold">
                                        <flux:icon name="currency-dollar" class="size-3" />
                                        @if ($post->repostOf->price_min && $post->repostOf->price_max)
                                            <span>{{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_min, 0) }}
                                                -
                                                {{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_max, 0) }}</span>
                                        @elseif ($post->repostOf->price_min)
                                            <span>From
                                                {{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_min, 0) }}</span>
                                        @else
                                            <span>Up to
                                                {{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_max, 0) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2 mb-2 whitespace-pre-wrap">
                                {!! $post->repostOf->formatted_content !!}
                            </p>
                            @if ($post->repostOf->images)
                                @php $originImages = array_filter(explode(',', $post->repostOf->images)); @endphp
                                @if (count($originImages) > 0)
                                    <div class="h-32 rounded-lg overflow-hidden border border-zinc-200/50">
                                        <img src="{{ route('images.show', ['path' => trim($originImages[0])]) }}"
                                            class="size-full object-cover">
                                    </div>
                                @endif
                            @elseif($post->repostOf->video)
                                <div
                                    class="h-32 rounded-lg overflow-hidden bg-black flex items-center justify-center border border-zinc-200/50">
                                    <video src="{{ route('videos.show', ['path' => $post->repostOf->video]) }}"
                                        class="w-full h-full object-cover" controls controlsList="nodownload"
                                        playsinline preload="metadata"></video>
                                </div>
                            @endif
                        </a>
                    </div>
                @endif

                <!-- Stats & Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-zinc-50 dark:border-zinc-800/50">
                    <div class="flex items-center gap-6">
                        <button wire:click="toggleLike"
                            @click="new Audio('{{ asset('assets/mixkit-cartoon-toy-whistle-616.wav') }}').play()"
                            class="flex items-center gap-1.5 transition-colors group {{ auth()->check() && $post->isLikedBy(auth()->user()) ? 'text-red-500' : 'text-zinc-500 hover:text-red-500' }}">
                            @if (auth()->check() && $post->isLikedBy(auth()->user()))
                                <svg class="size-5 fill-current" viewBox="0 0 24 24">
                                    <path
                                        d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                                </svg>
                            @else
                                <flux:icon name="heart" class="size-5" />
                            @endif
                            <span class="text-sm font-medium">{{ $post->likes_count }}</span>
                        </button>
                        <span class="flex items-center gap-1.5 text-zinc-500">
                            <flux:icon name="chat-bubble-left" class="size-5" />
                            <span class="text-sm font-medium">{{ $post->all_comments_count }}</span>
                        </span>

                        <button x-data="{
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
                        }" @click="share()"
                            class="flex items-center gap-1.5 transition-colors relative"
                            :class="copied ? 'text-green-500' : 'text-zinc-500 hover:text-green-500'">
                            <flux:icon name="share" class="size-5" />
                            <span x-show="copied" x-transition
                                class="absolute -top-8 left-1/2 -translate-x-1/2 bg-zinc-800 text-white text-[10px] px-2 py-1 rounded shadow-lg whitespace-nowrap">
                                {{ __('Link Copied!') }}
                            </span>
                        </button>

                        @if (auth()->check() && $post->user_id !== auth()->id())
                            <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate
                                class="flex items-center gap-1.5 border border-[var(--color-brand-purple)] text-[var(--color-brand-purple)] px-4 py-1.5 rounded-full text-xs font-semibold hover:bg-[var(--color-brand-purple)] hover:text-white transition-all">
                                <flux:icon name="chat-bubble-left-right" class="size-3.5" />
                                {{ __('Chat') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="space-y-6 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <h4 class="font-bold text-zinc-900 dark:text-zinc-100">{{ __('Comments') }}</h4>

                <div class="space-y-6">
                    @forelse($post->comments as $comment)
                        <div class="flex gap-3">
                            <div
                                class="size-8 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-600 font-bold text-xs shrink-0 overflow-hidden">
                                @if ($comment->user->profile_picture_url)
                                    <img src="{{ $comment->user->profile_picture_url }}"
                                        class="size-full object-cover">
                                @else
                                    {{ $comment->user->initials() }}
                                @endif
                            </div>
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $comment->user->name }}</span>
                                    <span
                                        class="text-[10px] text-zinc-400">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ htmlspecialchars_decode($comment->content, ENT_QUOTES) }}
                                </p>
                                @if (!$post->challenge_id && auth()->check())
                                    <button wire:click="setReplyTo({{ $comment->id }})"
                                        class="text-[10px] font-bold text-[var(--color-brand-purple)] hover:underline">
                                        {{ __('Reply') }}
                                    </button>
                                    @if (auth()->id() === $comment->user_id)
                                        <button wire:click="deleteComment({{ $comment->id }})"
                                            wire:confirm="{{ __('Delete comment?') }}"
                                            class="text-[10px] font-bold text-red-500 hover:underline ml-3">
                                            {{ __('Delete') }}
                                        </button>
                                    @endif
                                @endif

                                <!-- Replies -->
                                @if ($comment->replies->count() > 0)
                                    <div class="mt-4 space-y-4 pl-4 border-l-2 border-zinc-50 dark:border-zinc-800">
                                        @foreach ($comment->replies as $reply)
                                            <div class="flex gap-2">
                                                <div
                                                    class="size-6 rounded-full bg-zinc-50 flex items-center justify-center text-zinc-500 font-bold text-[8px] shrink-0 overflow-hidden">
                                                    @if ($reply->user->profile_picture_url)
                                                        <img src="{{ $reply->user->profile_picture_url }}"
                                                            class="size-full object-cover">
                                                    @else
                                                        {{ $reply->user->initials() }}
                                                    @endif
                                                </div>
                                                <div class="flex-1 space-y-0.5">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $reply->user->name }}</span>
                                                        <span
                                                            class="text-[8px] text-zinc-400">{{ $reply->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                                        {{ htmlspecialchars_decode($reply->content, ENT_QUOTES) }}
                                                    </p>
                                                    @if (auth()->id() === $reply->user_id)
                                                        <button wire:click="deleteComment({{ $reply->id }})"
                                                            wire:confirm="{{ __('Delete reply?') }}"
                                                            class="text-[8px] font-bold text-red-500 hover:underline">
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
                        <div class="text-center py-8">
                            <p class="text-sm text-zinc-500">
                                {{ __('No comments yet. Be the first to share your thoughts!') }}
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Footer (Comment Input) -->
        <div
            class="mt-4 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 sticky bottom-4 shadow-lg">
            @auth
                @if ($replyToId)
                    <div
                        class="flex items-center justify-between mb-2 px-2 py-1 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <span class="text-[10px] text-purple-700 dark:text-purple-300">
                            {{ __('Replying to') }} <span class="font-bold">{{ $replyToName }}</span>
                        </span>
                        <button wire:click="cancelReply" class="text-[10px] text-zinc-400 hover:text-red-500">
                            <flux:icon name="x-mark" class="size-3" />
                        </button>
                    </div>
                @endif
                <div class="flex items-center gap-2">
                    <div
                        class="size-8 rounded-full bg-[var(--color-brand-purple)]/10 flex items-center justify-center text-[var(--color-brand-purple)] text-xs font-bold shrink-0 overflow-hidden">
                        @if (auth()->user()->profile_picture_url)
                            <img src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ auth()->user()->initials() }}
                        @endif
                    </div>
                    <div class="flex-1 relative">
                        <!-- Quick Reaction Emojis -->
                        <div class="flex gap-2 mb-3 overflow-x-auto pb-1 scrollbar-hide">
                            @foreach (['🔥', '❤️', '👍', '😮', '😢', '😡'] as $emoji)
                                <button wire:click="sendQuickComment('{{ $emoji }}')"
                                    class="size-10 text-2xl hover:scale-125 transition-transform bg-zinc-50 dark:bg-zinc-800 rounded-full flex items-center justify-center cursor-pointer shrink-0 shadow-sm border border-zinc-100 dark:border-zinc-700">
                                    {{ $emoji }}
                                </button>
                            @endforeach
                        </div>

                        <!-- Comment Textarea Area -->
                        <div class="relative">
                            <textarea wire:model="commentContent" placeholder="{{ __('Write a comment...') }}" rows="1"
                                class="w-full bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 rounded-2xl pl-4 pr-12 py-3 text-sm focus:ring-1 focus:ring-[var(--color-brand-purple)] focus:border-[var(--color-brand-purple)] resize-none min-h-[46px] scrollbar-hide"
                                wire:keydown.enter.prevent="addComment"></textarea>

                            <button wire:click="addComment"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-brand-purple)] hover:scale-110 transition-transform p-1">
                                <flux:icon name="paper-airplane" class="size-5" />
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center text-sm text-zinc-500">
                    <a href="{{ route('login') }}"
                        class="font-bold text-[var(--color-brand-purple)] hover:underline">{{ __('Log in') }}</a>
                    {{ __('to allow comment') }}
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
</div>
