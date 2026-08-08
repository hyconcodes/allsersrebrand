<?php

use App\Models\User;
use App\Models\Post;
use App\Models\Conversation;
use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Traits\HandlesPostActions;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    use WithPagination, WithFileUploads, HandlesPostActions;
    public User $user;
    public $showcases = [];
    public string $activeTab = 'posts';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function rendering($view)
    {
        $title = $this->user->name . ' (@' . ($this->user->username ?? 'artisan') . ') | ' . ($this->user->work ?? 'Professional Artisan') . ' on Allsers';
        $description = $this->user->bio ?: 'Explore the professional portfolio and verified services of ' . $this->user->name . ' on Allsers. Hire trusted artisans with confidence.';
        $image = $this->user->profile_picture_url ?: asset('assets/allsers.png');

        $view->title($title);

        $posts = Post::where('user_id', $this->user->id)
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

        return $view->with([
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaImage' => $image,
            'metaUrl' => route('artisan.profile', ['user' => $this->user->slug]),
            'posts' => $posts,
        ]);
    }

    public function mount(User $user)
    {
        $this->user = $user;
        $this->loadShowcases();
    }

    public function loadShowcases()
    {
        $this->showcases = $this->user
            ->artisanEngagements()
            ->where('is_public', true)
            ->with(['review.reviewer', 'user'])
            ->latest()
            ->get();
    }

    #[On('post-deleted')]
    public function refreshProfile()
    {
        // rendering() will re-fetch posts on next render
    }

    public function startConversation()
    {
        if (!auth()->check()) {
            return $this->redirect(route('login'));
        }

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
}; ?>

@push('head')
    <!-- Primary Meta Tags -->
    <meta name="title" content="{{ $user->name }} | {{ $user->work ?? 'Verified Artisan' }} on Allsers">
    <meta name="description"
        content="{{ Str::limit($user->bio ?: 'Explore the professional portfolio and verified services of ' . $user->name . ' on Allsers. Hire trusted artisans with confidence.', 160) }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="profile">
    <meta property="og:url" content="{{ route('artisan.profile', ['user' => $user->slug]) }}">
    <meta property="og:title" content="{{ $user->name }} | {{ $user->work ?? 'Verified Artisan' }} on Allsers">
    <meta property="og:description"
        content="{{ Str::limit($user->bio ?: 'Explore the professional portfolio and verified services of ' . $user->name . ' on Allsers. Hire trusted artisans with confidence.', 160) }}">
    <meta property="og:image" content="{{ $user->profile_picture_url ?: asset('assets/allsers.png') }}">
    <meta property="profile:username" content="{{ $user->username }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ route('artisan.profile', ['user' => $user->slug]) }}">
    <meta property="twitter:title" content="{{ $user->name }} | {{ $user->work ?? 'Verified Artisan' }} on Allsers">
    <meta property="twitter:description"
        content="{{ Str::limit($user->bio ?: 'Explore the professional portfolio and verified services of ' . $user->name . ' on Allsers. Hire trusted artisans with confidence.', 160) }}">
    <meta property="twitter:image" content="{{ $user->profile_picture_url ?: asset('assets/allsers.png') }}">

    <!-- Structured Data (JSON-LD) for Google Rich Snippets -->
    @php
        $avgRating = $user->averageRating();
        $reviewCount = $user->reviews()->count();
        $latestReviews = $user->reviews()->with('reviewer')->latest()->limit(3)->get();

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => $user->name,
            'image' => $user->profile_picture_url ?: asset('assets/allsers.png'),
            '@id' => route('artisan.profile', ['user' => $user->slug]),
            'url' => route('artisan.profile', ['user' => $user->slug]),
            'description' => Str::limit(
                $user->bio ?:
                'Verified ' .
                    ($user->work ?? 'Artisan') .
                    ' on Allsers platform. Hire top-rated professionals for your needs.',
                160,
            ),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $user->address ?? 'Lagos, Nigeria',
                'addressCountry' => 'NG',
            ],
            'priceRange' => "$$",
            'provider' => [
                '@type' => 'Person',
                'name' => $user->name,
                'jobTitle' => $user->work ?? 'Artisan',
                'image' => $user->profile_picture_url ?: asset('assets/allsers.png'),
            ],
        ];

        if ($reviewCount > 0) {
            $jsonLd['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($avgRating, 1),
                'reviewCount' => (string) $reviewCount,
                'bestRating' => '5',
                'worstRating' => '1',
            ];

            $jsonLd['review'] = $latestReviews
                ->map(function ($review) {
                    return [
                        '@type' => 'Review',
                        'author' => [
                            '@type' => 'Person',
                            'name' => $review->reviewer ? $review->reviewer->name : 'Allsers User',
                        ],
                        'datePublished' => $review->created_at->toIso8601String(),
                        'reviewBody' => Str::limit($review->comment, 150),
                        'reviewRating' => [
                            '@type' => 'Rating',
                            'ratingValue' => (string) $review->rating,
                            'bestRating' => '5',
                            'worstRating' => '1',
                        ],
                    ];
                })
                ->toArray();
        }
    @endphp
    <script type="application/ld+json">
    {!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

<div class="max-w-2xl mx-auto" x-data="{
    copy(text) {
        navigator.clipboard.writeText(text).then(() => {
            $dispatch('toast', { type: 'success', title: 'Link Copied!', message: 'Profile link copied to clipboard.' });
        });
    }
}">
    <x-top-bar title="{{ $user->name }}" />

    <div class="hidden">
        <h1>{{ $user->name }} - {{ $user->work ?? 'Artisan' }} Profile</h1>
        <p>{{ $user->bio }}</p>
    </div>

    <!-- Profile Header -->
    <div>
        <div class="h-32 sm:h-44 bg-gradient-to-r from-purple-600 to-purple-900"></div>

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
                        <button @click="copy('{{ route('artisan.profile', $user->slug) }}')"
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
                    <span class="flex items-center gap-1 text-base font-extrabold text-zinc-900 dark:text-zinc-100">
                        <flux:icon name="star" variant="solid" class="size-4 text-yellow-500" />
                        {{ $user->averageRating() ? number_format($user->averageRating(), 1) : 'New' }}
                    </span>
                    <span class="block text-xs text-zinc-500 mt-0.5">{{ $user->reviews()->count() }} {{ __('reviews') }}</span>
                </div>
                <div>
                    <span class="block text-base font-extrabold text-zinc-900 dark:text-zinc-100">{{ count($showcases) }}</span>
                    <span class="block text-xs text-zinc-500 mt-0.5">{{ __('jobs') }}</span>
                </div>
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

    <!-- Tabs -->
    <div class="flex items-center gap-6 px-4 border-b border-zinc-200/50 dark:border-zinc-800/50 overflow-x-auto">
        <button wire:click="setTab('posts')"
            class="relative py-3 text-sm font-bold whitespace-nowrap transition-all {{ $activeTab === 'posts' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('Posts') }}
            @if ($activeTab === 'posts')
                <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full"></div>
            @endif
        </button>
        @if (count($showcases) > 0)
            <button wire:click="setTab('work')"
                class="relative py-3 text-sm font-bold whitespace-nowrap transition-all {{ $activeTab === 'work' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                {{ __('Verified Work') }}
                @if ($activeTab === 'work')
                    <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full"></div>
                @endif
            </button>
        @endif
        <button wire:click="setTab('reviews')"
            class="relative py-3 text-sm font-bold whitespace-nowrap transition-all {{ $activeTab === 'reviews' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('Reviews') }}
            @if ($activeTab === 'reviews')
                <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full"></div>
            @endif
        </button>
    </div>

    <!-- Posts Tab -->
    @if ($activeTab === 'posts')
        <div>
            @forelse($posts as $post)
                <livewire:dashboard.post-item :post="$post" :wire:key="'artisan-post-'.$post->id" />
            @empty
                <div class="px-4 py-12 text-center border-b border-zinc-200/50 dark:border-zinc-800/50">
                    <p class="text-sm text-zinc-500">{{ __('No portfolio items yet.') }}</p>
                </div>
            @endforelse

            @if ($posts instanceof \Illuminate\Pagination\LengthAwarePaginator && $posts->hasPages())
                <div class="px-4 py-4">
                    {{ $posts->links(data: ['wire:navigate' => true]) }}
                </div>
            @endif
        </div>
    @endif

    <!-- Verified Work Tab -->
    @if ($activeTab === 'work' && count($showcases) > 0)
        <div class="px-4 py-6 border-b border-zinc-200/50 dark:border-zinc-800/50">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($showcases as $showcase)
                    @php
                        $showcaseUrls = [];
                        if (isset($showcase->showcase_photos['before'])) $showcaseUrls[] = \App\Models\Setting::asset($showcase->showcase_photos['before']);
                        if (isset($showcase->showcase_photos['after'])) $showcaseUrls[] = \App\Models\Setting::asset($showcase->showcase_photos['after']);
                    @endphp
                    <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors"
                        x-data="{ view: 'after' }">
                        @if (isset($showcase->showcase_photos['before']) && isset($showcase->showcase_photos['after']))
                            <div class="relative aspect-[16/10] bg-zinc-100 dark:bg-zinc-900 cursor-pointer"
                                @click="$dispatch('open-lightbox', { images: {{ Js::from($showcaseUrls) }}, index: view === 'before' ? 0 : 1 })">
                                <div class="absolute inset-0 transition-opacity duration-500"
                                    :class="view === 'before' ? 'opacity-100' : 'opacity-0'">
                                    <img src="{{ \App\Models\Setting::asset($showcase->showcase_photos['before']) }}" class="size-full object-cover">
                                </div>
                                <div class="absolute inset-0 transition-opacity duration-500"
                                    :class="view === 'after' ? 'opacity-100' : 'opacity-0'">
                                    <img src="{{ \App\Models\Setting::asset($showcase->showcase_photos['after']) }}" class="size-full object-cover">
                                </div>
                                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex bg-zinc-950/80 rounded-full p-0.5 border border-zinc-700 z-10">
                                    <button @click.stop="view = 'before'"
                                        :class="view === 'before' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-400'"
                                        class="px-3 py-1 rounded-full text-xs font-medium transition-all">Before</button>
                                    <button @click.stop="view = 'after'"
                                        :class="view === 'after' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-400'"
                                        class="px-3 py-1 rounded-full text-xs font-medium transition-all">After</button>
                                </div>
                            </div>
                        @elseif(isset($showcase->showcase_photos['after']))
                            <div class="aspect-[16/10] bg-zinc-100 dark:bg-zinc-900 cursor-pointer"
                                @click="$dispatch('open-lightbox', { images: {{ Js::from($showcaseUrls) }}, index: 0 })">
                                <img src="{{ \App\Models\Setting::asset($showcase->showcase_photos['after']) }}" class="size-full object-cover">
                            </div>
                        @endif

                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <flux:icon name="star" variant="solid"
                                            class="size-3 {{ $i <= ($showcase->review?->rating ?? 5) ? 'text-yellow-500' : 'text-zinc-300 dark:text-zinc-700' }}" />
                                    @endfor
                                    <span class="text-xs font-bold text-zinc-400 ml-1">{{ number_format($showcase->review?->rating ?? 5, 1) }}</span>
                                </div>
                                <span class="text-xs text-zinc-500">{{ $showcase->completed_at?->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2 leading-relaxed">
                                &ldquo;{{ $showcase->showcase_description }}&rdquo;
                            </p>
                            @if ($showcase->review)
                                <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center gap-2">
                                    <div class="size-6 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                        <img src="{{ $showcase->user->profile_picture_url }}" class="size-full object-cover">
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $showcase->user->name }}</p>
                                        <p class="text-xs text-zinc-500">{{ __('Verified Customer') }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Reviews Tab -->
    @if ($activeTab === 'reviews')
        <div class="px-4 py-6">
            <livewire:artisan.rating-widget :artisan="$user" />
        </div>
    @endif

    <livewire:dashboard.post-detail />
    @include('partials.post-modals')
</div>