<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
    @stack('head')
    <style>
        /* Disable pinch-zooming for a native app feel */
        html,
        body {
            touch-action: pan-x pan-y;
        }

        /* Hide scrollbar for ALL elements within flux-sidebar and the sidebar itself */
        [flux-sidebar],
        [flux-sidebar] * {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }

        [flux-sidebar]::-webkit-scrollbar,
        [flux-sidebar] *::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        /* Bottom-sheet modals on mobile */
        @media (max-width: 640px) {
            [data-flux-modal] > div:last-child {
                align-items: flex-end !important;
            }
            [data-flux-modal] > div:last-child > * {
                border-radius: 1.5rem 1.5rem 0 0 !important;
                max-height: 85vh !important;
                margin-top: auto !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
    <script>
        // Strictly prevent pinch-to-zoom on mobile devices
        document.addEventListener('touchstart', function(event) {
            if (event.touches.length > 1) {
                event.preventDefault();
            }
        }, {
            passive: false
        });

        document.addEventListener('gesturestart', function(event) {
            event.preventDefault();
        });
    </script>
</head>

<body class="min-h-screen bg-white dark:bg-zinc-950 antialiased {{ auth()->check() ? 'lg:pl-[68px] pb-16 lg:pb-0' : 'pb-6 lg:pb-0' }} scroll-m-0">
    @auth
        <!-- Desktop Sidebar (icon-only, X-style) -->
        <aside class="fixed left-0 top-0 h-screen w-[68px] border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 flex-col items-center py-3 z-50 hidden lg:flex">
            <a href="{{ route('dashboard') }}" wire:navigate class="size-10 flex items-center justify-center mb-6 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <img src="{{ asset('assets/allsers.png') }}" class="h-7 w-7" fetchpriority="high" />
            </a>

            <nav class="flex flex-col items-center gap-1">
                @php
                    $desktopNav = [
                        ['icon' => 'home', 'route' => 'dashboard', 'label' => 'Home', 'active' => request()->routeIs('dashboard'), 'badge' => null],
                        ['icon' => 'magnifying-glass', 'route' => 'finder', 'label' => 'Search', 'active' => request()->routeIs('finder'), 'badge' => null],
                        ['icon' => 'sparkles', 'route' => 'lila', 'label' => 'Lila AI', 'active' => request()->routeIs('lila'), 'badge' => null],
                        ['icon' => 'bell', 'route' => 'notifications', 'label' => 'Notifications', 'active' => request()->routeIs('notifications'), 'badge' => auth()->user()->unreadNotifications->count() ?: null],
                        ['icon' => 'chat-bubble-left-right', 'route' => 'chat', 'label' => 'Messages', 'active' => request()->routeIs('chat*'), 'badge' => auth()->user()->unreadMessagesCount() ?: null],
                        ['icon' => 'bookmark', 'route' => 'bookmarks', 'label' => 'Bookmarks', 'active' => request()->routeIs('bookmarks'), 'badge' => null],
                    ];
                @endphp

                @foreach ($desktopNav as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate
                        class="group relative size-11 rounded-full flex items-center justify-center transition-colors {{ $item['active'] ? 'text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                        <span class="relative">
                            <flux:icon name="{{ $item['icon'] }}" class="size-5" />
                            @if ($item['badge'])
                                <span class="absolute -top-1 -right-1 size-2 rounded-full bg-purple-500"></span>
                            @endif
                        </span>
                        <span class="absolute left-full ml-3 px-2 py-1 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs rounded-md whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-lg border border-zinc-200 dark:border-zinc-700">
                            {{ $item['label'] }}
                        </span>
                    </a>
                @endforeach
            </nav>

            <flux:spacer />

            <!-- Post button -->
            <button x-data="" x-on:click="$dispatch('open-create-post')"
                class="size-11 rounded-full bg-purple-600 hover:bg-purple-500 text-white flex items-center justify-center transition-colors mb-4">
                <flux:icon name="pencil" class="size-4" />
            </button>

            <!-- User avatar -->
            <flux:dropdown position="top" align="start">
                <button class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 hover:ring-2 hover:ring-zinc-300 dark:hover:ring-zinc-600 transition-all overflow-hidden text-xs font-bold">
                    @if (auth()->user()->profile_picture_url)
                        <img loading="lazy" src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover" />
                    @else
                        {{ auth()->user()->initials() }}
                    @endif
                </button>

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    @if (auth()->user()->profile_picture_url)
                                        <img loading="lazy" src="{{ auth()->user()->profile_picture_url }}" class="h-full w-full object-cover">
                                    @else
                                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-100">{{ auth()->user()->initials() }}</span>
                                    @endif
                                </span>
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                        @if (auth()->user()->isAdmin())
                            <flux:menu.item :href="route('admin.dashboard')" icon="shield-check" wire:navigate>{{ __('Admin') }}</flux:menu.item>
                        @endif
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                        <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                        <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    </flux:radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </aside>
    @else
        <!-- Guest Header -->
        <header class="sticky top-0 z-50 w-full bg-white/80 dark:bg-zinc-950/80 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between w-full">
                <a href="{{ route('home') }}" class="flex items-center space-x-2" wire:navigate>
                    <img loading="lazy" src="{{ asset('assets/allsers.png') }}" alt="{{ config('app.name') }}" class="h-8 w-8" />
                    <span class="text-xl font-bold text-zinc-900 dark:text-white hidden sm:block">Allsers</span>
                </a>
                <div class="flex items-center gap-3 sm:gap-6">
                    <a href="{{ route('login') }}"
                        class="text-sm font-bold text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-white transition-colors" wire:navigate>
                        {{ __('Log In') }}
                    </a>
                    <a href="{{ route('register') }}"
                        class="px-4 sm:px-6 py-2 bg-purple-600 text-white text-sm font-bold rounded-full hover:bg-purple-500 transition-all whitespace-nowrap"
                        wire:navigate>
                        <span class="hidden sm:inline">{{ __('Join Allsers') }}</span>
                        <span class="sm:hidden">{{ __('Join') }}</span>
                    </a>
                </div>
            </div>
        </header>
    @endauth

    {{ $slot }}

    <x-ui.toast />

    <x-lightbox />

    <livewire:ai-chat />

    @auth
        <livewire:dashboard.create-post-modal />

    @endauth

    @auth
        <!-- Mobile Bottom Nav (5 items, X-style) -->
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-[500] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-lg border-t border-zinc-200 dark:border-zinc-800 pb-[env(safe-area-inset-bottom,0.5rem)] pt-1">
            <div class="flex items-center justify-around max-w-lg mx-auto pb-1">
                <a href="{{ route('dashboard') }}" wire:navigate
                    class="flex flex-col items-center gap-0.5 pt-1 {{ request()->routeIs('dashboard') ? 'text-purple-500' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    <flux:icon name="home" :variant="request()->routeIs('dashboard') ? 'solid' : 'outline'" class="size-6" />
                    <span class="text-[10px] font-medium">Home</span>
                </a>

                <a href="{{ route('finder') }}" wire:navigate
                    class="flex flex-col items-center gap-0.5 pt-1 {{ request()->routeIs('finder') ? 'text-purple-500' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    <flux:icon name="magnifying-glass" :variant="request()->routeIs('finder') ? 'solid' : 'outline'" class="size-6" />
                    <span class="text-[10px] font-medium">Search</span>
                </a>

                <!-- Lila (Center, elevated) -->
                <a href="{{ route('lila') }}" wire:navigate class="relative -mt-4">
                    <div class="size-12 rounded-full bg-purple-600 flex items-center justify-center shadow-lg shadow-purple-500/30 hover:bg-purple-500 transition-colors">
                        <img loading="lazy" src="{{ asset('assets/lila-avatar.png') }}" class="size-10 rounded-full object-cover" />
                    </div>
                </a>

                <a href="{{ route('chat') }}" wire:navigate
                    class="flex flex-col items-center gap-0.5 pt-1 relative {{ request()->routeIs('chat*') ? 'text-purple-500' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    <span class="relative">
                        <flux:icon name="chat-bubble-left-right" :variant="request()->routeIs('chat*') ? 'solid' : 'outline'" class="size-6" />
                        @if ($unreadCount = auth()->user()->unreadMessagesCount())
                            <span class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-red-500 border border-white dark:border-zinc-950"></span>
                        @endif
                    </span>
                    <span class="text-[10px] font-medium">Inbox</span>
                </a>

                <a href="{{ route('notifications') }}" wire:navigate
                    class="flex flex-col items-center gap-0.5 pt-1 relative {{ request()->routeIs('notifications') ? 'text-purple-500' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    <span class="relative">
                        <flux:icon name="bell" :variant="request()->routeIs('notifications') ? 'solid' : 'outline'" class="size-6" />
                        @if (auth()->user()->unreadNotifications->count())
                            <span class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-red-500 border border-white dark:border-zinc-950"></span>
                        @endif
                    </span>
                    <span class="text-[10px] font-medium">Alerts</span>
                </a>
            </div>
        </nav>

        <!-- Post FAB (mobile) -->
        <button x-data="" x-on:click="$dispatch('open-create-post')"
            class="lg:hidden fixed bottom-20 right-4 z-[500] size-14 rounded-full bg-purple-600 text-white shadow-lg shadow-purple-500/30 flex items-center justify-center hover:bg-purple-500 transition-colors active:scale-95">
            <flux:icon name="plus" class="size-6" />
        </button>

        <livewire:onesignal-handler />
    @endauth

    @fluxScripts
    @stack('scripts')
    <x-pwa-scripts />

    <script>
        document.addEventListener('play', function(e) {
            if (e.target.tagName.toLowerCase() === 'video') {
                const videos = document.getElementsByTagName('video');
                for (let i = 0; i < videos.length; i++) {
                    if (videos[i] !== e.target && !videos[i].paused) {
                        videos[i].pause();
                    }
                }
            }
        }, true);

        document.addEventListener('livewire:navigated', function() {
            window.scrollTo({ top: 0, behavior: 'instant' });
        });
    </script>
</body>

</html>
