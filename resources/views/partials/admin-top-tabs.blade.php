@php
    $adminTabs = [
        ['label' => __('Control Center'), 'route' => 'admin.dashboard'],
        ['label' => __('Pings'), 'route' => 'admin.pings'],
        ['label' => __('Reports'), 'route' => 'admin.reports'],
        ['label' => __('Messages'), 'route' => 'admin.messages'],
        ['label' => __('Settings'), 'route' => 'admin.settings'],
    ];
@endphp

<div class="flex items-center gap-6 overflow-x-auto">
    @foreach ($adminTabs as $tab)
        <a href="{{ route($tab['route']) }}" wire:navigate
            class="relative py-3 text-sm font-bold whitespace-nowrap transition-all {{ request()->routeIs($tab['route']) ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ $tab['label'] }}
            @if (request()->routeIs($tab['route']))
                <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-[var(--color-brand-purple)] rounded-full"></div>
            @endif
        </a>
    @endforeach
</div>
