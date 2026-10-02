<?php

use App\Models\Post;
use App\Models\Comment;
use App\Models\Conversation;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public ?Post $post = null;
    public string $commentContent = '';
    public ?int $replyToId = null;
    public string $replyToName = '';

    #[On('open-post-detail')]
    public function loadPost($postId)
    {
        $this->post = Post::with([
            'user',
            'repostOf.user',
            'comments.user',
            'comments.replies.user',
            'bookmarks' => function ($query) {
                $query->where('user_id', auth()->id());
            },
        ])
            ->withCount(['likes', 'allComments'])
            ->find($postId);

        $this->dispatch('modal-show', name: 'post-detail-drawer');
    }

    public function addComment()
    {
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
        $this->loadPost($this->post->id);

        // Notify feed to update comment counts
        $this->dispatch('comment-added');
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
        $user = auth()->user();
        $existingBookmark = $this->post->bookmarks()->where('user_id', $user->id)->first();

        if ($existingBookmark) {
            $existingBookmark->delete();
        } else {
            $this->post->bookmarks()->create(['user_id' => $user->id]);
        }

        $this->loadPost($this->post->id);
        $this->dispatch('post-bookmarked'); // Sync with feed
    }

    public function toggleLike()
    {
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

        $this->loadPost($this->post->id);
        $this->dispatch('post-liked'); // Sync with feed
    }

    public function deletePost()
    {
        if ($this->post && $this->post->user_id === auth()->id()) {
            $this->post->delete();
            $this->post = null;
            $this->dispatch('modal-close', name: 'post-detail-drawer');
            $this->dispatch('post-deleted');
            $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Post has been deleted.');
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'You cannot delete this post.');
        }
    }

    public function startConversation()
    {
        $userId = $this->post->user->id;
        $authId = auth()->id();

        // Find conversation with both users
        $conversation = auth()
            ->user()
            ->conversations()
            ->whereHas('users', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create();
            $conversation->users()->attach([$authId, $userId]);
        }

        return $this->redirect(route('chat', $conversation->id), navigate: true);
    }

    public $showReportModal = false;
    public $reportReason = '';
    public bool $showAdminDeletePostModal = false;
    public string $adminDeletePostReason = '';
    public bool $showAdminDeleteCommentModal = false;
    public ?int $adminDeleteCommentId = null;
    public string $adminDeleteCommentReason = '';

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

        \App\Models\Report::create([
            'user_id' => auth()->id(),
            'post_id' => $this->post->id,
            'reason' => $this->reportReason,
            'status' => 'pending',
        ]);

        $this->showReportModal = false;
        $this->dispatch('toast', type: 'success', title: 'Report Submitted', message: 'Thank you for reporting. We will review this post.');
    }

    public function openAdminDeletePostModal()
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) { $this->dispatch('toast', type:'error', title:'Unauthorized', message:'Admin only.'); return; }
        $this->adminDeletePostReason = '';
        $this->showAdminDeletePostModal = true;
    }
    public function adminDeletePost()
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) { $this->dispatch('toast', type:'error', title:'Unauthorized', message:'Admin only.'); return; }
        $this->validate(['adminDeletePostReason' => 'required|string|min:10|max:500']);
        if (!$this->post) return;
        $owner = $this->post->user; $excerpt = \Illuminate\Support\Str::limit($this->post->content ?? '', 500); $reason = $this->adminDeletePostReason;
        $this->post->delete(); $this->post = null;
        try { if ($owner && $owner->email) \Illuminate\Support\Facades\Mail::to($owner->email)->send(new \App\Mail\PostDeletedMail($owner, $excerpt, $reason)); } catch (\Throwable $e) { \Log::error('PostDeletedMail failed: '.$e->getMessage()); }
        $this->showAdminDeletePostModal = false;
        $this->dispatch('modal-close', name: 'post-detail-drawer');
        $this->dispatch('post-deleted');
        $this->dispatch('toast', type: 'success', title: 'Post Deleted', message: 'Post removed and owner notified.');
    }
    public function openAdminDeleteCommentModal($commentId)
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) { $this->dispatch('toast', type:'error', title:'Unauthorized', message:'Admin only.'); return; }
        $this->adminDeleteCommentId = (int) $commentId;
        $this->adminDeleteCommentReason = '';
        $this->showAdminDeleteCommentModal = true;
    }
    public function adminDeleteComment()
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) { $this->dispatch('toast', type:'error', title:'Unauthorized', message:'Admin only.'); return; }
        $this->validate(['adminDeleteCommentReason' => 'required|string|min:10|max:500']);
        $comment = Comment::with('user')->find($this->adminDeleteCommentId);
        if (!$comment) { $this->dispatch('toast', type:'error', title:'Error', message:'Comment not found.'); $this->showAdminDeleteCommentModal=false; return; }
        $owner = $comment->user; $excerpt = $comment->content; $reason = $this->adminDeleteCommentReason;
        $comment->delete();
        try { if ($owner && $owner->email) \Illuminate\Support\Facades\Mail::to($owner->email)->send(new \App\Mail\CommentDeletedMail($owner, $excerpt, $reason)); } catch (\Throwable $e) { \Log::error('CommentDeletedMail failed: '.$e->getMessage()); }
        $this->showAdminDeleteCommentModal = false; $this->adminDeleteCommentId = null;
        if ($this->post) $this->loadPost($this->post->id);
        $this->dispatch('comment-added');
        $this->dispatch('toast', type: 'success', title: 'Comment Deleted', message: 'Comment removed and owner notified.');
    }
}; ?>

<div>
    <flux:modal name="post-detail-drawer" variant="flyout" class="w-full sm:max-w-xl p-0">
        @if ($post)
            <div class="h-full flex flex-col bg-white dark:bg-zinc-900 overflow-hidden">
                <!-- Header -->
                <div
                    class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between sticky top-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md z-10">
                    <h2 class="font-bold text-lg text-zinc-900 dark:text-zinc-100">{{ __('Post Details') }}</h2>
                    <!-- <flux:modal.close>
                                                            <flux:button variant="ghost" icon="x-mark" size="sm" />
                                                        </flux:modal.close> -->
                </div>

                <!-- Content Area -->
                <div class="flex-1 overflow-y-auto p-4 space-y-6">
                    <!-- Original Post -->
                    <div class="space-y-4 relative">
                        @if ($post?->repost_of_id)
                            <div class="absolute left-[-10px] top-12 bottom-[100px] w-0.5 bg-[#6a11cb] opacity-50 z-0">
                            </div>
                        @endif
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-3 relative z-10">
                                <a @if (auth()->id() !== $post->user_id) href="{{ route('artisan.profile', $post->user) }}"
                                wire:navigate @endif
                                    @click.stop
                                    class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 font-bold text-sm overflow-hidden @if (auth()->id() !== $post->user_id) cursor-pointer transition-all @endif">
                                    @if ($post->user->profile_picture_url)
                                        <img src="{{ $post->user->profile_picture_url }}"
                                            class="size-full object-cover">
                                    @else
                                        {{ $post->user->initials() }}
                                    @endif
                                </a>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 @if (auth()->id() !== $post->user_id) @click.stop="window.location.href='{{ route('artisan.profile', $post->user) }}'" @endif
                                            class="font-bold text-zinc-900 dark:text-zinc-100 @if (auth()->id() !== $post->user_id) hover:text-[var(--color-brand-purple)] cursor-pointer @endif">
                                            {{ $post->user->username }}
                                        </h3>
                                        @if ($post->repost_of_id)
                                            <div class="flex items-center gap-1 text-xs text-zinc-500 font-medium">
                                                <flux:icon name="arrow-path-rounded-square" class="size-3.5" />
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
                                    @if ($post->user_id === auth()->id())
                                        <flux:menu.item wire:click="deletePost"
                                            wire:confirm="{{ __('Are you sure you want to delete this post?') }}"
                                            icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>
                                    @else
                                        <flux:menu.item wire:click="openReportModal" icon="flag">{{ __('Report') }}</flux:menu.item>
                                    @endif
                                    @if(auth()->check() && auth()->user()->isAdmin())
                                        <flux:menu.separator />
                                        @if($post->user_id !== auth()->id())
                                            <flux:menu.item x-data @click="$dispatch('open-ban-modal', {userId: {{ $post->user_id }}, userName: '{{ addslashes($post->user->name) }}'})" icon="no-symbol">{{ $post->user->isBanned() ? __('Extend Ban') : __('Ban User') }}</flux:menu.item>
                                        @endif
                                        <flux:menu.item wire:click="openAdminDeletePostModal" icon="trash" variant="danger">{{ __('Delete Post (Admin)') }}</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </div>

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

                        <p class="text-zinc-700 dark:text-zinc-300 text-sm leading-relaxed whitespace-pre-line break-words">
                            {!! $post->formatted_content !!}
                        </p>

                        <!-- Images -->
                        @if ($post->images)
                            @php
                                $imageArray = is_array($post->images) ? $post->images : array_filter(explode(',', (string)$post->images));
                                $imageUrls = array_map(fn($img) => route('images.show', ['path' => trim($img)]), $imageArray);
                            @endphp
                            @if (count($imageArray) > 0)
                                <div class="space-y-2">
                                    @foreach ($imageArray as $index => $image)
                                        <div
                                            class="rounded-xl overflow-hidden border border-zinc-100 dark:border-zinc-800 cursor-pointer"
                                            @click="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: {{ $index }} })">
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
                            <div @click.stop="$dispatch('open-post-detail', { postId: {{ $post->repost_of_id }} })"
                                class="mb-3 border border-zinc-800 rounded-xl p-3 hover:bg-zinc-900/50 transition-colors cursor-pointer">
                                <div class="flex items-center gap-2 mb-2">
                                    <div
                                        class="size-6 rounded-full bg-zinc-100 flex items-center justify-center text-xs overflow-hidden">
                                        @if ($post->repostOf->user->profile_picture_url)
                                            <img src="{{ $post->repostOf->user->profile_picture_url }}"
                                                class="size-full object-cover">
                                        @else
                                            {{ $post->repostOf->user->initials() }}
                                        @endif
                                    </div>
                                    <span
                                        class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $post->repostOf->user->name }}</span>
                                    <span class="text-xs text-zinc-500">&bull;
                                        {{ $post->repostOf->created_at->diffForHumans() }}</span>
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
                                <p
                                    class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2 mb-2 whitespace-pre-wrap break-words">
                                    {!! $post->repostOf->formatted_content !!}</p>
                                @if ($post->repostOf->images)
                                    @php
                                        $originImages = is_array($post->repostOf->images) ? $post->repostOf->images : array_filter(explode(',', (string)$post->repostOf->images));
                                        $originUrls = array_map(fn($o) => route('images.show', ['path' => trim($o)]), $originImages);
                                    @endphp
                                    @if (count($originImages) > 0)
                                        <div class="h-32 rounded-lg overflow-hidden border border-zinc-200/50 cursor-pointer"
                                            @click="$dispatch('open-lightbox', { images: {{ Js::from($originUrls) }}, index: 0 })">
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
                            </div>
                        @endif

                        <!-- Stats & Actions -->
                        <div
                            class="flex items-center justify-between pt-4 border-t border-zinc-50 dark:border-zinc-800/50">
                            <div class="flex items-center gap-6">
                                <button wire:click="toggleLike"
                                    x-data="{
                                        liked: {{ $post->isLikedBy(auth()->user()) ? 'true' : 'false' }},
                                        count: {{ $post->likes_count ?? 0 }},
                                        hearts: [],
                                        burst() {
                                            const colors = ['text-rose-500','text-pink-400','text-amber-400','text-purple-500','text-sky-400','text-emerald-400','text-orange-400'];
                                            const pts = [[-16,-12],[16,-10],[-11,-19],[12,-17],[0,-22],[-7,-15],[9,-14]];
                                            this.hearts = pts.map(([x,y], i) => ({ id: Date.now()+i+Math.random(), x, y, color: colors[i % colors.length], delay: i*38 }));
                                            setTimeout(() => this.hearts = [], 760);
                                        }
                                    }"
                                    @click="const willLike = !liked; liked = willLike; count += willLike ? 1 : -1; if (willLike) burst()"
                                    class="relative flex items-center gap-1.5 transition-colors"
                                    :class="liked ? 'text-red-500' : 'text-zinc-400 dark:text-zinc-500 hover:text-red-500'">
                                    <span class="absolute inset-0 pointer-events-none overflow-visible" aria-hidden="true">
                                        <template x-for="h in hearts" :key="h.id">
                                            <span class="heart-burst absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2" :style="`--tx:${h.x}px; --ty:${h.y}px; animation-delay:${h.delay}ms`">
                                                <span :class="h.color"><flux:icon name="heart" variant="solid" class="size-2.5 drop-shadow-sm" /></span>
                                            </span>
                                        </template>
                                    </span>
                                    <span class="relative">
                                        <flux:icon name="heart" variant="solid" class="size-[18px]" />
                                    </span>
                                    <span class="text-xs" x-text="count">{{ $post->likes_count }}</span>
                                </button>
                                <span class="flex items-center gap-1.5 text-zinc-500">
                                    <flux:icon name="chat-bubble-left" class="size-[18px]" />
                                    <span class="text-xs">{{ $post->all_comments_count }}</span>
                                </span>
                                @if ($post->user_id !== auth()->id())
                                    <button wire:click="startConversation"
                                        class="flex items-center gap-1.5 text-zinc-500 hover:text-purple-500 transition-colors">
                                        <flux:icon name="chat-bubble-left-right" class="size-[18px]" />
                                    </button>
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
                                        class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 font-bold text-xs shrink-0 overflow-hidden">
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
                                                class="text-xs text-zinc-500">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ htmlspecialchars_decode($comment->content, ENT_QUOTES) }}
                                        </p>
                                        <div class="flex items-center gap-3 flex-wrap">
                                            @if (!$post->challenge_id)
                                                <button wire:click="setReplyTo({{ $comment->id }})"
                                                    class="text-xs text-zinc-500 hover:text-purple-500 transition-colors">
                                                    {{ __('Reply') }}
                                                </button>
                                            @endif
                                            @if(auth()->check() && auth()->user()->isAdmin())
                                                <button wire:click="openAdminDeleteCommentModal({{ $comment->id }})" class="text-xs text-red-500 hover:underline flex items-center gap-1"><flux:icon name="trash" class="size-3" /> {{ __('Delete') }}</button>
                                                @if($comment->user_id !== auth()->id())
                                                    <button x-data @click="$dispatch('open-ban-modal', {userId: {{ $comment->user_id }}, userName: '{{ addslashes($comment->user->name) }}'})" class="text-xs text-amber-600 hover:underline flex items-center gap-1"><flux:icon name="no-symbol" class="size-3" /> {{ $comment->user->isBanned() ? __('Extend Ban') : __('Ban') }}</button>
                                                @endif
                                            @endif
                                        </div>

                                        <!-- Replies -->
                                        @if ($comment->replies->count() > 0)
                                            <div
                                                class="mt-4 space-y-4 pl-4 border-l-2 border-zinc-50 dark:border-zinc-800">
                                                @foreach ($comment->replies as $reply)
                                                    <div class="flex gap-2">
                                                    <div
                                                        class="size-7 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 font-bold text-xs shrink-0 overflow-hidden">
                                                        @if ($reply->user->profile_picture_url)
                                                            <img src="{{ $reply->user->profile_picture_url }}"
                                                                class="size-full object-cover">
                                                        @else
                                                            {{ $reply->user->initials() }}
                                                        @endif
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <span
                                                                class="font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $reply->user->name }}</span>
                                                            <span
                                                                class="text-xs text-zinc-500">{{ $reply->created_at->diffForHumans() }}</span>
                                                        </div>
                                                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">
                                                            {{ htmlspecialchars_decode($reply->content, ENT_QUOTES) }}
                                                        </p>
                                                        @if(auth()->check() && auth()->user()->isAdmin())
                                                            <div class="flex items-center gap-2 mt-1">
                                                                <button wire:click="openAdminDeleteCommentModal({{ $reply->id }})" class="text-xs text-red-500 hover:underline flex items-center gap-1"><flux:icon name="trash" class="size-3" /> {{ __('Delete') }}</button>
                                                                @if($reply->user_id !== auth()->id())
                                                                    <button x-data @click="$dispatch('open-ban-modal', {userId: {{ $reply->user_id }}, userName: '{{ addslashes($reply->user->name) }}'})" class="text-xs text-amber-600 hover:underline flex items-center gap-1"><flux:icon name="no-symbol" class="size-3" /> {{ $reply->user->isBanned() ? __('Extend Ban') : __('Ban') }}</button>
                                                                @endif
                                                            </div>
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
                <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30">
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
                    <div class="flex items-center gap-2">
                        <div
                            class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 text-xs font-bold shrink-0 overflow-hidden">
                            @if (auth()->user()->profile_picture_url)
                                <img src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                            @else
                                {{ auth()->user()->initials() }}
                            @endif
                        </div>
                        <div class="flex-1 flex items-center gap-2 bg-zinc-100 dark:bg-zinc-900 rounded-full px-4 py-2 border border-zinc-200 dark:border-zinc-800 focus-within:border-purple-500 transition-colors">
                            <input wire:model="commentContent" type="text"
                                placeholder="{{ __('Write a comment...') }}"
                                class="flex-1 bg-transparent border-0 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:ring-0 outline-none"
                                wire:keydown.enter="addComment">
                            <button wire:click="addComment"
                                class="text-purple-500 hover:text-purple-400 transition-colors p-1 shrink-0">
                                <flux:icon name="paper-airplane" class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Admin Delete Post Modal -->
    <flux:modal wire:model="showAdminDeletePostModal" class="sm:max-w-lg">
        <div class="space-y-6">
            <div><flux:heading size="lg">{{ __('Delete Post') }}</flux:heading><flux:subheading>{{ __('Remove this post and notify owner by email.') }}</flux:subheading></div>
            <div>
                <flux:label>{{ __('Reason') }} *</flux:label>
                <flux:textarea wire:model="adminDeletePostReason" rows="3" placeholder="{{ __('Why — owner will receive by email') }}" />
                @error('adminDeletePostReason') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2 justify-end">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="adminDeletePost">{{ __('Delete & Notify') }}</flux:button>
            </div>
        </div>
    </flux:modal>
    <!-- Admin Delete Comment Modal -->
    <flux:modal wire:model="showAdminDeleteCommentModal" class="sm:max-w-lg">
        <div class="space-y-6">
            <div><flux:heading size="lg">{{ __('Delete Comment') }}</flux:heading><flux:subheading>{{ __('Remove and notify author.') }}</flux:subheading></div>
            <div>
                <flux:label>{{ __('Reason') }} *</flux:label>
                <flux:textarea wire:model="adminDeleteCommentReason" rows="3" placeholder="{{ __('Reason — author will receive by email') }}" />
                @error('adminDeleteCommentReason') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2 justify-end">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="adminDeleteComment">{{ __('Delete & Notify') }}</flux:button>
            </div>
        </div>
    </flux:modal>

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
