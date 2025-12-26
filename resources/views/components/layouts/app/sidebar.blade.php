<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">

        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
            <img src="{{ asset('assets/allsers.png') }}" alt="{{ config('app.name') }}" class="h-8 w-8" />
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.group :heading="__('Platform')" class="grid">
                <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group :heading="__('Apps')" class="grid mt-4">
                <flux:navlist.item icon="magnifying-glass" href="#">{{ __('Finder') }}</flux:navlist.item>
                <flux:navlist.item icon="chat-bubble-left-right" href="#">{{ __('Chat') }}</flux:navlist.item>
                <flux:navlist.item icon="bell" href="#" badge="12">{{ __('Notifications') }}</flux:navlist.item>
                <flux:navlist.item icon="bookmark" :href="route('bookmarks')" wire:navigate>{{ __('Bookmarks') }}</flux:navlist.item>
                <flux:navlist.item icon="user" :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}
                </flux:navlist.item>
            </flux:navlist.group>
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
            <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()"
                icon:trailing="chevrons-up-down" data-test="sidebar-menu-button" />

            <flux:menu class="w-[220px]">
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
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
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
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

    {{ $slot }}

    @fluxScripts
</body>

</html>