<?php

use App\Models\ChallengeRating;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Traits\HandlesComments;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    use HandlesComments;

    public Post $post;

    public $isJudge = false;

    public $userRating = 0;

    public bool $showAllComments = false;

    public bool $editingPost = false;

    public string $editingPostContent = '';

    public $showReportModal = false;

    public $reportReason = '';

    public bool $showAdminDeletePostModal = false;

    public string $adminDeletePostReason = '';

    public bool $showAdminDeleteCommentModal = false;

    public ?int $adminDeleteCommentId = null;

    public string $adminDeleteCommentReason = '';

    public function mount($post, $isJudge = false)
    {
        $this->post = $post;
        $this->isJudge = $isJudge;

        if (auth()->check()) {
            $ratingRecord = ChallengeRating::where('post_id', $post->id)
                ->where('user_id', auth()->id())
                ->first();
            $this->userRating = $ratingRecord ? $ratingRecord->rating : 0;
        }
    }

    public function setRating($stars)
    {
        if (! $this->isJudge) {
            return;
        }

        ChallengeRating::updateOrCreate(['post_id' => $this->post->id, 'user_id' => auth()->id()], ['rating' => $stars]);

        $this->userRating = $stars;
        $this->dispatch('toast', type: 'success', title: 'Submitted', message: 'Your star rating has been recorded!');

        // Refresh post to update average
        $this->post->load('ratings');
    }

    public function toggleLike()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        $existingLike = $this->post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
        } else {
            $this->post
                ->likes()
                ->where('user_id', $user->id)
                ->delete();
            $this->post->likes()->create(['user_id' => $user->id]);
        }

        $this->post->refresh();
        $this->post->loadCount('likes');
    }

    public function toggleBookmark()
    {
        if (! auth()->check()) {
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

        $this->post->refresh();
    }

    protected function refreshComments(): void
    {
        $this->post->setRelation('comments', $this->post->comments()->latest()->with(['user', 'replies.user'])->get());
        $this->post->loadCount(['allComments']);
    }

    public function toggleComments()
    {
        $this->showAllComments = ! $this->showAllComments;

        if ($this->showAllComments) {
            $this->refreshComments();
        }
    }

    public function expandComments()
    {
        $this->showAllComments = true;
        $this->refreshComments();
    }

    public function addComment()
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (empty(trim($this->commentContent)) && empty($this->commentImages) && ! $this->commentVideo) {
            return;
        }

        if ($this->replyToId) {
            $parent = Comment::find($this->replyToId);
            if (! $parent || $parent->post_id !== $this->post->id) {
                $this->replyToId = null;
                $this->replyToName = '';
            }
        }

        try {
            $validated = $this->validateCommentInput();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->toastCommentOversize($e);

            throw $e;
        }

        $media = $this->storeCommentMedia($validated);

        $comment = Comment::create([
            'user_id' => $user->id,
            'post_id' => $this->post->id,
            'parent_id' => $this->replyToId,
            'content' => trim($this->commentContent),
            'images' => $media['images'],
            'video' => $media['video'],
        ]);

        $this->notifyForComment($comment, $this->post, $user);

        $this->resetCommentComposer();
        $this->showAllComments = true;
        $this->refreshComments();
        $this->dispatch('comment-added');
    }

    public function updateComment()
    {
        $comment = Comment::find($this->editingCommentId);

        if (! $comment || $comment->user_id !== auth()->id()) {
            $this->dispatch('toast', type: 'error', title: 'Unauthorized', message: 'You can only edit your own comments.');

            return;
        }

        $this->validate([
            'editingCommentContent' => 'nullable|string|max:1000',
        ]);

        if (empty(trim((string) $this->editingCommentContent)) && empty($comment->images) && empty($comment->video)) {
            $this->addError('editingCommentContent', __('Add text or keep an attachment.'));

            return;
        }

        $comment->update(['content' => trim((string) $this->editingCommentContent)]);
        $this->cancelCommentEdit();
        $this->refreshComments();
        $this->dispatch('toast', type: 'success', title: 'Updated', message: 'Comment updated.');
    }

    public function deleteOwnComment($commentId)
    {
        $comment = Comment::find($commentId);

        if (! $comment || $comment->post_id !== $this->post->id || $comment->user_id !== auth()->id()) {
            $this->dispatch('toast', type: 'error', title: 'Unauthorized', message: 'You can only delete your own comments.');

            return;
        }

        $comment->delete();
        $this->refreshComments();
        $this->dispatch('comment-added');
        $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Comment deleted.');
    }

    public function startPostEdit()
    {
        if (! auth()->check() || $this->post->user_id !== auth()->id()) {
            $this->dispatch('toast', type: 'error', title: 'Unauthorized', message: 'You can only edit your own posts.');

            return;
        }

        $this->editingPost = true;
        $this->editingPostContent = $this->post->content;
    }

    public function cancelPostEdit()
    {
        $this->editingPost = false;
        $this->editingPostContent = '';
    }

    public function updatePost()
    {
        if (! auth()->check() || $this->post->user_id !== auth()->id()) {
            $this->dispatch('toast', type: 'error', title: 'Unauthorized', message: 'You can only edit your own posts.');

            return;
        }

        $this->validate([
            'editingPostContent' => 'nullable|string|max:1000',
        ]);

        if (empty(trim((string) $this->editingPostContent)) && empty($this->post->images) && empty($this->post->video)) {
            $this->addError('editingPostContent', __('Add text or keep an attachment.'));

            return;
        }

        $this->post->update(['content' => trim((string) $this->editingPostContent)]);
        $this->cancelPostEdit();
        $this->post->refresh();
        $this->dispatch('toast', type: 'success', title: 'Updated', message: 'Post updated.');
    }

    public function deletePost()
    {
        if (! auth()->check() || $this->post->user_id !== auth()->id()) {
            $this->dispatch('toast', type: 'error', title: 'Unauthorized', message: 'You can only delete your own posts.');

            return;
        }

        $this->post->delete();
        $this->dispatch('post-deleted');
        $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Post deleted.');
    }

    public function openReportModal()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $this->reportReason = '';
        $this->showReportModal = true;
    }

    public function submitReport()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

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

<div
    class="relative overflow-hidden group/card {{ $post->user->judgingChallenges()->where('challenges.id', $post->challenge_id)->where('challenge_judges.status', 'accepted')->exists() ? 'ring-2 ring-[var(--color-brand-purple)] ring-offset-4 dark:ring-offset-zinc-950' : '' }}">

    <div class="flex items-start gap-3 sm:gap-3.5 relative z-10 bg-white dark:bg-zinc-900 px-4 sm:px-5 py-4 transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-900/60">
        <!-- User Avatar + accent thread line (same as feed posts) -->
        <div class="flex flex-col items-center shrink-0 self-stretch">
            <a @if (auth()->id() !== $post->user_id) href="{{ route('artisan.profile', $post->user) }}" wire:navigate @endif
                @click.stop
                class="shrink-0 size-10 sm:size-11 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 font-bold text-sm overflow-hidden hover:opacity-90 transition-opacity"
                style="border: 2px solid {{ $post->user->accentColor() }}">
                @if ($post->user->profile_picture_url)
                    <img loading="lazy" src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                @else
                    <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">{{ $post->user->initials() }}</span>
                @endif
            </a>
            <div class="w-0.5 flex-1" style="background-color: {{ $post->user->accentColor() }}" aria-hidden="true"></div>
            <span class="size-5 shrink-0 rounded-full border-2 bg-white dark:bg-zinc-900 flex items-center justify-center overflow-hidden"
                style="border-color: {{ $post->user->accentColor() }}" aria-hidden="true">
                <img src="{{ asset('assets/allsers.png') }}" alt="" class="size-full object-cover">
            </span>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 min-w-0">
            <!-- Header (Username, work, timestamp, judge badge, pin) -->
            <div class="flex items-center justify-between gap-2 mb-1">
                <div class="flex items-center gap-1.5 text-sm min-w-0 flex-wrap leading-none">
                    <span class="font-bold text-zinc-900 dark:text-zinc-100 hover:underline truncate">{{ '@' . $post->user->username }}</span>

                    @if ($post->user->work)
                        <span title="{{ $post->user->work }}"
                            class="text-xs font-medium truncate"
                            style="color: {{ $post->user->accentColor() }};
                                   border: 1.5px dotted {{ $post->user->accentColor() }};
                                   background-color: {{ $post->user->accentColor() }}1a;
                                   padding: 0.25rem 0.5rem;
                                   border-radius: 9999px;
                                   display: inline-block;">
                            {{ $post->user->work }}
                        </span>
                    @endif

                    <span class="text-xs text-zinc-500">· {{ $post->created_at->diffForHumans(null, true) }}</span>

                    @if ($post->user->judgingChallenges()->where('challenges.id', $post->challenge_id)->where('challenge_judges.status', 'accepted')->exists())
                        <span
                            class="px-1.5 py-0.5 rounded-full bg-[var(--color-brand-purple)] text-[9px] font-bold text-white uppercase tracking-normal shrink-0">
                            {{ __('JUDGE') }}</span>
                    @endif
                </div>

                <!-- Pin Button (Only for Creator) + Author/Admin menu -->
                <div class="flex items-center gap-1 shrink-0">
                    @if (auth()->id() === $post->challenge->creator_id)
                        <button wire:click.stop="$parent.pinPost({{ $post->id }})"
                            class="shrink-0 p-1.5 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                            aria-label="{{ __('Pin to challenge') }}">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                fill="{{ $post->is_challenge_pinned ? 'currentColor' : 'none' }}" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                            </svg>
                        </button>
                    @endif

                    @if (auth()->check() && (auth()->id() === $post->user_id || auth()->user()->isAdmin()))
                        <flux:dropdown position="bottom" align="end">
                            <button type="button" @click.stop
                                class="shrink-0 p-1.5 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                                aria-label="{{ __('Post options') }}">
                                <flux:icon name="ellipsis-horizontal" class="size-4" />
                            </button>
                            <flux:menu>
                                @if (auth()->id() === $post->user_id)
                                    @unless ($editingPost)
                                        <flux:menu.item wire:click="startPostEdit" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                    @endunless
                                    <flux:menu.item wire:click="deletePost"
                                        wire:confirm="{{ __('Are you sure you want to delete this post?') }}"
                                        icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>
                                @endif
                                @if (auth()->id() !== $post->user_id)
                                    <flux:menu.item wire:click="openReportModal" icon="flag">{{ __('Report') }}</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            </div>

            <!-- Post Text Content (with inline edit) -->
            @if ($editingPost)
                <div class="mb-3" @click.stop>
                    <textarea wire:model="editingPostContent" rows="4" maxlength="1000"
                        class="w-full rounded-xl border border-purple-500/50 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-purple-500"></textarea>
                    @error('editingPostContent')
                        <span class="block text-xs text-red-500 mt-1">{{ $message }}</span>
                    @enderror
                    <div class="mt-2 flex items-center gap-2">
                        <button type="button" wire:click="updatePost"
                            class="rounded-full bg-[var(--color-brand-purple)] px-4 py-1.5 text-xs font-bold text-white transition-opacity hover:opacity-90">
                            {{ __('Save') }}
                        </button>
                        <button type="button" wire:click="cancelPostEdit"
                            class="rounded-full bg-zinc-200 dark:bg-zinc-800 px-4 py-1.5 text-xs font-bold text-zinc-600 dark:text-zinc-300 transition-colors hover:bg-zinc-300 dark:hover:bg-zinc-700">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </div>
            @elseif ($post->content)
                @if (Str::length($post->content) > 300)
                    <div x-data="{ expanded: false }" class="mb-3" @click.stop>
                        <p x-show="!expanded" class="text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed whitespace-pre-line break-words">
                            {!! $post->formatted_content_summary !!}
                        </p>
                        <p x-show="expanded" class="text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed whitespace-pre-line break-words">
                            {!! $post->formatted_content !!}
                        </p>
                        <button x-show="!expanded" @click="expanded = true"
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

            <!-- Media (images and/or video) — same display logic as feed posts -->
            @php
                $imageArray = $post->images
                    ? (is_array($post->images) ? $post->images : array_filter(explode(',', (string) $post->images)))
                    : [];
                $imageUrls = array_map(fn($img) => route('images.show', ['path' => trim($img)]), $imageArray);
                $imageCount = count($imageArray);
                $hasVideo = filled($post->video);
            @endphp

            @if ($imageCount > 0 && $hasVideo)
                <div class="mb-3 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800/80 shadow-sm" @click.stop>
                    @if ($imageCount === 1)
                        <div class="grid grid-cols-2 gap-0.5">
                            <div class="overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 0 })">
                                <img loading="lazy" src="{{ route('images.show', ['path' => trim($imageArray[0])]) }}" alt="Post image"
                                    class="w-full h-full object-cover min-h-[160px] max-h-[260px] hover:scale-105 transition-transform duration-300">
                            </div>
                            <div x-data="{ muted: true }" @click.stop class="relative overflow-hidden bg-black">
                                <video x-ref="video" data-autoplay muted src="{{ route('videos.show', ['path' => $post->video]) }}"
                                    class="w-full h-full object-cover min-h-[160px] max-h-[260px]"
                                    controls controlsList="nodownload" playsinline preload="metadata"></video>
                                <button type="button" @click.stop="muted = !muted; $refs.video.muted = muted"
                                    class="absolute top-2 right-2 z-10 flex items-center justify-center size-7 rounded-full bg-black/50 text-white backdrop-blur-sm hover:bg-black/70 transition-colors"
                                    :aria-label="muted ? 'Unmute video' : 'Mute video'">
                                    <flux:icon x-show="muted" name="speaker-x-mark" class="size-3.5" />
                                    <flux:icon x-show="!muted" name="speaker-wave" class="size-3.5" style="display: none;" />
                                </button>
                            </div>
                        </div>
                    @else
                        @php
                            $displayImages = array_slice($imageArray, 0, 2);
                            $remaining = $imageCount - 2;
                        @endphp
                        <div class="grid grid-cols-2 gap-0.5">
                            <div class="overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 0 })">
                                <img loading="lazy" src="{{ route('images.show', ['path' => trim($displayImages[0])]) }}" alt="Post image"
                                    class="w-full h-full object-cover min-h-[160px] max-h-[260px] hover:scale-105 transition-transform duration-300">
                            </div>
                            @if ($remaining > 0)
                                <div class="relative overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                    @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 1 })"
                                    aria-label="{{ __('Show :count more images', ['count' => $remaining]) }}">
                                    <img loading="lazy" src="{{ route('images.show', ['path' => trim($displayImages[1])]) }}" alt="Post image"
                                        class="w-full h-full object-cover min-h-[160px] max-h-[260px]">
                                    <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                        <span class="text-white text-xl sm:text-2xl font-extrabold tracking-tight">+{{ $remaining }}</span>
                                    </div>
                                </div>
                            @else
                                <div class="overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                    @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 1 })">
                                    <img loading="lazy" src="{{ route('images.show', ['path' => trim($displayImages[1])]) }}" alt="Post image"
                                        class="w-full h-full object-cover min-h-[160px] max-h-[260px] hover:scale-105 transition-transform duration-300">
                                </div>
                            @endif
                        </div>
                        <div x-data="{ muted: true }" @click.stop class="relative bg-black">
                            <video x-ref="video" data-autoplay muted src="{{ route('videos.show', ['path' => $post->video]) }}"
                                class="w-full h-auto max-h-[260px] object-cover"
                                controls controlsList="nodownload" playsinline preload="metadata"></video>
                            <button type="button" @click.stop="muted = !muted; $refs.video.muted = muted"
                                class="absolute top-2 right-2 z-10 flex items-center justify-center size-7 rounded-full bg-black/50 text-white backdrop-blur-sm hover:bg-black/70 transition-colors"
                                :aria-label="muted ? 'Unmute video' : 'Mute video'">
                                <flux:icon x-show="muted" name="speaker-x-mark" class="size-3.5" />
                                <flux:icon x-show="!muted" name="speaker-wave" class="size-3.5" style="display: none;" />
                            </button>
                        </div>
                    @endif
                </div>
            @elseif ($imageCount > 0)
                <div class="mb-3 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800/80 shadow-sm" @click.stop>
                    @if ($imageCount === 1)
                        <div class="max-h-[400px] overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                            @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 0 })">
                            <img loading="lazy" src="{{ route('images.show', ['path' => trim($imageArray[0])]) }}" alt="Post image"
                                class="w-full h-auto max-h-[400px] object-cover hover:scale-[1.01] transition-transform duration-300">
                        </div>
                    @elseif($imageCount === 2)
                        <div class="grid grid-cols-2 gap-0.5">
                            @foreach ($imageArray as $index => $image)
                                <div class="overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                    @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: {{ $index }} })">
                                    <img loading="lazy" src="{{ route('images.show', ['path' => trim($image)]) }}" alt="Post image"
                                        class="w-full h-full object-cover min-h-[160px] max-h-[260px] hover:scale-105 transition-transform duration-300">
                                </div>
                            @endforeach
                        </div>
                    @else
                        @php
                            $displayImages = array_slice($imageArray, 0, 2);
                            $remaining = $imageCount - 2;
                        @endphp
                        <div class="grid grid-cols-2 gap-0.5">
                            <div class="overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 0 })">
                                <img loading="lazy" src="{{ route('images.show', ['path' => trim($displayImages[0])]) }}" alt="Post image"
                                    class="w-full h-full object-cover min-h-[160px] max-h-[260px] hover:scale-105 transition-transform duration-300">
                            </div>
                            <div class="relative overflow-hidden bg-black/5 dark:bg-black/40 cursor-pointer"
                                @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($imageUrls) }}, index: 1 })"
                                aria-label="{{ __('Show :count more images', ['count' => $remaining]) }}">
                                <img loading="lazy" src="{{ route('images.show', ['path' => trim($displayImages[1])]) }}" alt="Post image"
                                    class="w-full h-full object-cover min-h-[160px] max-h-[260px]">
                                <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                    <span class="text-white text-xl sm:text-2xl font-extrabold tracking-tight">+{{ $remaining }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($hasVideo)
                <div x-data="{ muted: true }" @click.stop class="relative mb-3 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800/80 max-h-[400px] bg-black shadow-sm">
                    <video x-ref="video" data-autoplay muted src="{{ route('videos.show', ['path' => $post->video]) }}" class="w-full h-auto max-h-[400px] object-cover"
                        controls controlsList="nodownload" playsinline preload="metadata"></video>
                    <button type="button" @click.stop="muted = !muted; $refs.video.muted = muted"
                        class="absolute top-2 right-2 z-10 flex items-center justify-center size-8 rounded-full bg-black/50 text-white backdrop-blur-sm hover:bg-black/70 transition-colors"
                        :aria-label="muted ? 'Unmute video' : 'Mute video'">
                        <flux:icon x-show="muted" name="speaker-x-mark" class="size-4" />
                        <flux:icon x-show="!muted" name="speaker-wave" class="size-4" style="display: none;" />
                    </button>
                </div>
            @endif

            <!-- Action Bar (Twitter/X Style — same as feed posts) + Judge Star Ratings -->
            <div class="flex items-center justify-between gap-2 pt-2 mt-1 text-zinc-500">
                <div class="flex items-center justify-between max-w-md flex-1">
                    <!-- Comment Button (toggles inline comments) -->
                    <button wire:click.stop="toggleComments"
                        @click.stop
                        class="flex items-center gap-2 group text-xs transition-colors {{ $showAllComments ? 'text-sky-500' : 'hover:text-sky-500' }}">
                        <div class="p-2 rounded-full group-hover:bg-sky-500/10 transition-colors">
                            <flux:icon name="chat-bubble-left" class="size-4" />
                        </div>
                        <span class="font-medium tabular-nums">{{ $post->all_comments_count ?? $post->allComments()->count() }}</span>
                    </button>

                    <!-- Like Button -->
                    <button wire:click.stop="toggleLike"
                        x-data="{
                            liked: {{ $post->isLikedBy(auth()->user()) ? 'true' : 'false' }},
                            count: {{ $post->likes_count ?? 0 }},
                            hearts: [],
                            burst() {
                                const colors = ['text-rose-500','text-pink-400','text-amber-400','text-purple-500','text-sky-400','text-emerald-400','text-orange-400'];
                                const pts = [[-18,-14],[18,-12],[-12,-22],[14,-20],[0,-26],[-8,-18],[10,-16]];
                                this.hearts = pts.map(([x,y], i) => ({ id: Date.now()+i+Math.random(), x, y, color: colors[i % colors.length], delay: i*38 }));
                                setTimeout(() => this.hearts = [], 760);
                            }
                        }"
                        @click="const willLike = !liked; liked = willLike; count += willLike ? 1 : -1; if (willLike) burst()"
                        class="relative flex items-center gap-2 group text-xs transition-colors"
                        :class="liked ? 'text-rose-500' : 'text-zinc-400 dark:text-zinc-500 hover:text-rose-500'">
                        <span class="absolute inset-0 pointer-events-none overflow-visible" aria-hidden="true">
                            <template x-for="h in hearts" :key="h.id">
                                <span class="heart-burst absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2" :style="`--tx:${h.x}px; --ty:${h.y}px; animation-delay:${h.delay}ms`">
                                    <span :class="h.color"><flux:icon name="heart" variant="solid" class="size-2.5 drop-shadow-sm" /></span>
                                </span>
                            </template>
                        </span>
                        <span class="relative p-2 rounded-full group-hover:bg-rose-500/10 transition-colors">
                            <flux:icon name="heart" variant="solid" class="size-4 group-hover:scale-110 transition-transform" />
                        </span>
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
                            <svg xmlns="http://www.w3.org/2000/svg" :fill="bookmarked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" :stroke-width="bookmarked ? '0' : '1.5'" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                            </svg>
                        </div>
                    </button>
                </div>

                <!-- Star Ratings (Retained from challenge cards — judges can rate) -->
                <div class="flex items-center gap-0.5 sm:gap-1 shrink-0" @click.stop>
                    @for ($i = 1; $i <= 5; $i++)
                        <button wire:click.stop="setRating({{ $i }})" {{ !$isJudge ? 'disabled' : '' }}
                            class="{{ $isJudge ? 'hover:scale-125 transition-transform' : 'cursor-default' }}">
                            <flux:icon name="star"
                                class="size-4 sm:size-5 {{ $i <= ($isJudge ? $userRating : $post->averageRating()) ? 'text-yellow-400' : 'text-zinc-200 dark:text-zinc-800' }}"
                                variant="{{ $i <= ($isJudge ? $userRating : $post->averageRating()) ? 'solid' : 'outline' }}" />
                        </button>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>
