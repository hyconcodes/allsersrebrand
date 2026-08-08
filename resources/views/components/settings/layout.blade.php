@props(['active' => 'profile'])

<div class="mx-auto w-full max-w-lg">
    <x-top-bar>
        <x-slot:left>
            <div class="flex items-center gap-6 overflow-x-auto">
                @php
                    $tabs = [
                        ['label' => __('Profile'), 'active' => $active === 'profile', 'route' => 'profile.edit'],
                        ['label' => __('Password'), 'active' => $active === 'password', 'route' => 'user-password.edit'],
                    ];

                    if (\Laravel\Fortify\Features::canManageTwoFactorAuthentication()) {
                        $tabs[] = ['label' => __('2FA'), 'active' => $active === 'two-factor', 'route' => 'two-factor.show'];
                    }

                    $tabs[] = ['label' => __('Appearance'), 'active' => $active === 'appearance', 'route' => 'appearance.edit'];
                @endphp
                @foreach ($tabs as $tab)
                    <a href="{{ route($tab['route']) }}" wire:navigate
                        class="relative py-3 text-sm font-bold whitespace-nowrap transition-all {{ $tab['active'] ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                        {{ $tab['label'] }}
                        @if ($tab['active'])
                            <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full"></div>
                        @endif
                    </a>
                @endforeach
            </div>
        </x-slot:left>
    </x-top-bar>

    <div class="px-4 py-6 space-y-8">
        {{ $slot }}
    </div>
</div>
