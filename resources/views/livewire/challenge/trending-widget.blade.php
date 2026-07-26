<?php

use App\Models\Challenge;
use Livewire\Volt\Component;

new class extends Component {
    public function with()
    {
        return [
            'challenges' => Challenge::withCount('posts')
                ->where('end_at', '>', now())
                ->orderByDesc('is_admin_challenge')
                ->latest()
                ->limit(4)
                ->get()
        ];
    }
}; ?>

<div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
    <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
        <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Trending Challenges') }}</h3>
        <a href="{{ route('challenges.index') }}" class="text-xs font-bold text-purple-600 hover:underline">{{ __('View All') }}</a>
    </div>

    <div class="px-5 py-4 space-y-5">
        @forelse($challenges as $challenge)
            <a href="{{ route('challenges.show', $challenge->custom_link) }}" class="block group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-zinc-400 uppercase font-bold tracking-normal mb-1">
                            {{ $challenge->is_admin_challenge ? __('Official') : __('Community') }}
                        </p>
                        <p class="font-bold text-zinc-800 dark:text-zinc-200 group-hover:text-purple-600 transition-colors leading-tight">
                            #{{ $challenge->hashtag }}
                        </p>
                        <p class="text-xs text-zinc-500 mt-1 font-medium">{{ number_format($challenge->posts_count) }} {{ __('posts submitted') }}</p>
                    </div>
                    @if($challenge->is_admin_challenge)
                        <flux:icon name="shield-check" class="size-4 text-purple-600 shrink-0" />
                    @endif
                </div>
            </a>
        @empty
            <div class="py-4 text-center">
                <p class="text-xs text-zinc-500">{{ __('No active challenges at the moment.') }}</p>
            </div>
        @endforelse
    </div>

    @if(auth()->check() && (auth()->user()->isArtisan() || auth()->user()->isAdmin()))
        <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
            <a href="{{ route('challenges.create') }}" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-white text-xs font-bold hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-all border border-zinc-200 dark:border-zinc-700">
                <flux:icon name="plus" class="size-4" />
                {{ __('Launch Challenge') }}
            </a>
        </div>
    @endif
</div>
