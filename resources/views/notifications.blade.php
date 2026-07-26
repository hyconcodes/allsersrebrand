<x-layouts.app :title="__('Notifications')">
    <div class="dashboard-two-col-layout w-full max-w-7xl mx-auto px-4 lg:px-0">
        <!-- Notifications Feed (Left Column) -->
        <div class="dashboard-feed-column">
            <livewire:dashboard.notifications-feed />
        </div>

        <!-- Right Sidebar (Reused from Dashboard) -->
        <div class="dashboard-sidebar space-y-6">
            <livewire:challenge.trending-widget />
            <livewire:dashboard.pros-widget />
        </div>
    </div>
</x-layouts.app>
