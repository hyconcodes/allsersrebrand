<!-- Suggested Pros Widget -->
<div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
    <h3 class="font-bold text-lg mb-4 text-zinc-900 dark:text-zinc-100">{{ __('Work with Pros') }}</h3>
    <div class="space-y-4">
        <!-- Pro 1 -->
        <div class="flex items-center gap-3">
            <div
                class="size-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-700 font-bold text-xs">
                AL</div>
            <div class="flex-1 min-w-0">
                <h4 class="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">Alex Lewis</h4>
                <p class="text-xs text-zinc-500 truncate">Carpenter • 4.9 <span class="text-yellow-500">★</span></p>
            </div>
            <button
                class="bg-[var(--color-brand-purple)] text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:bg-[var(--color-brand-purple)]/90 transition-colors">
                {{ __('Hire') }}
            </button>
        </div>
        <!-- Pro 2 -->
        <div class="flex items-center gap-3">
            <div
                class="size-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-700 font-bold text-xs">
                RK</div>
            <div class="flex-1 min-w-0">
                <h4 class="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">Rachel King</h4>
                <p class="text-xs text-zinc-500 truncate">Architect • 5.0 <span class="text-yellow-500">★</span></p>
            </div>
            <button
                class="bg-gray-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 text-xs font-semibold px-3 py-1.5 rounded-full hover:bg-gray-200 transition-colors">
                {{ __('Follow') }}
            </button>
        </div>
        <!-- Pro 3 -->
        <div class="flex items-center gap-3">
            <div
                class="size-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">
                JM</div>
            <div class="flex-1 min-w-0">
                <h4 class="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">John Miller</h4>
                <p class="text-xs text-zinc-500 truncate">HVAC Tech • 4.8 <span class="text-yellow-500">★</span></p>
            </div>
            <button
                class="bg-gray-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 text-xs font-semibold px-3 py-1.5 rounded-full hover:bg-gray-200 transition-colors">
                {{ __('Follow') }}
            </button>
        </div>
    </div>
    <button class="w-full mt-4 text-xs font-medium text-[var(--color-brand-purple)] hover:underline">
        {{ __('View all professionals') }}
    </button>
</div>