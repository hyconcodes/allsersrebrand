<?php

use App\Models\User;
use App\Models\Post;
use App\Models\Conversation;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Traits\HandlesPostActions;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    use WithPagination, WithFileUploads, HandlesPostActions;
    public User $user;
    public $posts;

    public function rendering($view)
    {
        $view->title($this->user->name . ' - ' . ($this->user->work ?? 'Artisan'));
    }

    public function mount(User $user)
    {
        $this->user = $user;
        $this->loadPosts();
    }

    public function loadPosts()
    {
        $this->posts = Post::where('user_id', $this->user->id)
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
            ->withCount(['likes', 'allComments'])
            ->latest()
            ->paginate(20);
    }

    public function startConversation()
    {
        $userId = $this->user->id;
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

    public function deletePost($postId)
    {
        $post = Post::find($postId);
        if ($post && $post->user_id === auth()->id()) {
            $post->delete();
            $this->loadPosts();
            $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Post has been deleted.');
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Unauthorized action.');
        }
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <!-- Profile Header -->
    <div>
        <div class="h-32 sm:h-48 bg-gradient-to-r from-purple-600 to-blue-700"></div>

        <div class="px-4 pb-4">
            <div class="flex justify-between items-end -mt-12 sm:-mt-16 mb-4">
                <div class="size-20 sm:size-28 rounded-full bg-zinc-950 p-0.5">
                    <div class="size-full rounded-full bg-zinc-100 flex items-center justify-center text-zinc-600 font-bold text-2xl sm:text-4xl overflow-hidden">
                        @if ($user->profile_picture_url)
                            <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 mb-1" x-data="{
                    copy(text) {
                        navigator.clipboard.writeText(text).then(() => {
                            $dispatch('toast', { type: 'success', title: 'Link Copied!', message: 'Profile link copied to clipboard.' });
                        });
                    }
                }">
                    @if (auth()->id() !== $user->id)
                        <button wire:click="startConversation"
                            class="px-4 py-1.5 border border-zinc-700 text-zinc-100 rounded-full text-sm font-bold hover:bg-zinc-800 transition-colors">
                            {{ __('Chat') }}
                        </button>
                    @endif
                </div>
            </div>

            <div class="space-y-1">
                <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                    {{ $user->name }}
                    @if ($user->isArtisan())
                        <flux:icon name="check-badge" class="size-5 text-blue-500 fill-current" />
                    @endif
                </h1>
                <p class="text-sm text-zinc-500">{{ '@' . $user->username }}</p>
                @if ($user->work)
                    <p class="text-sm text-purple-500 font-medium">{{ $user->work }}</p>
                @endif
            </div>

            @if ($user->bio)
                <p class="mt-3 text-sm text-zinc-400 leading-relaxed max-w-2xl">
                    {{ $user->bio }}
                </p>
            @endif

            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <span class="flex items-center gap-1.5 text-zinc-500">
                    <flux:icon name="map-pin" class="size-3.5" />
                    {{ $user->address ?? __('Global') }}
                </span>
                <span class="flex items-center gap-1.5 text-zinc-500">
                    <flux:icon name="calendar-days" class="size-3.5" />
                    {{ __('Joined') }} {{ \Carbon\Carbon::parse($user->created_at)->format('M Y') }}
                </span>
                <span class="flex items-center gap-1.5 text-zinc-500">
                    <flux:icon name="document-text" class="size-3.5" />
                    <span class="font-bold text-zinc-100">{{ $posts->total() }}</span> {{ __('Posts') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Badges -->
    @if ($user->badges->count() > 0)
        <div class="border-b border-zinc-800 px-4 py-4">
            <div class="flex flex-wrap gap-3">
                @foreach ($user->badges as $badge)
                    <div class="group relative">
                        <div class="size-10 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center">
                            @if ($badge->icon_url)
                                <img src="{{ asset('storage/' . $badge->icon_url) }}" class="size-6 object-contain">
                            @else
                                <flux:icon name="trophy" class="size-5 text-yellow-500" />
                            @endif
                        </div>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-44 p-2 bg-zinc-900 border border-zinc-800 text-xs rounded-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-center z-20 shadow-lg">
                            <p class="font-bold text-zinc-100">{{ $badge->name }}</p>
                            <p class="text-zinc-400 mt-1">{{ $badge->description }}</p>
                            <p class="text-xs mt-2 text-yellow-500">{{ __('Awarded') }}: {{ \Carbon\Carbon::parse($badge->pivot->awarded_at)->format('M Y') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Posts -->
    <div>
        <div class="border-b border-zinc-800 px-4">
            <span class="inline-block px-4 py-3 text-sm font-bold text-zinc-100 border-b-2 border-purple-500">{{ __('Posts') }}</span>
        </div>

        <div>
            @forelse($posts as $post)
                <livewire:dashboard.post-item :post="$post" :wire:key="'user-post-'.$post->id" />
            @empty
                <div class="px-4 py-12 text-center border-b border-zinc-800">
                    <p class="text-sm text-zinc-500">{{ __('This user hasn\'t posted anything yet.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($posts instanceof \Illuminate\Pagination\LengthAwarePaginator && $posts->hasPages())
            <div class="px-4 py-4">
                {{ $posts->links(data: ['wire:navigate' => true]) }}
            </div>
        @endif
    </div>

    <livewire:dashboard.post-detail />
    @include('partials.post-modals')
</div>
