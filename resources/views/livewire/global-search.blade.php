<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Post;

new class extends Component {
    public $query = '';
    public $users;
    public $posts;

    public function mount()
    {
        $this->users = collect();
        $this->posts = collect();
    }

    public function updatedQuery($value)
    {
        if (strlen($value) < 2) {
            $this->users = collect();
            $this->posts = collect();
            return;
        }

        $this->users = User::where('name', 'like', "%{$value}%")
            ->orWhere('username', 'like', "%{$value}%")
            ->take(3)
            ->get();

        $this->posts = Post::where('content', 'like', "%{$value}%")
            ->with('user')
            ->latest()
            ->take(3)
            ->get();
    }
}; ?>

<div class="relative w-full" x-data="{ focused: false }" @click.outside="focused = false">
    <div class="relative">
        <flux:icon name="magnifying-glass" class="absolute left-3 top-2.5 size-4 text-zinc-400 pointer-events-none" />
        <input type="text" wire:model.live.debounce.300ms="query" @focus="focused = true"
            placeholder="{{ __('Search...') }}"
            class="w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl py-2.5 pl-9 pr-8 text-sm focus:ring-2 focus:ring-[var(--color-brand-purple)] focus:border-transparent text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 shadow-sm transition-all hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
        @if($query)
            <button wire:click="$set('query', '')" class="absolute right-2 top-2 text-zinc-400 hover:text-zinc-600 p-0.5">
                <flux:icon name="x-mark" class="size-4" />
            </button>
        @endif
    </div>

    @if(($users->isNotEmpty() || $posts->isNotEmpty()) && $query)
        <div x-show="focused" x-transition
            class="absolute left-2 right-2 top-full mt-2 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-zinc-100 dark:border-zinc-800 z-50 overflow-hidden max-h-96 overflow-y-auto">

            @if($users->isNotEmpty())
                <div class="p-2">
                    <h3 class="px-2 pb-1 text-xs font-bold text-zinc-500 uppercase tracking-wider">{{ __('People') }}</h3>
                    <div class="space-y-1">
                        @foreach($users as $user)
                            <a href="{{ route('artisan.profile', $user) }}" wire:navigate
                                class="flex items-center gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 rounded-lg transition-colors group">
                                <div
                                    class="size-8 rounded-full bg-zinc-100 flex items-center justify-center overflow-hidden shrink-0">
                                    @if($user->profile_picture_url)
                                        <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                                    @else
                                        <span class="text-xs font-bold text-zinc-500">{{ $user->initials() }}</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-sm font-bold text-zinc-900 dark:text-zinc-100 truncate group-hover:text-[var(--color-brand-purple)]">
                                        {{ $user->name }}</p>
                                    <p class="text-xs text-zinc-500 truncate">{{ '@' . $user->username }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($users->isNotEmpty() && $posts->isNotEmpty())
                <div class="h-px bg-zinc-100 dark:bg-zinc-800 mx-2"></div>
            @endif

            @if($posts->isNotEmpty())
                <div class="p-2">
                    <h3 class="px-2 pb-1 text-xs font-bold text-zinc-500 uppercase tracking-wider">{{ __('Posts') }}</h3>
                    <div class="space-y-1">
                        @foreach($posts as $post)
                            <button @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                                class="w-full flex items-start gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 rounded-lg transition-colors text-left group">
                                <div
                                    class="size-8 rounded-full bg-zinc-100 flex items-center justify-center overflow-hidden shrink-0">
                                    @if($post->user->profile_picture_url)
                                        <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                                    @else
                                        <span class="text-xs font-bold text-zinc-500">{{ $post->user->initials() }}</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-zinc-500 mb-0.5">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $post->user->name }}</span>
                                        <span>• {{ $post->created_at->diffForHumans() }}</span>
                                    </p>
                                    <p
                                        class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 transition-colors">
                                        {{ $post->content }}
                                    </p>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>