<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
    @stack('head')
    <style>
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
    </style>
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800 scroll-m-0 pb-6 lg:pb-0">
    @auth
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">

            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <img src="{{ asset('assets/allsers.png') }}" alt="{{ config('app.name') }}" class="h-8 w-8" />
            </a>

            <flux:navlist variant="outline">

                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                        wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="fire" :href="route('challenges.index')"
                        :current="request()->routeIs('challenges.*') || request()->routeIs('challenge.*')" wire:navigate>
                        {{ __('Challenges') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Apps')" class="grid mt-4">
                    <flux:navlist.item icon="chat-bubble-left-right" :href="route('chat')"
                        :current="request()->routeIs('chat*')" :badge="auth()->user()->unreadMessagesCount() ?: null"
                        wire:navigate>{{ __('Chat') }}</flux:navlist.item>
                    <flux:navlist.item icon="bell" :href="route('notifications')"
                        :badge="auth()->user()->unreadNotifications->count() ?: null" wire:navigate>
                        {{ __('Notifications') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="bookmark" :href="route('bookmarks')" wire:navigate>{{ __('Bookmarks') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="user" :href="route('artisan.profile', auth()->user())" wire:navigate>
                        {{ __('Profile') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                @if (auth()->user()->isAdmin())
                    <flux:navlist.group :heading="__('Admin')" class="grid mt-4">
                        <flux:navlist.item icon="shield-check" :href="route('admin.reports')"
                            :current="request()->routeIs('admin.reports')" wire:navigate>
                            {{ __('Reports') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <div class="px-2 mt-6">
                <h3 class="px-2 mb-2 text-xs font-medium text-zinc-500 uppercase tracking-wider">{{ __('Filters') }}</h3>
                <div class="space-y-3">
                    <div class="space-y-1">
                        <label class="px-2 text-xs text-zinc-600 dark:text-zinc-400">{{ __('Location') }}</label>
                        <div class="relative">
                            <select
                                class="w-full text-sm dark:bg-zinc-800 dark:text-zinc-200 border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 focus:ring-[var(--color-brand-purple)] focus:border-[var(--color-brand-purple)] appearance-none">
                                <option>{{ __('All Locations') }}</option>
                                <option>New York</option>
                                <option>London</option>
                                <option>Paris</option>
                            </select>
                            <flux:icon name="chevron-down"
                                class="absolute right-3 top-2.5 size-4 text-zinc-400 pointer-events-none" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="px-2 text-xs text-zinc-600 dark:text-zinc-400">{{ __('Rating') }}</label>
                        <div class="relative">
                            <select
                                class="w-full text-sm dark:bg-zinc-800 dark:text-zinc-200 border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 focus:ring-[var(--color-brand-purple)] focus:border-[var(--color-brand-purple)] appearance-none">
                                <option>{{ __('All Ratings') }}</option>
                                <option>5 Stars</option>
                                <option>4+ Stars</option>
                                <option>3+ Stars</option>
                            </select>
                            <flux:icon name="chevron-down"
                                class="absolute right-3 top-2.5 size-4 text-zinc-400 pointer-events-none" />
                        </div>
                    </div>
                </div>
            </div>

            <flux:spacer />


            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile :name="auth()->user()->name" :avatar="auth()->user()->profile_picture_url"
                    :initials="auth()->user()->initials()" icon:trailing="chevrons-up-down"
                    data-test="sidebar-menu-button" />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    @if (auth()->user()->profile_picture_url)
                                        <img src="{{ auth()->user()->profile_picture_url }}"
                                            class="h-full w-full object-cover">
                                    @else
                                        <span
                                            class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {{ auth()->user()->initials() }}
                                        </span>
                                    @endif
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />
                    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                        <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                        <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    </flux:radio.group>
                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                            data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">

            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :avatar="auth()->user()->profile_picture_url" :initials="auth()->user()->initials()"
                    icon:trailing="chevron-down" />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    @if (auth()->user()->profile_picture_url)
                                        <img src="{{ auth()->user()->profile_picture_url }}"
                                            class="h-full w-full object-cover">
                                    @else
                                        <span
                                            class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {{ auth()->user()->initials() }}
                                        </span>
                                    @endif
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />
                    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                        <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                        <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    </flux:radio.group>
                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                            data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>
    @else
        <!-- Guest View: Simplified Header -->
        <flux:header
            class="sticky top-0 z-50 w-full bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between w-full">
                <a href="{{ route('home') }}" class="flex items-center space-x-2" wire:navigate>
                    <img src="{{ asset('assets/allsers.png') }}" alt="{{ config('app.name') }}" class="h-8 w-8" />
                    <span class="text-xl font-black text-zinc-900 dark:text-white hidden sm:block">Allsers</span>
                </a>

                <div class="flex items-center gap-3 sm:gap-6">
                    <a href="{{ route('login') }}"
                        class="text-sm font-bold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-colors"
                        wire:navigate>
                        {{ __('Log In') }}
                    </a>
                    <a href="{{ route('register') }}"
                        class="px-4 sm:px-6 py-2 bg-[var(--color-brand-purple)] text-white text-sm font-bold rounded-full hover:opacity-90 transition-all shadow-lg shadow-purple-500/20 whitespace-nowrap"
                        wire:navigate>
                        <span class="hidden sm:inline">{{ __('Join Allsers') }}</span>
                        <span class="sm:hidden">{{ __('Join') }}</span>
                    </a>
                </div>
            </div>
        </flux:header>
    @endauth

    <!-- <div class="@auth lg:ml-0 @endauth"> -->
    {{ $slot }}
    <!-- </div> -->

    <x-ui.toast />

    <livewire:ai-chat />

    @auth
        <!-- Mobile Bottom Nav -->
        <nav
            class="lg:hidden fixed bottom-0 left-0 right-0 z-[500] bg-white/80 dark:bg-zinc-900/80 backdrop-blur-lg border-t border-zinc-200 dark:border-zinc-800 px-6 pb-6 pt-3 shadow-[0_-10px_30px_rgba(0,0,0,0.05)]">
            <div class="flex items-center justify-between max-w-md mx-auto">
                <!-- Home -->
                <a href="{{ route('dashboard') }}" wire:navigate class="flex flex-col items-center gap-1 group">
                    <div
                        class="p-2 rounded-xl transition-all {{ request()->routeIs('dashboard') ? 'bg-[var(--color-brand-purple)] text-white shadow-lg shadow-purple-500/30' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                        <flux:icon name="home" :variant="request()->routeIs('dashboard') ? 'solid' : 'outline'"
                            class="size-6" />
                    </div>
                    <span
                        class="text-[10px] font-black uppercase tracking-widest {{ request()->routeIs('dashboard') ? 'text-[var(--color-brand-purple)]' : 'text-zinc-400' }}">Home</span>
                </a>

                <!-- Lila (Central AI Toggle) -->
                <button @click="$dispatch('open-lila')" class="relative -mt-12 flex flex-col items-center gap-2">
                    <div
                        class="size-16 rounded-full bg-gradient-to-tr from-[var(--color-brand-purple)] to-purple-400 p-1 shadow-2xl shadow-purple-500/40 ring-4 ring-white dark:ring-zinc-900">
                        <div class="size-full rounded-full overflow-hidden border-2 border-white/20">
                            <img src="{{ asset('assets/lila-avatar.png') }}"
                                class="size-full object-cover animate-lila-idle">
                        </div>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-[var(--color-brand-purple)]">Ask
                        Lila</span>
                </button>

                <!-- Chat -->
                <a href="{{ route('chat') }}" wire:navigate class="flex flex-col items-center gap-1 group relative">
                    <div
                        class="p-2 rounded-xl transition-all {{ request()->routeIs('chat*') ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                        <flux:icon name="chat-bubble-left-right"
                            :variant="request()->routeIs('chat*') ? 'solid' : 'outline'" class="size-6" />
                    </div>
                    @if ($unreadCount = auth()->user()->unreadMessagesCount())
                        <div
                            class="absolute top-1 right-2 size-4 bg-red-500 border border-white dark:border-zinc-900 rounded-full flex items-center justify-center text-[8px] font-black text-white shadow-lg">
                            {{ $unreadCount }}
                        </div>
                    @endif
                    <span
                        class="text-[10px] font-black uppercase tracking-widest {{ request()->routeIs('chat*') ? 'text-blue-600' : 'text-zinc-400' }}">Chat</span>
                </a>
            </div>
        </nav>
    @endauth

    @fluxScripts
</body>

</html>
