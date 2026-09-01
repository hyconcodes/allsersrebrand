<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $isSubscribed = false;

    public function mount()
    {
        $user = Auth::user();
        $this->isSubscribed = $user ? $user->pushSubscriptions()->exists() : false;
    }
}; ?>

<div x-data="{
    subscribed: @entangle('isSubscribed'),
    loading: false,
    vapidKey: '',
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    },
    async getRegistration() {
        const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('SW timeout')), 3000));
        return Promise.race([navigator.serviceWorker.ready, timeout]);
    },
    async checkInitialState() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            this.subscribed = Notification.permission === 'granted';
            return;
        }
        try {
            const reg = await this.getRegistration().catch(() => null);
            if (!reg) { this.subscribed = false; return; }
            const sub = await reg.pushManager.getSubscription();
            this.subscribed = !!sub;
        } catch (e) {
            console.warn('Check push state failed:', e);
        }
    },
    async toggleNotifications() {
        if (this.loading) return;
        this.loading = true;
        try {
            const pushSupported = ('serviceWorker' in navigator) && ('PushManager' in window);
            if (!pushSupported) {
                if (Notification.permission === 'granted') {
                    this.subscribed = true;
                    $dispatch('toast', { type: 'info', title: 'Notifications Active', message: 'In-tab notifications enabled (Push not supported on this browser).' });
                } else {
                    const perm = await Notification.requestPermission();
                    this.subscribed = perm === 'granted';
                    $dispatch('toast', {
                        type: perm === 'granted' ? 'success' : 'error',
                        title: perm === 'granted' ? 'Notifications Enabled' : 'Permission Denied',
                        message: perm === 'granted' ? 'You will receive in-tab notifications while browsing.' : 'Please enable notifications in browser settings.'
                    });
                }
                return;
            }

            this.vapidKey = (document.querySelector('meta[name=vapid-public-key]')?.content || '').trim() || this.vapidKey;
            const registration = await this.getRegistration();
            let subscription = await registration.pushManager.getSubscription();
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

            if (this.subscribed && subscription) {
                const resDel = await fetch('/push-subscriptions', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });
                if (!resDel.ok) console.warn('Delete subscription failed', resDel.status);
                await subscription.unsubscribe();
                localStorage.removeItem('webpush_vapid_key');
                this.subscribed = false;
                $dispatch('toast', { type: 'info', title: 'Notifications Paused', message: 'You will no longer receive push notifications.' });
            } else {
                let permission = Notification.permission;
                if (permission !== 'granted') permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    $dispatch('toast', { type: 'error', title: 'Permission Denied', message: 'Please allow notifications in your browser.' });
                    return;
                }
                if (!this.vapidKey) {
                    this.vapidKey = (document.querySelector('meta[name=vapid-public-key]')?.content || '').trim();
                    if (!this.vapidKey) {
                        $dispatch('toast', { type: 'error', title: 'Error', message: 'VAPID key not configured.' });
                        return;
                    }
                }
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.vapidKey),
                });
                const payload = subscription.toJSON();
                const res = await fetch('/push-subscriptions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                if (!res.ok) throw new Error('Failed to save subscription');
                localStorage.setItem('webpush_vapid_key', this.vapidKey);
                this.subscribed = true;
                $dispatch('toast', { type: 'success', title: 'Notifications Enabled', message: 'You are now subscribed to real-time updates!' });
                try {
                    const csrf2 = document.querySelector('meta[name=csrf-token]')?.content || '';
                    await fetch('/push-subscriptions/test-webpush', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf2, 'Accept': 'application/json' }, credentials: 'same-origin' });
                } catch {}
                window.dispatchEvent(new CustomEvent('push-subscription-changed', { detail: { subscribed: true } }));
            }
        } catch (e) {
            console.error('Toggle Web Push Error:', e.name, e.message, e);
            let msg = 'Something went wrong with notification settings.';
            if (e.name === 'AbortError') msg = 'Push service error — try: 1) Clear Application > Storage > Clear site data and reload, 2) Check not in incognito/Brave shields, 3) Ensure FCM reachable (VPN/firewall), 4) Retry.';
            if (e.name === 'NotAllowedError') msg = 'Permission denied — enable notifications in browser settings.';
            $dispatch('toast', { type: 'error', title: 'Error: ' + e.name, message: msg });
        } finally {
            this.loading = false;
        }
    }
}"
    x-init="checkInitialState(); window.addEventListener('push-subscription-changed', e => { subscribed = e.detail.subscribed; })"
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

{{-- OneSignal version DISABLED
<div x-data="{
    subscribed: @entangle('isSubscribed'),
    loading: false,
    async toggleNotifications() {
        const OneSignal = await new Promise(r => {
            const OS = window.OneSignal; if (OS) r(OS); else { window.OneSignalDeferred = window.OneSignalDeferred || []; window.OneSignalDeferred.push(OS => r(OS)); }
        });
        if (this.subscribed) { await OneSignal.User.PushSubscription.optOut(); this.subscribed=false; }
        else {
            if (OneSignal.Notifications.permission !== 'granted') await OneSignal.Notifications.requestPermission();
            await OneSignal.User.PushSubscription.optIn();
        }
    }
}"></div>
--}}
