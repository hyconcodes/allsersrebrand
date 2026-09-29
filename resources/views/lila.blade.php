<x-layouts.app :title="__('Ask Lila')">
    <livewire:location-sync context="lila" />
    <div class="flex flex-col h-[calc(100dvh-5rem)] w-full">
        <x-top-bar title="{{ __('Lila') }}" />
        <div class="flex-1 min-h-0">
            <livewire:ai-chat :full-page="true" />
        </div>
    </div>
</x-layouts.app>
