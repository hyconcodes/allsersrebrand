<x-layouts.app :title="__('Menu')">
    <div class="p-4 md:p-6 max-w-lg mx-auto space-y-6 pb-24">

        <!-- User Profile Header -->
        <div
            class="border-b border-zinc-800 pb-6 flex flex-col items-center text-center relative">
            <div
                class="absolute top-0 right-0 p-12 bg-[var(--color-brand-purple)]/5 rounded-full -mr-16 -mt-16 blur-3xl transition-all group-hover:bg-[var(--color-brand-purple)]/10">
            </div>

            <div
                class="size-20 rounded-2xl overflow-hidden border-4 border-white dark:border-zinc-800 shadow-xl relative z-10 bg-zinc-100 dark:bg-zinc-800">
                @if (auth()->user()->profile_picture_url)
                    <img src="{{ auth()->user()->profile_picture_url }}" class="size-full object-cover">
                @else
                    <div class="size-full flex items-center justify-center text-2xl font-bold text-zinc-400">
                        {{ auth()->user()->initials() }}
                    </div>
                @endif
            </div>

            <div class="mt-4 relative z-10">
                <h2 class="text-xl font-bold text-zinc-100">{{ auth()->user()->name }}
                </h2>
                <p class="text-xs font-bold text-zinc-500 mt-1">{{ auth()->user()->email }}</p>

                @if (auth()->user()->isArtisan())
                    <div
                        class="mt-2 flex items-center justify-center gap-1.5 text-xs font-medium text-purple-500 bg-purple-500/10 px-3 py-1 rounded-full">
                        <flux:icon name="check-badge" variant="solid" class="size-3" />
                        {{ auth()->user()->work ?? __('Verified Artisan') }}
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 w-full mt-6 relative z-10">
                <flux:button :href="route('profile.edit')" variant="filled" class="rounded-xl font-bold py-2.5"
                    wire:navigate>
                    {{ __('Edit Profile') }}
                </flux:button>

                @php
                    $profileUrl = auth()->user()->isArtisan()
                        ? route('artisan.profile', auth()->user())
                        : route('user.profile', auth()->user());
                @endphp

                <flux:button x-data="{
                    share() {
                        if (navigator.share) {
                            navigator.share({
                                title: '{{ auth()->user()->name }} on Allsers',
                                text: 'Check out my profile on Allsers!',
                                url: '{{ $profileUrl }}'
                            });
                        } else {
                            navigator.clipboard.writeText('{{ $profileUrl }}');
                            $dispatch('toast', { type: 'success', title: 'Link Copied', message: 'Profile link copied to clipboard!' });
                        }
                    }
                }" @click="share()" variant="outline"
                    class="rounded-xl font-bold py-2.5">
                    <flux:icon name="share" class="mr-2 size-4" />
                    {{ __('Share') }}
                </flux:button>
            </div>
        </div>

        <flux:separator />

        <!-- Notification Control -->
        <div class="space-y-3">
            <flux:heading size="sm" class="text-zinc-500 uppercase tracking-wider text-xs font-medium px-2">
                {{ __('Connectivity') }}</flux:heading>
            <livewire:dashboard.notification-toggle />
        </div>

        <flux:separator />

        <flux:navlist>
            <flux:navlist.group :heading="__('Platform')">
                <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>{{ __('Home') }}</flux:navlist.item>
                <flux:navlist.item icon="magnifying-glass" :href="route('finder')"
                    :current="request()->routeIs('finder')" wire:navigate>{{ __('Finder') }}</flux:navlist.item>
                <flux:navlist.item icon="fire" :href="route('challenges.index')"
                    :current="request()->routeIs('challenges.*')" wire:navigate>{{ __('Challenges') }}
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group :heading="__('Social')" class="mt-4">
                <flux:navlist.item icon="chat-bubble-left-right" :href="route('chat')"
                    :current="request()->routeIs('chat*')" :badge="auth()->user()->unreadMessagesCount() ?: null"
                    wire:navigate>{{ __('Chat') }}</flux:navlist.item>
                <flux:navlist.item icon="bell" :href="route('notifications')"
                    :badge="auth()->user()->unreadNotifications->count() ?: null" wire:navigate>
                    {{ __('Notifications') }}</flux:navlist.item>
                <flux:navlist.item icon="bookmark" :href="route('bookmarks')" wire:navigate>{{ __('Saved') }}
                </flux:navlist.item>
            </flux:navlist.group>

            @if (auth()->user()->isAdmin())
                <flux:navlist.group :heading="__('Admin')" class="mt-4">
                    <flux:navlist.item icon="shield-check" :href="route('admin.dashboard')"
                        :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Dashboard') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="flag" :href="route('admin.reports')"
                        :current="request()->routeIs('admin.reports')" wire:navigate>{{ __('Reports') }}
                    </flux:navlist.item>
                </flux:navlist.group>
            @endif
        </flux:navlist>

        <flux:separator />

        <div class="space-y-3">
            <flux:heading size="sm" class="text-zinc-500 uppercase tracking-wider text-xs font-medium px-2">
                {{ __('Appearance') }}</flux:heading>
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="device-phone-mobile">{{ __('Device') }}</flux:radio>
            </flux:radio.group>
        </div>

        <flux:separator />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:button type="submit" variant="danger" class="w-full rounded-xl py-3 font-bold"
                icon="arrow-right-start-on-rectangle">
                {{ __('Log Out') }}
            </flux:button>
        </form>

        <div class="pt-6 text-center">
            <p class="text-xs text-zinc-500">
                {{ config('app.name') }} &bull; v{{ config('app.version', '4.9') }} &bull; @yield('version', '2026')
            </p>
        </div>
    </div>
</x-layouts.app>
