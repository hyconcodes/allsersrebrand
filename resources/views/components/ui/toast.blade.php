<div x-data="{ 
        notifications: [],
        add(e) {
            this.notifications.push({
                id: e.detail.id || Date.now(),
                type: e.detail.type || 'info',
                title: e.detail.title || '',
                message: e.detail.message || '',
                autohide: e.detail.autohide ?? true,
                timeout: e.detail.timeout || 5000
            });
        },
        remove(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }" @toast.window="add($event)"
    class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
    <template x-for="notification in notifications" :key="notification.id">
        <div x-data="{ 
                show: false,
                init() {
                    this.$nextTick(() => this.show = true);
                    if (notification.autohide) {
                        setTimeout(() => this.show = false, notification.timeout);
                        setTimeout(() => this.remove(notification.id), notification.timeout + 500);
                    }
                }
            }" x-show="show" x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="translate-y-4 opacity-0 scale-95"
            x-transition:enter-end="translate-y-0 opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="translate-y-0 opacity-100 scale-100"
            x-transition:leave-end="translate-y-4 opacity-0 scale-95"
            class="pointer-events-auto bg-white dark:bg-zinc-900 rounded-2xl p-4 shadow-2xl border border-zinc-100 dark:border-zinc-800 flex items-start gap-4 overflow-hidden relative group"
            :class="{
                'border-l-4 border-l-blue-500': notification.type === 'info',
                'border-l-4 border-l-green-500': notification.type === 'success',
                'border-l-4 border-l-red-500': notification.type === 'error',
                'border-l-4 border-l-yellow-500': notification.type === 'warning'
            }">
            <div class="shrink-0">
                <template x-if="notification.type === 'success'">
                    <div
                        class="size-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </template>
                <template x-if="notification.type === 'info'">
                    <div
                        class="size-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </template>
                <template x-if="notification.type === 'error'">
                    <div
                        class="size-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-600">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </template>
            </div>

            <div class="flex-1 min-w-0">
                <template x-if="notification.title">
                    <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100" x-text="notification.title"></h4>
                </template>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed" x-text="notification.message"></p>
            </div>

            <button @click="show = false; setTimeout(() => remove(notification.id), 300)"
                class="shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>