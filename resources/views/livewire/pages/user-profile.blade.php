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

    public function rendering($view)
    {
        $view->title($this->user->name . ' - ' . ($this->user->work ?? 'Artisan'));

        $view->with([
            'posts' => $this->getPosts(),
        ]);
    }

    public function getPosts()
    {
        return Post::where('user_id', $this->user->id)
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
            $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Post has been deleted.');
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Unauthorized action.');
        }
    }
}; ?>

<div class="max-w-2xl mx-auto" x-data="{
    copy(text) {
        navigator.clipboard.writeText(text).then(() => {
            $dispatch('toast', { type: 'success', title: 'Link Copied!', message: 'Profile link copied to clipboard.' });
        });
    }
}">
    <x-top-bar title="{{ $user->name }}" />

    <!-- Profile Header -->
    <div>
        <div class="h-32 sm:h-44 bg-gradient-to-r from-purple-600 to-blue-700"></div>

        <div class="px-4 pb-3">
            <div class="flex justify-between items-end -mt-12 sm:-mt-14 mb-2">
                <div class="size-20 sm:size-28 rounded-full bg-white dark:bg-zinc-950 p-1 cursor-pointer shadow-lg shrink-0"
                    @click="$dispatch('open-lightbox', { images: ['{{ $user->profile_picture_url }}'], index: 0 })">
                    <div class="size-full rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 font-bold text-2xl sm:text-4xl overflow-hidden">
                        @if ($user->profile_picture_url)
                            <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 mb-1 shrink-0">
                    @auth
                        @if (auth()->id() === $user->id)
                            <a href="{{ route('profile.edit') }}" wire:navigate
                                class="px-4 py-1.5 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-full text-sm font-bold hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                {{ __('Edit profile') }}
                            </a>
                        @else
                            <button wire:click="startConversation"
                                class="px-4 py-1.5 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-full text-sm font-bold hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                {{ __('Chat') }}
                            </button>
                        @endif
                        <button @click="copy('{{ route('user.profile', $user) }}')"
                            class="size-9 rounded-full border border-zinc-300 dark:border-zinc-700 flex items-center justify-center text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                            <flux:icon name="ellipsis-horizontal" class="size-4" />
                        </button>
                    @else
                        <a href="{{ route('login') }}"
                            class="px-4 py-1.5 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-full text-sm font-bold hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                            {{ __('Chat') }}
                        </a>
                    @endauth
                </div>
            </div>

            <div class="space-y-1">
                <h1 class="text-xl font-extrabold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    {{ $user->name }}
                    @if ($user->isArtisan())
                        <flux:icon name="check-badge" class="size-5 text-blue-500 fill-current" />
                    @endif
                </h1>
                <p class="text-sm text-zinc-500">{{ '@' . $user->username }}</p>
                @if ($user->work)
                    <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">{{ $user->work }}</p>
                @endif
            </div>

            @if ($user->bio)
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed max-w-2xl">
                    {{ $user->bio }}
                </p>
            @endif

            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-zinc-500">
                <span class="flex items-center gap-1.5">
                    <flux:icon name="map-pin" class="size-3.5" />
                    {{ $user->address ?? __('Global') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <flux:icon name="calendar-days" class="size-3.5" />
                    {{ __('Joined') }} {{ \Carbon\Carbon::parse($user->created_at)->format('M Y') }}
                </span>
                @if ($user->experience_year)
                    <span class="flex items-center gap-1.5">
                        <flux:icon name="briefcase" class="size-3.5" />
                        {{ $user->experience_year }}+ {{ __('Years Exp.') }}
                    </span>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap gap-x-8 gap-y-2">
                <div>
                    <span class="block text-base font-extrabold text-zinc-900 dark:text-zinc-100">{{ $posts->total() }}</span>
                    <span class="block text-xs text-zinc-500 mt-0.5">{{ __('posts') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges -->
    @if ($user->badges->count() > 0)
        <div class="border-b border-zinc-200/50 dark:border-zinc-800/50 px-4 py-3">
            <div class="flex flex-wrap gap-3">
                @foreach ($user->badges as $badge)
                    <div class="group relative">
                        <div
                            class="size-9 rounded-full bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 flex items-center justify-center">
                            @if ($badge->icon_url)
                                <img src="{{ asset('storage/' . $badge->icon_url) }}" class="size-5 object-contain">
                            @else
                                <flux:icon name="trophy" class="size-4 text-yellow-500" />
                            @endif
                        </div>
                        <div
                            class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-44 p-2 bg-zinc-900 dark:bg-zinc-800 border border-zinc-800 text-xs rounded-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-center z-20 shadow-lg">
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
        <div class="flex items-center gap-6 px-4 border-b border-zinc-200/50 dark:border-zinc-800/50 overflow-x-auto">
            <span class="relative py-3 text-sm font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                {{ __('Posts') }}
                <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full"></div>
            </span>
        </div>

        <div>
            @forelse($posts as $post)
                <livewire:dashboard.post-item :post="$post" :wire:key="'user-post-'.$post->id" />
            @empty
                <div class="px-4 py-12 text-center border-b border-zinc-200/50 dark:border-zinc-800/50">
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
