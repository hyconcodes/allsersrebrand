<?php

use Livewire\Volt\Component;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Report;

new class extends Component {
    public Post $post;
    public $commentContent = '';

    public $showReportModal = false;
    public $reportReason = '';

    public function toggleLike()
    {
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

        $this->post->refresh();
        $this->dispatch('post-liked');
    }

    public function toggleBookmark()
    {
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

        $this->post->refresh();
        $this->dispatch('post-bookmarked');
    }

    public function addComment()
    {
        $user = auth()->user();
        if (!$user || empty(trim($this->commentContent))) {
            return;
        }

        $comment = Comment::create([
            'user_id' => $user->id,
            'post_id' => $this->post->id,
            'parent_id' => null,
            'content' => trim($this->commentContent),
        ]);

        if ($this->post->user_id !== $user->id) {
            $this->post->user->notify(new \App\Notifications\CommentAdded($this->post, $user, $comment));
        }

        $this->commentContent = '';
        $this->dispatch('comment-added');
    }

    public function redirectToPost()
    {
        $this->redirect(route('posts.show', $this->post), navigate: true);
    }

    public function navigateToRepost()
    {
        if ($this->post->repostOf) {
            $this->redirect(route('posts.show', $this->post->repostOf), navigate: true);
        }
    }

    public function startConversation()
    {
        $userId = $this->post->user->id;
        $authId = auth()->id();

        $conversation = \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $authId) {
            $existing = auth()
                ->user()
                ->conversations()
                ->whereHas('users', function ($query) use ($userId) {
                    $query->where('users.id', $userId);
                })
                ->first();

            if ($existing) {
                return $existing;
            }

            $conversation = Conversation::create();
            $conversation->users()->attach([$authId, $userId]);
            return $conversation;
        });

        return $this->redirect(route('chat', $conversation->id), navigate: true);
    }

    public function openReportModal()
    {
        $this->showReportModal = true;
        $this->reportReason = '';
    }

    public function submitReport()
    {
        $this->validate([
            'reportReason' => 'required|string|min:10|max:500',
        ]);

        Report::create([
            'post_id' => $this->post->id,
            'user_id' => auth()->id(),
            'reason' => $this->reportReason,
        ]);

        $this->showReportModal = false;
        $this->reportReason = '';

        $this->dispatch('toast', type: 'success', title: 'Report Submitted', message: 'Thank you for reporting. We will review this post.');
    }
}; ?>

<div class="px-4 sm:px-5 py-4 border-b border-zinc-200/60 dark:border-zinc-800/80 hover:bg-zinc-50/60 dark:hover:bg-zinc-900/40 relative transition-colors cursor-pointer group/card"
    @if (auth()->id() !== $post->user_id) @click="window.location.href='{{ route('posts.show', $post) }}'" @endif>
    
    @if ($post->repost_of_id)
        <div class="absolute left-[26px] top-[60px] bottom-[52px] w-0.5 bg-purple-500/30 z-0"></div>
    @endif

    <div class="flex items-start gap-3 sm:gap-3.5 relative z-10" @if ($post->user_id === auth()->id()) wire:click.stop="redirectToPost" @endif>
        <!-- User Avatar -->
        <a @if (auth()->id() !== $post->user_id) href="{{ route('artisan.profile', $post->user) }}" wire:navigate @endif
            @click.stop
            class="shrink-0 size-10 sm:size-11 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 font-bold text-sm overflow-hidden ring-1 ring-black/5 dark:ring-white/10 hover:opacity-90 transition-opacity">
            @if ($post->user->profile_picture_url)
                <img loading="lazy" src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
            @else
                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">{{ $post->user->initials() }}</span>
            @endif
        </a>

        <!-- Main Content Area -->
        <div class="flex-1 min-w-0">
            <!-- Header (User name, handle, work, timestamp) -->
            <div class="flex items-center justify-between gap-2 mb-1">
                <div class="flex items-center gap-1.5 text-sm min-w-0 flex-wrap leading-none">
                    <span class="font-bold text-zinc-900 dark:text-zinc-100 hover:underline truncate">{{ $post->user->name }}</span>
                    <span class="text-xs text-zinc-500 truncate">@<span>{{ $post->user->username }}</span></span>
                    
                    @if ($post->user->work && !$post->repost_of_id)
                        <span class="text-xs text-purple-500 font-medium px-2 py-0.5 rounded-full bg-purple-500/10 border border-purple-500/20">
                            {{ $post->user->work }}
                        </span>
                    @endif

                    <span class="text-xs text-zinc-500">· {{ $post->created_at->diffForHumans(null, true) }}</span>

                    @if ($post->repost_of_id)
                        <span class="inline-flex items-center gap-1 text-xs text-zinc-400 font-medium ml-1">
                            <flux:icon name="arrow-path-rounded-square" class="size-3 text-purple-400 shrink-0" />
                            <span>{{ __('reposted') }}</span>
                        </span>
                    @endif
                </div>

                <!-- Three-dot More Menu (top right) -->
                @auth
                    <div class="relative shrink-0" @click.stop>
                        <flux:dropdown position="bottom-end">
                            <button class="p-1.5 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors opacity-0 group-hover/card:opacity-100">
                                <flux:icon name="ellipsis-horizontal" class="size-4" />
                            </button>

                            <flux:menu class="min-w-[160px]">
                                <flux:menu.item x-data x-on:click="
                                    const url = '{{ route('posts.show', $post) }}';
                                    if (navigator.share) { navigator.share({ url }).catch(() => {}); }
                                    else { navigator.clipboard.writeText(url).then(() => { $wire.dispatch('toast', { type: 'success', title: 'Copied!', message: 'Link copied to clipboard.' }); }); }
                                " icon="share">
                                    {{ __('Share') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="openReportModal" icon="flag">
                                    {{ __('Report') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                @endauth
            </div>

            <!-- Price Badge (if present) -->
            @if ($post->price_min || $post->price_max)
                <div class="mb-2.5">
                    <div class="inline-flex items-center gap-1.5 bg-purple-500/10 border border-purple-500/20 text-purple-400 text-xs font-semibold px-2.5 py-0.5 rounded-full shadow-sm">
                        <flux:icon name="currency-dollar" class="size-3.5" />
                        @if ($post->price_min && $post->price_max)
                            <span>{{ $post->user->currency_symbol }}{{ number_format($post->price_min, 0) }} – {{ $post->user->currency_symbol }}{{ number_format($post->price_max, 0) }}</span>
                        @elseif ($post->price_min)
                            <span>{{ __('From') }} {{ $post->user->currency_symbol }}{{ number_format($post->price_min, 0) }}</span>
                        @else
                            <span>{{ __('Up to') }} {{ $post->user->currency_symbol }}{{ number_format($post->price_max, 0) }}</span>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Post Text Content -->
            @if ($post->content)
                @if (Str::length($post->content) > 300)
                    <div x-data="{ expanded: false }" class="mb-3" @click.stop>
                        <p x-show="!expanded" class="text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed whitespace-pre-line break-words">
                            {!! $post->formatted_content_summary !!}
                        </p>
                        <p x-show="expanded" class="text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed whitespace-pre-line break-words">
                            {!! $post->formatted_content !!}
                        </p>
                        <button x-show="!expanded" @click="window.location.href='{{ route('posts.show', $post) }}'"
                            class="text-xs font-semibold text-purple-400 hover:underline mt-1">{{ __('See More') }}</button>
                    </div>
                @else
                    <div class="mb-3">
                        <p class="text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed whitespace-pre-line break-words">
                            {!! $post->formatted_content !!}
                        </p>
                    </div>
                @endif
            @endif

            <!-- Images Grid -->
            @if ($post->images)
                @php
                    $imageArray = is_array($post->images)
                        ? $post->images
                        : array_filter(explode(',', (string) $post->images));
                    $imageUrls = array_map(fn($img) => route('images.show', ['path' => trim($img)]), $imageArray);
                @endphp
                @if (count($imageArray) > 0)
                    <div class="mb-3 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800/80 shadow-sm">
                        @if (count($imageArray) === 1)
                            <div class="max-h-[400px] overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                @click="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 0 })">
                                <img loading="lazy" src="{{ route('images.show', ['path' => trim($imageArray[0])]) }}" alt="Post image"
                                    class="w-full h-auto max-h-[400px] object-cover hover:scale-[1.01] transition-transform duration-300">
                            </div>
                        @else
                            <div class="grid gap-0.5 @if (count($imageArray) === 2) grid-cols-2 @elseif(count($imageArray) >= 3) grid-cols-2 @endif">
                                @foreach ($imageArray as $index => $image)
                                    <div class="overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer @if (count($imageArray) === 3 && $index === 0) row-span-2 @endif"
                                        @click="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: {{ $index }} })">
                                        <img loading="lazy" src="{{ route('images.show', ['path' => trim($image)]) }}" alt="Post image"
                                            class="w-full h-full object-cover min-h-[160px] max-h-[260px] hover:scale-105 transition-transform duration-300">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            <!-- Video Player -->
            @if ($post->video)
                <div class="mb-3 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800/80 max-h-[400px] bg-black shadow-sm">
                    <video src="{{ route('videos.show', ['path' => $post->video]) }}" class="w-full h-auto max-h-[400px] object-cover"
                        controls controlsList="nodownload" playsinline preload="metadata"></video>
                </div>
            @endif

            <!-- Original Post Preview (Repost) -->
            @if ($post->repostOf)
                <div @click.stop="window.location.href='{{ route('posts.show', $post->repostOf) }}'"
                    class="mb-3 border border-zinc-200 dark:border-zinc-800/80 rounded-2xl bg-zinc-50/60 dark:bg-zinc-900/60 hover:bg-zinc-100/80 dark:hover:bg-zinc-800/60 transition-colors cursor-pointer overflow-hidden shadow-sm">
                    <div class="p-3.5">
                        <div class="flex items-center gap-2 mb-1.5">
                            <div class="size-6 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs overflow-hidden shrink-0">
                                @if ($post->repostOf->user->profile_picture_url)
                                    <img loading="lazy" src="{{ $post->repostOf->user->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <span class="text-[10px] font-bold">{{ $post->repostOf->user->initials() }}</span>
                                @endif
                            </div>
                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $post->repostOf->user->name }}</span>
                            <span class="text-xs text-zinc-500">@<span>{{ $post->repostOf->user->username }}</span></span>
                            <span class="text-xs text-zinc-500">· {{ $post->repostOf->created_at->diffForHumans(null, true) }}</span>
                        </div>
                        @if ($post->repostOf->price_min || $post->repostOf->price_max)
                            <div class="inline-flex items-center gap-1 bg-purple-500/10 border border-purple-500/20 text-purple-400 text-[11px] font-semibold px-2 py-0.5 rounded-full mb-1.5">
                                <flux:icon name="currency-dollar" class="size-3" />
                                <span>{{ $post->repostOf->user->currency_symbol }}{{ number_format($post->repostOf->price_min ?: $post->repostOf->price_max, 0) }}</span>
                            </div>
                        @endif
                        <p class="text-xs text-zinc-700 dark:text-zinc-300 line-clamp-3 whitespace-pre-wrap break-words leading-relaxed">
                            {!! $post->repostOf->formatted_content !!}
                        </p>
                    </div>
                    @if ($post->repostOf->images)
                        @php
                            $originImages = is_array($post->repostOf->images)
                                ? $post->repostOf->images
                                : array_filter(explode(',', (string) $post->repostOf->images));
                            $originUrls = array_map(fn($o) => route('images.show', ['path' => trim($o)]), $originImages);
                        @endphp
                        @if (count($originImages) > 0)
                            <div class="h-32 overflow-hidden border-t border-zinc-200 dark:border-zinc-800/80 cursor-pointer"
                                @click="$dispatch('open-lightbox', { images: {{ Js::from($originUrls) }}, index: 0 })">
                                <img loading="lazy" src="{{ route('images.show', ['path' => trim($originImages[0])]) }}"
                                    class="w-full h-full object-cover">
                            </div>
                        @endif
                    @elseif($post->repostOf->video)
                        <div class="h-32 overflow-hidden bg-black border-t border-zinc-200 dark:border-zinc-800/80">
                            <video src="{{ route('videos.show', ['path' => $post->repostOf->video]) }}"
                                class="w-full h-full object-cover" controls controlsList="nodownload" playsinline
                                preload="metadata"></video>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Action Bar (Twitter/X Style Inside Content Column) -->
            <div class="flex items-center justify-between max-w-md pt-2 mt-1 text-zinc-500">
                <!-- Comment Button -->
                <button wire:click.stop="redirectToPost"
                    class="flex items-center gap-2 group text-xs hover:text-sky-500 transition-colors">
                    <div class="p-2 rounded-full group-hover:bg-sky-500/10 transition-colors">
                        <flux:icon name="chat-bubble-left" class="size-4" />
                    </div>
                    <span class="font-medium tabular-nums">{{ $post->all_comments_count ?? 0 }}</span>
                </button>

                <!-- Repost Button -->
                @if (auth()->check() && auth()->user()->isArtisan() && $post->canBeReposted())
                    <button wire:click.stop="$parent.openRepostModal({{ $post->id }})"
                        class="flex items-center gap-2 group text-xs hover:text-emerald-500 transition-colors"
                        title="{{ __('Repost Work') }}">
                        <div class="p-2 rounded-full group-hover:bg-emerald-500/10 transition-colors">
                            <flux:icon name="arrow-path-rounded-square" class="size-4" />
                        </div>
                    </button>
                @endif

                <!-- Like Button -->
                <button wire:click.stop="toggleLike"
                    x-data="{ liked: {{ $post->isLikedBy(auth()->user()) ? 'true' : 'false' }}, count: {{ $post->likes_count ?? 0 }} }"
                    @click="liked = !liked; count += liked ? 1 : -1"
                    class="flex items-center gap-2 group text-xs transition-colors"
                    :class="liked ? 'text-rose-500' : 'hover:text-rose-500'">
                    <div class="p-2 rounded-full group-hover:bg-rose-500/10 transition-colors">
                        <flux:icon name="heart" class="size-4 group-hover:scale-110 transition-transform" ::variant="liked ? 'solid' : 'outline'" />
                    </div>
                    <span class="font-medium tabular-nums" x-text="count">0</span>
                </button>

                <!-- Share Button -->
                <button x-data="{ copied: false }" @click.stop="
                    const url = '{{ route('posts.show', $post) }}';
                    if (navigator.share) { navigator.share({ url }).catch(() => {}); }
                    else { navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 2000); }); }
                " class="flex items-center gap-2 group text-xs hover:text-purple-500 transition-colors relative">
                    <div class="p-2 rounded-full group-hover:bg-purple-500/10 transition-colors">
                        <flux:icon name="share" class="size-4" />
                    </div>
                    <span x-show="copied" x-transition
                        class="absolute -top-8 left-1/2 -translate-x-1/2 bg-zinc-800 text-white text-[11px] font-medium px-2 py-1 rounded-md shadow-lg whitespace-nowrap z-50">
                        {{ __('Copied!') }}
                    </span>
                </button>

                <!-- Bookmark Button -->
                <button wire:click.stop="toggleBookmark"
                    x-data="{ bookmarked: {{ $post->isBookmarkedBy(auth()->user()) ? 'true' : 'false' }} }"
                    @click="bookmarked = !bookmarked"
                    class="flex items-center gap-2 group text-xs transition-colors"
                    :class="bookmarked ? 'text-amber-500' : 'hover:text-amber-500'">
                    <div class="p-2 rounded-full group-hover:bg-amber-500/10 transition-colors">
                        <flux:icon name="bookmark" class="size-4" ::variant="bookmarked ? 'solid' : 'outline'" />
                    </div>
                </button>

                <!-- Direct Message / Hire Button -->
                @if ($post->user_id !== auth()->id())
                    <button wire:click="startConversation" @click.stop
                        class="flex items-center gap-2 group text-xs hover:text-purple-500 transition-colors"
                        title="{{ __('Chat with Artisan') }}">
                        <div class="p-2 rounded-full group-hover:bg-purple-500/10 transition-colors">
                            <flux:icon name="chat-bubble-left-right" class="size-4" />
                        </div>
                    </button>
                @endif
            </div>

            <!-- Inline Comments -->
            @php $inlineComments = $post->comments->take(2); @endphp
            @if ($inlineComments->isNotEmpty())
                <div class="mt-3 space-y-3">
                    @foreach ($inlineComments as $comment)
                        <div class="flex items-start gap-2.5" @click.stop>
                            <div class="size-7 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 font-bold text-xs shrink-0 overflow-hidden ring-1 ring-black/5 dark:ring-white/10">
                                @if ($comment->user->profile_picture_url)
                                    <img loading="lazy" src="{{ $comment->user->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <span class="text-[10px] font-bold text-zinc-600 dark:text-zinc-300">{{ $comment->user->initials() }}</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate">{{ $comment->user->name }}</span>
                                    <span class="text-[11px] text-zinc-500 shrink-0">{{ $comment->created_at->diffForHumans(null, true) }}</span>
                                </div>
                                <p class="text-xs text-zinc-700 dark:text-zinc-400 leading-relaxed break-words whitespace-pre-wrap line-clamp-3">{{ $comment->content }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @auth
                @if ($post->all_comments_count > $inlineComments->count())
                    <div class="mt-2" @click.stop>
                        <a href="{{ route('posts.show', $post) }}" wire:navigate
                            class="text-xs font-semibold text-zinc-500 hover:text-purple-500 transition-colors">
                            {{ __('See all') }} {{ $post->all_comments_count }} {{ __('comments') }}
                        </a>
                    </div>
                @endif
                <div class="mt-2.5 flex items-center gap-2.5" @click.stop>
                    <div class="size-7 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 text-xs font-bold shrink-0 overflow-hidden ring-1 ring-black/5 dark:ring-white/10">
                        @if (auth()->user()->profile_picture_url)
                            <img loading="lazy" src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                        @else
                            <span class="text-[10px] font-bold text-zinc-600 dark:text-zinc-300">{{ auth()->user()->initials() }}</span>
                        @endif
                    </div>
                    <div class="flex-1 flex items-center bg-zinc-100 dark:bg-zinc-800/60 rounded-full px-3 py-1.5 border border-transparent focus-within:border-purple-500/50 transition-colors">
                        <input wire:model="commentContent" type="text"
                            placeholder="{{ __('Post a comment...') }}"
                            class="flex-1 bg-transparent border-0 text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-600 focus:ring-0 outline-none"
                            wire:keydown.enter="addComment">
                        <button wire:click="addComment"
                            class="text-purple-500 hover:text-purple-400 transition-colors p-0.5 shrink-0">
                            <flux:icon name="paper-airplane" class="size-3.5" />
                        </button>
                    </div>
                </div>
            @endauth
        </div>
    </div>

    <!-- Report Modal -->
    @if (auth()->check())
        <flux:modal wire:model="showReportModal" class="sm:max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Report Post') }}</flux:heading>
                    <flux:subheading>{{ __('Help us understand what is wrong with this post.') }}</flux:subheading>
                </div>

                <div class="space-y-4">
                    <flux:textarea wire:model="reportReason" rows="4"
                        placeholder="{{ __('Please describe why you are reporting this post (minimum 10 characters)...') }}" />

                    @error('reportReason')
                        <p class="text-red-500 text-xs">{{ $message }}</p>
                    @enderror

                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 text-sm text-zinc-600 dark:text-zinc-400">
                        <p class="font-medium mb-2">{{ __('Common reasons for reporting:') }}</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>{{ __('Spam or misleading content') }}</li>
                            <li>{{ __('Harassment or hate speech') }}</li>
                            <li>{{ __('Violence or dangerous content') }}</li>
                            <li>{{ __('Inappropriate or offensive material') }}</li>
                            <li>{{ __('Copyright infringement') }}</li>
                        </ul>
                    </div>
                </div>

                <div class="flex gap-2 justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="submitReport" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitReport">{{ __('Submit Report') }}</span>
                        <span wire:loading wire:target="submitReport">{{ __('Submitting...') }}</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
