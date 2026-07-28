<?php
use Livewire\Volt\Component;

new class extends Component {
    // No specific logic needed, purely navigational
}; ?>

@php
    $user = auth()->user();
    $profileUrl = $user && $user->isArtisan()
        ? route('artisan.profile', $user)
        : route('user.profile', $user);
@endphp

<div class="flex items-center justify-between mb-1 border-b border-zinc-200/50 dark:border-zinc-800/50 sticky top-0 z-30 bg-white dark:bg-zinc-950">
    <div class="flex items-center gap-6">
        <a href="{{ route('dashboard', ['tab' => 'for-you']) }}"
            class="relative py-3 text-sm font-bold transition-all {{ request('tab', 'for-you') === 'for-you' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('For you') }}
            @if (request('tab', 'for-you') === 'for-you')
                <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full">
                </div>
            @endif
        </a>
        <a href="{{ route('dashboard', ['tab' => 'local']) }}"
            class="relative py-3 text-sm font-bold transition-all {{ request('tab') === 'local' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('Local') }}
            @if (request('tab') === 'local')
                <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full">
                </div>
            @endif
        </a>
    </div>

    @auth
        <div x-data="{ menuOpen: false }" class="relative">
            <button @click="menuOpen = !menuOpen"
                class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 hover:ring-2 hover:ring-zinc-300 dark:hover:ring-zinc-600 transition-all overflow-hidden text-xs font-bold shrink-0">
                @if ($user->profile_picture_url)
                    <img src="{{ $user->profile_picture_url }}" class="size-full object-cover" />
                @else
                    {{ $user->initials() }}
                @endif
            </button>

            <div x-show="menuOpen"
                x-transition:enter="transition-all duration-200 ease-out"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition-all duration-150 ease-in"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.away="menuOpen = false"
                @keydown.escape.window="menuOpen = false"
                class="hidden lg:block absolute right-0 top-full mt-2 w-[240px] bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xl z-50 overflow-hidden"
                x-cloak>
                <div class="p-3">
                    <div class="flex items-center gap-3 px-1 py-1.5">
                        <span class="relative flex size-9 shrink-0 overflow-hidden rounded-lg">
                            @if ($user->profile_picture_url)
                                <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                            @else
                                <span class="flex size-full items-center justify-center rounded-lg bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-100 text-xs font-bold">{{ $user->initials() }}</span>
                            @endif
                        </span>
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                            <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-zinc-100 dark:border-zinc-800 py-1">
                    <a href="{{ $profileUrl }}" wire:navigate @click="menuOpen = false"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <flux:icon name="user" class="size-4 text-zinc-400" />
                        {{ __('View Profile') }}
                    </a>
                    <a href="{{ route('bookmarks') }}" wire:navigate @click="menuOpen = false"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <flux:icon name="bookmark" class="size-4 text-zinc-400" />
                        {{ __('Bookmarks') }}
                    </a>
                    <a href="{{ route('profile.edit') }}" wire:navigate @click="menuOpen = false"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <flux:icon name="cog" class="size-4 text-zinc-400" />
                        {{ __('Settings') }}
                    </a>
                </div>

                <div class="border-t border-zinc-100 dark:border-zinc-800 px-4 py-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Appearance') }}</span>
                        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="scale-90 origin-right">
                            <flux:radio value="light" icon="sun" />
                            <flux:radio value="dark" icon="moon" />
                        </flux:radio.group>
                    </div>
                </div>

                @if ($user->isAdmin())
                    <div class="border-t border-zinc-100 dark:border-zinc-800 py-1">
                        <a href="{{ route('admin.dashboard') }}" wire:navigate @click="menuOpen = false"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-purple-600 dark:text-purple-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <flux:icon name="shield-check" class="size-4" />
                            {{ __('Admin Dashboard') }}
                        </a>
                    </div>
                @endif

                <div class="border-t border-zinc-100 dark:border-zinc-800 py-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" @click="menuOpen = false"
                            class="flex items-center gap-3 w-full px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <flux:icon name="arrow-right-start-on-rectangle" class="size-4" />
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>

            <template x-teleport="body">
                <div x-show="menuOpen"
                    x-transition:enter="transition-opacity duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @keydown.escape.window="menuOpen = false"
                    class="lg:hidden fixed inset-0 z-[9999]"
                    x-cloak>
                    <div class="absolute inset-0 bg-black/50" @click="menuOpen = false"></div>
                    <div x-show="menuOpen"
                        x-transition:enter="transition-transform duration-300 ease-out"
                        x-transition:enter-start="translate-y-full"
                        x-transition:enter-end="translate-y-0"
                        x-transition:leave="transition-transform duration-200 ease-in"
                        x-transition:leave-start="translate-y-0"
                        x-transition:leave-end="translate-y-full"
                        @click.away="menuOpen = false"
                        class="absolute bottom-0 left-0 right-0 bg-white dark:bg-zinc-950 rounded-t-2xl border-t border-zinc-200 dark:border-zinc-800 max-h-[85vh] overflow-y-auto shadow-2xl pb-[env(safe-area-inset-bottom,1rem)]">
                        <div class="flex items-center justify-center pt-3 pb-1">
                            <div class="w-8 h-1 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                        </div>

                        <div class="px-4 pt-2 pb-3">
                            <div class="flex items-center gap-3">
                                <span class="relative flex size-12 shrink-0 overflow-hidden rounded-xl">
                                    @if ($user->profile_picture_url)
                                        <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                                    @else
                                        <span class="flex size-full items-center justify-center rounded-xl bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-100 text-sm font-bold">{{ $user->initials() }}</span>
                                    @endif
                                </span>
                                <div class="grid flex-1 text-start">
                                    <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                                    <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-zinc-100 dark:border-zinc-800 py-1">
                            <a href="{{ $profileUrl }}" wire:navigate @click="menuOpen = false"
                                class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <flux:icon name="user" class="size-5 text-zinc-400" />
                                {{ __('View Profile') }}
                            </a>
                            <a href="{{ route('bookmarks') }}" wire:navigate @click="menuOpen = false"
                                class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <flux:icon name="bookmark" class="size-5 text-zinc-400" />
                                {{ __('Bookmarks') }}
                            </a>
                            <a href="{{ route('profile.edit') }}" wire:navigate @click="menuOpen = false"
                                class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <flux:icon name="cog" class="size-5 text-zinc-400" />
                                {{ __('Settings') }}
                            </a>
                        </div>

                        <div class="border-t border-zinc-100 dark:border-zinc-800 px-4 py-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Appearance') }}</span>
                                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                                </flux:radio.group>
                            </div>
                        </div>

                        @if ($user->isAdmin())
                            <div class="border-t border-zinc-100 dark:border-zinc-800 py-1">
                                <a href="{{ route('admin.dashboard') }}" wire:navigate @click="menuOpen = false"
                                    class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-purple-600 dark:text-purple-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <flux:icon name="shield-check" class="size-5" />
                                    {{ __('Admin Dashboard') }}
                                </a>
                            </div>
                        @endif

                        <div class="border-t border-zinc-100 dark:border-zinc-800 py-1 mb-2">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" @click="menuOpen = false"
                                    class="flex items-center gap-3 w-full px-4 py-3 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <flux:icon name="arrow-right-start-on-rectangle" class="size-5" />
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endauth
</div>
