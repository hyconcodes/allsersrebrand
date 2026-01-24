<?php

use App\Models\Challenge;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new class extends Component {
    public function rendering(\Illuminate\View\View $view)
    {
        $title = 'Community Challenges & Trending Artisans on Allsers';
        $description = 'Join official community challenges, showcase your skills, and earn unique badges on Allsers. Compete with top artisans and win rewards.';

        $view->layout('components.layouts.app', [
            'title' => $title,
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaUrl' => route('challenges.index'),
        ]);
    }

    public function with()
    {
        return [
            'ongoingChallenges' => Challenge::with(['creator', 'participants'])
                ->where('end_at', '>', now())
                ->orderByDesc('is_admin_challenge')
                ->latest()
                ->get(),
            'pastChallenges' => Challenge::with(['creator', 'winner'])
                ->where('end_at', '<=', now())
                ->latest()
                ->paginate(12),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 py-12 space-y-16">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div class="space-y-2">
            <h1 class="text-4xl font-extrabold text-zinc-900 dark:text-white tracking-tight">
                {{ __('Challenges & Trending') }}</h1>
            <p class="text-zinc-500 max-w-lg">
                {{ __('Join official community challenges, showcase your skills, and earn unique badges.') }}</p>
        </div>

        @if (auth()->user()->isArtisan() || auth()->user()->isAdmin())
            <a href="{{ route('challenges.create') }}"
                class="inline-flex items-center gap-2 px-6 py-3 bg-[var(--color-brand-purple)] text-white font-bold rounded-2xl hover:scale-105 active:scale-95 transition-all shadow-lg shadow-purple-500/20">
                <flux:icon name="plus" class="size-5" />
                {{ __('Launch New Challenge') }}
            </a>
        @endif
    </div>

    <!-- Active Challenges Section -->
    <section class="space-y-8">
        <h2 class="text-2xl font-black text-zinc-900 dark:text-white flex items-center gap-3">
            <div class="size-8 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                <flux:icon name="fire" class="size-5 text-orange-500" />
            </div>
            {{ __('Ongoing Challenges') }}
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse($ongoingChallenges as $challenge)
                <div
                    class="group relative bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col">
                    <!-- Ribbon for Admin Challenges -->
                    @if ($challenge->is_admin_challenge)
                        <div
                            class="absolute top-4 right-4 z-10 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1">
                            <flux:icon name="shield-check" class="size-3" />
                            {{ __('Official') }}
                        </div>
                    @endif

                    <!-- Banner -->
                    <div class="h-48 relative overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        @if ($challenge->banner_url)
                            <img src="{{ asset('storage/' . $challenge->banner_url) }}"
                                class="size-full object-cover group-hover:scale-110 transition-transform duration-700">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-zinc-900/80 to-transparent"></div>
                        <div class="absolute bottom-4 left-6">
                            <p class="text-[10px] font-black uppercase tracking-widest text-zinc-300">
                                #{{ $challenge->hashtag }}</p>
                            <h3 class="text-xl font-black text-white mt-1">{{ $challenge->title }}</h3>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6 flex-1 flex flex-col justify-between space-y-6">
                        <div class="flex items-center justify-between text-xs font-bold text-zinc-500">
                            <div class="flex items-center gap-2">
                                <flux:icon name="users" class="size-4" />
                                {{ number_format($challenge->participants->count()) }} {{ __('participants') }}
                            </div>
                            <div class="flex items-center gap-2 text-orange-500">
                                <flux:icon name="clock" class="size-4" />
                                {{ $challenge->end_at->diffForHumans() }}
                            </div>
                        </div>

                        <p class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2">
                            {{ $challenge->guidelines }}
                        </p>

                        <div class="flex items-center justify-between pt-4">
                            <div class="flex items-center gap-2">
                                <div class="size-8 rounded-full bg-zinc-100 overflow-hidden">
                                    @if ($challenge->creator->profile_picture_url)
                                        <img src="{{ $challenge->creator->profile_picture_url }}"
                                            class="size-full object-cover">
                                    @endif
                                </div>
                                <span
                                    class="text-xs font-bold text-zinc-900 dark:text-white">{{ $challenge->creator->name }}</span>
                            </div>
                            <a href="{{ route('challenges.show', $challenge->custom_link) }}"
                                class="px-5 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white font-black text-xs hover:bg-[var(--color-brand-purple)] hover:text-white transition-all">
                                {{ __('View') }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-20 text-center">
                    <flux:icon name="inbox" class="size-16 text-zinc-200 mx-auto mb-4" />
                    <h3 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('No active challenges') }}</h3>
                    <p class="text-zinc-500">{{ __('Check back soon for new opportunities!') }}</p>
                </div>
            @endforelse
        </div>
    </section>

    <!-- Past Challenges -->
    @if ($pastChallenges->count() > 0)
        <section class="space-y-8">
            <h2 class="text-2xl font-black text-zinc-900 dark:text-white flex items-center gap-3">
                <div class="size-8 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                    <flux:icon name="archive-box" class="size-5 text-zinc-500" />
                </div>
                {{ __('Past Challenges') }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($pastChallenges as $challenge)
                    <a href="{{ route('challenges.show', $challenge->custom_link) }}"
                        class="group bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 p-6 hover:shadow-xl transition-all block">
                        <div class="flex items-center justify-between mb-4">
                            <span
                                class="text-[10px] font-black uppercase tracking-widest text-[var(--color-brand-purple)]">#{{ $challenge->hashtag }}</span>
                            <flux:icon name="chevron-right"
                                class="size-4 text-zinc-300 group-hover:translate-x-1 transition-transform" />
                        </div>
                        <h4 class="font-bold text-zinc-900 dark:text-white mb-4">{{ $challenge->title }}</h4>

                        @if ($challenge->winner)
                            <div class="flex items-center gap-2 pt-4 border-t border-zinc-50 dark:border-zinc-800">
                                <div class="size-6 rounded-full bg-yellow-100 overflow-hidden ring-2 ring-yellow-400">
                                    @if ($challenge->winner->profile_picture_url)
                                        <img src="{{ $challenge->winner->profile_picture_url }}"
                                            class="size-full object-cover">
                                    @endif
                                </div>
                                <span
                                    class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">{{ __('Winner') }}:
                                    <span
                                        class="text-zinc-900 dark:text-white">{{ $challenge->winner->name }}</span></span>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $pastChallenges->links() }}
            </div>
        </section>
    @endif
</div>
