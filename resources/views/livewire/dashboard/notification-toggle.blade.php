<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $isSubscribed = false;

    public function mount()
    {
        // We handle the actual state transition in JS,
        // but we can initialize based on whether we have a player ID.
        $this->isSubscribed = !empty(Auth::user()->onesignal_player_id);
    }
}; ?>

<div x-data="{
    subscribed: @entangle('isSubscribed'),
    loading: false,
    async toggleNotifications() {
        if (this.loading) return;
        this.loading = true;

        try {
            // Helper function to execute OneSignal logic safely
            const oneSignalAction = () => {
                return new Promise((resolve, reject) => {
                    const OneSignal = window.OneSignal;
                    if (OneSignal) {
                        resolve(OneSignal);
                    } else {
                        window.OneSignalDeferred = window.OneSignalDeferred || [];
                        window.OneSignalDeferred.push((OS) => resolve(OS));
                    }
                });
            };

            const OneSignal = await oneSignalAction();
            const permission = OneSignal.Notifications.permission;

            if (this.subscribed) {
                // Opt out
                await OneSignal.User.PushSubscription.optOut();
                this.subscribed = false;
                $dispatch('toast', {
                    type: 'info',
                    title: 'Notifications Paused',
                    message: 'You will no longer receive push notifications.'
                });
            } else {
                // Opt in / Request permission
                if (permission !== 'granted') {
                    await OneSignal.Notifications.requestPermission();
                }

                await OneSignal.User.PushSubscription.optIn();

                // Wait a bit for the ID to be generated if it hasn't been
                let retryCount = 0;
                let id = null;
                while (retryCount < 5 && !id) {
                    id = OneSignal.User.PushSubscription.id;
                    if (!id) {
                        await new Promise(r => setTimeout(r, 1000));
                        retryCount++;
                    }
                }

                if (id) {
                    this.subscribed = true;
                    $dispatch('toast', {
                        type: 'success',
                        title: 'Notifications Enabled',
                        message: 'You are now subscribed to real-time updates!'
                    });
                } else {
                    $dispatch('toast', {
                        type: 'error',
                        title: 'Subscription Failed',
                        message: 'We couldn\'t register your device. Please try again or check browser settings.'
                    });
                }
            }
        } catch (e) {
            console.error('OneSignal Toggle Error:', e);
            $dispatch('toast', {
                type: 'error',
                title: 'Error',
                message: 'Something went wrong with notification settings.'
            });
        } finally {
            this.loading = false;
        }
    }
}"
    class="flex items-center justify-between p-4 bg-white dark:bg-zinc-800/50 rounded-2xl border border-zinc-100 dark:border-zinc-700/50 transition-all hover:shadow-md">
    <div class="flex items-center gap-3">
        <div
            class="size-10 rounded-full bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center text-purple-600 dark:text-purple-400">
            <template x-if="subscribed">
                <flux:icon name="bell-alert" class="size-5" />
            </template>
            <template x-if="!subscribed">
                <flux:icon name="bell-slash" class="size-5" />
            </template>
        </div>
        <div>
            <div class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Push Notifications') }}</div>
            <div class="text-xs text-zinc-500 font-medium"
                x-text="subscribed ? 'Stay updated in real-time' : 'Click to enable updates'"></div>
        </div>
    </div>

    <button @click="toggleNotifications()" :disabled="loading"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-purple-600 focus:ring-offset-2"
        :class="subscribed ? 'bg-purple-600' : 'bg-zinc-200 dark:bg-zinc-700'">
        <span class="sr-only">Toggle notifications</span>
        <span
            class="pointer-events-none inline-block size-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="subscribed ? 'translate-x-5' : 'translate-x-0'"></span>
        <div x-show="loading" class="absolute inset-0 flex items-center justify-center">
            <div class="size-3 border-2 border-zinc-400 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </button>
</div>