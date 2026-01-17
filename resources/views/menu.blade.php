<x-layouts.app :title="__('Menu')">
    <div class="p-4 md:p-6 max-w-lg mx-auto space-y-8">

        <div class="flex items-center gap-4">
            <flux:heading size="xl">{{ __('Menu') }}</flux:heading>
        </div>

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
                <flux:navlist.item icon="user" :href="route('artisan.profile', auth()->user())" wire:navigate>
                    {{ __('Profile') }}</flux:navlist.item>
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

            <flux:navlist.group :heading="__('Settings')" class="mt-4">
                <flux:navlist.item icon="cog" :href="route('profile.edit')" wire:navigate>{{ __('Settings') }}
                </flux:navlist.item>
            </flux:navlist.group>
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
            <flux:button type="submit" variant="danger" class="w-full" icon="arrow-right-start-on-rectangle">
                {{ __('Log Out') }}
            </flux:button>
        </form>

        <div class="pt-6 text-center">
            <p class="text-xs text-zinc-400">
                {{ config('app.name') }} v{{ config('app.version', '1.0.0') }}
            </p>
        </div>
    </div>
</x-layouts.app>
