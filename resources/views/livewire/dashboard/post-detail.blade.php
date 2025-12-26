<?php

use App\Models\Post;
use App\Models\Comment;
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
            'comments.user',
            'comments.replies.user',
            'bookmarks' => function ($query) {
                $query->where('user_id', auth()->id());
            }
        ])
            ->withCount(['likes', 'allComments'])
            ->find($postId);

        $this->dispatch('modal-show', name: 'post-detail-drawer');
    }

    public function addComment()
    {
        if (empty(trim($this->commentContent)))
            return;

        Comment::create([
            'user_id' => auth()->id(),
            'post_id' => $this->post->id,
            'parent_id' => $this->replyToId,
            'content' => $this->commentContent,
        ]);

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
        }

        $this->loadPost($this->post->id);
        $this->dispatch('post-liked'); // Sync with feed
    }
}; ?>

<div>
    <flux:modal name="post-detail-drawer" variant="flyout" class="w-full sm:max-w-xl p-0">
        @if($post)
            <div class="h-full flex flex-col bg-white dark:bg-zinc-900 overflow-hidden">
                <!-- Header -->
                <div
                    class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between sticky top-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md z-10">
                    <h2 class="font-bold text-lg text-zinc-900 dark:text-zinc-100">{{ __('Post Details') }}</h2>
                    <flux:modal.close>
                        <flux:button variant="ghost" icon="x-mark" size="sm" />
                    </flux:modal.close>
                </div>

                <!-- Content Area -->
                <div class="flex-1 overflow-y-auto p-4 space-y-6">
                    <!-- Original Post -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="size-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-sm">
                                {{ $post->user->initials() }}
                            </div>
                            <div>
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ $post->user->name }}</h3>
                                <p class="text-xs text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                            </div>
                        </div>

                        <p class="text-zinc-700 dark:text-zinc-300 text-sm leading-relaxed">
                            {{ $post->content }}
                        </p>

                        <!-- Images -->
                        @if($post->images)
                            @php $imageArray = array_filter(explode(',', $post->images)); @endphp
                            @if(count($imageArray) > 0)
                                <div class="space-y-2">
                                    @foreach($imageArray as $image)
                                        <div class="rounded-xl overflow-hidden border border-zinc-100 dark:border-zinc-800">
                                            <img src="{{ route('images.show', ['path' => trim($image)]) }}"
                                                class="w-full h-auto object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif

                        <!-- Video -->
                        @if($post->video)
                            <div class="rounded-xl overflow-hidden h-80 border border-zinc-100 dark:border-zinc-800">
                                <video src="{{ route('images.show', ['path' => $post->video]) }}"
                                    class="w-full h-full object-cover" controls></video>
                            </div>
                        @endif

                        <!-- Stats & Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-zinc-50 dark:border-zinc-800/50">
                            <div class="flex items-center gap-6">
                                <button wire:click="toggleLike"
                                    @click="new Audio('{{ asset('assets/mixkit-little-goat-bleat-319.wav') }}').play()"
                                    class="flex items-center gap-1.5 transition-colors group {{ $post->isLikedBy(auth()->user()) ? 'text-red-500' : 'text-zinc-500 hover:text-red-500' }}">
                                    @if($post->isLikedBy(auth()->user()))
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
                                            }" @click="share()" class="flex items-center gap-1.5 transition-colors relative"
                                    :class="copied ? 'text-green-500' : 'text-zinc-500 hover:text-green-500'">
                                    <flux:icon name="share" class="size-5" />
                                    <span x-show="copied" x-transition
                                        class="absolute -top-8 left-1/2 -translate-x-1/2 bg-zinc-800 text-white text-[10px] px-2 py-1 rounded shadow-lg whitespace-nowrap">
                                        {{ __('Link Copied!') }}
                                    </span>
                                </button>
                                <button wire:click="toggleBookmark"
                                    class="transition-colors @if($post->isBookmarkedBy(auth()->user())) text-[var(--color-brand-purple)] @else text-zinc-500 hover:text-[var(--color-brand-purple)] @endif">
                                    @if($post->isBookmarkedBy(auth()->user()))
                                        <svg class="size-5 fill-current" viewBox="0 0 24 24"><path d="M5 4c0-1.1.9-2 2-2h10a2 2-2v18l-7-3-7 3V4z" /></svg>
                                    @else
                                        <flux:icon name="bookmark" class="size-5" />
                                    @endif
                                </button>
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
                                        class="size-8 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-600 font-bold text-xs shrink-0">
                                        {{ $comment->user->initials() }}
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $comment->user->name }}</span>
                                            <span
                                                class="text-[10px] text-zinc-400">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $comment->content }}</p>
                                        <button wire:click="setReplyTo({{ $comment->id }})"
                                            class="text-[10px] font-bold text-[var(--color-brand-purple)] hover:underline">
                                            {{ __('Reply') }}
                                        </button>

                                        <!-- Replies -->
                                        @if($comment->replies->count() > 0)
                                            <div class="mt-4 space-y-4 pl-4 border-l-2 border-zinc-50 dark:border-zinc-800">
                                                @foreach($comment->replies as $reply)
                                                    <div class="flex gap-2">
                                                        <div
                                                            class="size-6 rounded-full bg-zinc-50 flex items-center justify-center text-zinc-500 font-bold text-[8px] shrink-0">
                                                            {{ $reply->user->initials() }}
                                                        </div>
                                                        <div class="flex-1 space-y-0.5">
                                                            <div class="flex items-center gap-2">
                                                                <span
                                                                    class="font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $reply->user->name }}</span>
                                                                <span
                                                                    class="text-[8px] text-zinc-400">{{ $reply->created_at->diffForHumans() }}</span>
                                                            </div>
                                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $reply->content }}
                                                            </p>
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
                    @if($replyToId)
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
                            class="size-8 rounded-full bg-[var(--color-brand-purple)]/10 flex items-center justify-center text-[var(--color-brand-purple)] text-xs font-bold shrink-0">
                            {{ auth()->user()->initials() }}
                        </div>
                        <div class="flex-1 relative">
                            <input wire:model="commentContent" type="text" placeholder="{{ __('Write a comment...') }}"
                                class="w-full bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 rounded-full pl-4 pr-10 py-2 text-sm focus:ring-1 focus:ring-[var(--color-brand-purple)] focus:border-[var(--color-brand-purple)]"
                                wire:keydown.enter="addComment">
                            <button wire:click="addComment"
                                class="absolute right-2 top-1.5 text-[var(--color-brand-purple)] hover:scale-110 transition-transform p-1">
                                <flux:icon name="paper-airplane" class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>