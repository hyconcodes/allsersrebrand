{{-- Intentionally using a plain <main> instead of <flux:main> to prevent
     the Flux CSS rule :has(>[data-flux-main]) from applying display:grid
     to <body> which breaks inner two-column flex layouts (lg:flex-row). --}}
<x-layouts.app.sidebar :title="$title ?? null">
    <main class="!p-0 md:!p-6 md:max-w-7xl md:mx-auto">
        {{ $slot }}
    </main>
</x-layouts.app.sidebar>
