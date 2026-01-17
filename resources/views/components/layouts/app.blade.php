<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main container="false" class="!p-0 md:!p-6 md:max-w-7xl md:mx-auto">
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
