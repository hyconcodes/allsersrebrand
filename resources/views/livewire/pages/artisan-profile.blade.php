<?php

use App\Models\User;
use App\Models\Post;
use App\Models\Conversation;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    public User $user;
    public $posts = [];

    public function rendering($view)
    {
        $title = $this->user->name . ' (@' . ($this->user->username ?? $this->user->id) . ') | ' . ($this->user->work ?? 'Artisan') . ' on Allsers';
        $description = $this->user->bio ?? 'Explore the portfolio and services of ' . $this->user->name . ' on Allsers. Professional ' . ($this->user->work ?? 'Artisan') . ' available for hire.';
        $image = $this->user->profile_picture_url ?? asset('assets/allsers.png');

        $view->title($title);

        return $view->with([
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaImage' => $image,
            'metaUrl' => route('artisan.profile', $this->user->username ?? $this->user->id),
        ]);
    }

    public function mount($user)
    {
        // If $user is a string/ID, find it manually
        if (!($user instanceof User)) {
            $this->user = User::where('username', $user)->orWhere('id', $user)->firstOrFail();
        } else {
            $this->user = $user;
        }

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
            ->get();
    }

    public function startConversation()
    {
        if (!auth()->check()) {
            return $this->redirect(route('login'));
        }

        $userId = $this->user->id;
        $authId = auth()->id();

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
}; ?>

@push('head')
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('artisan.profile', $user->username ?? $user->id) }}">
    <meta property="og:title"
        content="{{ $user->name }} (@{{ $user - > username ?? $user - > id }}) | {{ $user->work ?? 'Artisan' }} on Allsers">
    <meta property="og:description"
        content="{{ $user->bio ?? 'Explore the portfolio and services of ' . $user->name . ' on Allsers.' }}">
    <meta property="og:image" content="{{ $user->profile_picture_url ?? asset('assets/allsers.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ route('artisan.profile', $user->username ?? $user->id) }}">
    <meta property="twitter:title"
        content="{{ $user->name }} (@{{ $user - > username ?? $user - > id }}) | {{ $user->work ?? 'Artisan' }} on Allsers">
    <meta property="twitter:description"
        content="{{ $user->bio ?? 'Explore the portfolio and services of ' . $user->name . ' on Allsers.' }}">
    <meta property="twitter:image" content="{{ $user->profile_picture_url ?? asset('assets/allsers.png') }}">
@endpush

<div class="max-w-4xl mx-auto px-4 py-8" x-data="{
    copy(text) {
        navigator.clipboard.writeText(text).then(() => {
            $dispatch('toast', { type: 'success', title: 'Link Copied!', message: 'Profile link copied to clipboard.' });
        });
    }
}">
    <!-- SEO Optimization (In-body fallback) -->
    <div class="hidden">
        <h1>{{ $user->name }} - {{ $user->work ?? 'Artisan' }} Profile</h1>
        <p>{{ $user->bio }}</p>
    </div>

    <!-- Profile Header -->
    <div
        class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 overflow-hidden shadow-sm mb-8">
        <div class="h-32 bg-gradient-to-r from-[var(--color-brand-purple)] to-[#6a11cb]"></div>
        <div class="px-8 pb-8">
            <div class="relative flex justify-between items-end -mt-12 mb-6">
                <div class="size-24 rounded-2xl bg-white dark:bg-zinc-900 p-1 shadow-lg">
                    <div
                        class="w-full h-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-700 dark:text-purple-300 font-bold text-3xl overflow-hidden">
                        @if ($user->profile_picture_url)
                            <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </div>
                </div>

                <div class="flex gap-3">
                    @auth
                        @if (auth()->id() !== $user->id)
                            <flux:button wire:click="startConversation" variant="primary" icon="chat-bubble-left-right"
                                class="rounded-full px-6">
                                {{ __('Chat') }}
                            </flux:button>
                        @endif
                    @else
                        <flux:button :href="route('login')" variant="primary" icon="chat-bubble-left-right"
                            class="rounded-full px-6">
                            {{ __('Chat') }}
                        </flux:button>
                    @endauth

                    {{-- <flux:button @click="copy('{{ route('artisan.profile', $user->username ?? $user->id) }}')"
                        variant="outline" icon="share" class="rounded-full shadow-none">
                        {{ __('Share') }}
                    </flux:button> --}}
                </div>
            </div>

            <div class="space-y-1">
                <h1 class="text-2xl font-black text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    {{ $user->name }}
                    @if ($user->isArtisan())
                        <flux:icon name="check-badge" class="size-6 text-blue-500 fill-current" />
                    @endif
                </h1>
                <p>{{ '@' . $user->username }}</p>
                <p class="text-[var(--color-brand-purple)] font-bold uppercase tracking-wide text-xs">
                    {{ $user->work ?? __('Artisan') }}
                </p>
            </div>

            <div class="mt-4 flex flex-wrap gap-6 text-sm">
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="map-pin" class="size-4" />
                    {{ $user->address ?? __('Global') }}
                </div>
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="briefcase" class="size-4" />
                    {{ $user->experience_year ?? '0' }}+ {{ __('Years Exp.') }}
                </div>
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="calendar-days" class="size-4" />
                    {{ __('Joined') }} {{ \Carbon\Carbon::parse($user->created_at)->format('M Y') }}
                </div>
            </div>

            @if ($user->bio)
                <p class="mt-6 text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed max-w-2xl">
                    {{ $user->bio }}
                </p>
            @endif

            <!-- Awarded Badges Section -->
            @if ($user->badges->count() > 0)
                <div class="mt-8 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-4">
                        {{ __('Special Badges & Awards') }}</h3>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($user->badges as $badge)
                            <div class="group relative flex flex-col items-center gap-2">
                                <div
                                    class="size-16 rounded-2xl bg-gradient-to-br from-yellow-400/20 to-orange-500/20 dark:from-yellow-400/10 dark:to-orange-500/10 p-2 border border-yellow-400/30 flex items-center justify-center transition-all hover:scale-110 hover:shadow-lg shadow-yellow-500/10">
                                    @if ($badge->icon_url)
                                        <img src="{{ asset('storage/' . $badge->icon_url) }}"
                                            class="size-full object-contain">
                                    @else
                                        <flux:icon name="trophy" class="size-8 text-yellow-500" />
                                    @endif
                                </div>
                                <span
                                    class="text-[10px] font-black text-zinc-900 dark:text-white text-center w-20 leading-tight uppercase tracking-tighter">{{ $badge->name }}</span>

                                <!-- Tooltip on hover -->
                                <div
                                    class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-zinc-900 text-white text-[10px] rounded-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-center z-20">
                                    <p class="font-bold">{{ $badge->name }}</p>
                                    <p class="text-zinc-400 mt-1">{{ $badge->description }}</p>
                                    <p class="text-[8px] mt-2 text-yellow-400">{{ __('Awarded') }}:
                                        {{ \Carbon\Carbon::parse($badge->pivot->awarded_at)->format('M Y') }}</p>
                                    <div
                                        class="absolute top-full left-1/2 -translate-x-1/2 border-8 border-transparent border-t-zinc-900">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Rating Section -->
    <div class="mb-8">
        <livewire:artisan.rating-widget :artisan="$user" />
    </div>

    <!-- Portfolio Section -->
    <div class="space-y-6 max-w-2xl">
        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 px-2">{{ __('Portfolio & Showcase') }}</h2>

        @forelse($posts as $post)
            <livewire:dashboard.post-item :post="$post" :wire:key="'artisan-post-'.$post->id" />
        @empty
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-12 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
                <p class="text-zinc-500 text-sm">{{ __('No portfolio items yet.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Modals -->
    <livewire:dashboard.post-detail />
</div>
