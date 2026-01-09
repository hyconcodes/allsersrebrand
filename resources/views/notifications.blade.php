<x-layouts.app :title="__('Notifications')">
    <div class="flex flex-col lg:flex-row gap-8 w-full max-w-7xl mx-auto px-4 lg:px-0">
        <!-- Notifications Feed (Left Column) -->
        <div class="flex-1 w-full max-w-2xl mx-auto lg:mx-0">
            <livewire:dashboard.notifications-feed />
        </div>

        <!-- Right Sidebar (Reused from Dashboard) -->
        <div class="hidden lg:block w-80 space-y-6">
            <livewire:challenge.trending-widget />
            <livewire:dashboard.pros-widget />
        </div>
    </div>
</x-layouts.app>
