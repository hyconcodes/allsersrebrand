<?php

use Livewire\Volt\Component;

new class extends Component {}; ?>
<x-layouts.app :title="__('Allsers - Feeds')">
    <livewire:dashboard.notification-prompt />
    <div class="w-full max-w-7xl mx-auto px-4 lg:px-0">
        <livewire:dashboard.location-permission />
    </div>
    <div class="dashboard-two-col-layout w-full max-w-7xl mx-auto px-4 lg:px-0">
        <!-- Main Feed (Left Column) -->
        <div class="dashboard-feed-column">
            @if (auth()->user()->role === 'artisan' &&
                    !auth()->user()->is_admin &&
                    ($completion = auth()->user()->profileCompletion()) &&
                    !$completion['is_complete']
            )
                <div
                    class="mb-6 p-4 bg-[var(--color-brand-purple)]/10 border border-[var(--color-brand-purple)]/20 rounded-2xl flex items-center gap-4">
                    <div
                        class="size-12 rounded-full bg-[var(--color-brand-purple)] flex items-center justify-center shrink-0 shadow-lg shadow-[var(--color-brand-purple)]/20">
                        <flux:icon name="sparkles" class="size-6 text-white" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Boost your visibility!') }}</h3>
                            <span
                                class="text-xs font-bold text-[var(--color-brand-purple)]">{{ $completion['percentage'] }}%</span>
                        </div>
                        <p class="text-xs text-zinc-600 mb-2 leading-relaxed">
                            {{ __('Clients trust complete profiles. Fill in your missing details to appear higher in search results.') }}
                        </p>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('profile.edit') }}"
                                class="text-xs font-bold uppercase tracking-normal text-[var(--color-brand-purple)] hover:underline">
                                {{ __('Finish Profile') }}
                            </a>
                            <div class="flex-1 h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                <div class="h-full bg-[var(--color-brand-purple)] transition-all duration-500"
                                    style="width: {{ $completion['percentage'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <livewire:dashboard.feed />
        </div>

        <!-- Right Sidebar (Trending & Pros) -->
        <div class="dashboard-sidebar space-y-6">
            <livewire:challenge.trending-widget />
            <livewire:dashboard.pros-widget />
        </div>
    </div>
</x-layouts.app>
