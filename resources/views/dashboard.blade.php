<?php

use Livewire\Volt\Component;

new class extends Component {}; ?>
<x-layouts.app :title="__('Home')">
    <div class="flex flex-col lg:flex-row gap-8 w-full max-w-7xl mx-auto px-4 lg:px-0">
        <!-- Main Feed (Left Column) -->
        <div class="flex-1 w-full max-w-2xl mx-auto lg:mx-0">
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
                            <h3 class="font-black text-sm text-zinc-900">{{ __('Boost your visibility!') }}</h3>
                            <span
                                class="text-xs font-bold text-[var(--color-brand-purple)]">{{ $completion['percentage'] }}%</span>
                        </div>
                        <p class="text-xs text-zinc-600 mb-2 leading-relaxed">
                            {{ __('Clients trust complete profiles. Fill in your missing details to appear higher in search results.') }}
                        </p>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('profile.edit') }}"
                                class="text-[11px] font-black uppercase tracking-wider text-[var(--color-brand-purple)] hover:underline">
                                {{ __('Finish Profile') }}
                            </a>
                            <div class="flex-1 h-1.5 bg-zinc-200 rounded-full overflow-hidden">
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
        <div class="hidden lg:block w-80 space-y-6">
            <livewire:challenge.trending-widget />
            @include('partials.pros-widget')
        </div>
    </div>
</x-layouts.app>
