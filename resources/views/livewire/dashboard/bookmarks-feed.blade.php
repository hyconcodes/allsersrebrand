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
            ->whereHas('bookmarks', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->withCount(['likes', 'allComments'])
            ->latest('created_at')
            ->take(20)
            ->get();
    }

    #[Livewire\Attributes\On('comment-added')]
    #[Livewire\Attributes\On('post-liked')]
    #[Livewire\Attributes\On('post-bookmarked')]
    #[Livewire\Attributes\On('post-deleted')]
    public function refreshBookmarks()
    {
        $this->loadPosts();
    }

    public function openReportModal($postId)
    {
        $this->dispatch('open-report-modal', postId: $postId);
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
        <livewire:dashboard.post-item :post="$post" :wire:key="'bookmark-post-'.$post->id" />
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

    <!-- Reuse Post Detail Drawer -->
    <livewire:dashboard.post-detail />
</div>
