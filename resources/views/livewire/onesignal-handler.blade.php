<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

new class extends Component {
    // OneSignal disabled — kept for backward compatibility, no longer used.
    // New push handling is via /push-subscriptions + native Web Push (see JS below).
    public function updateId($id)
    {
        if (Auth::check()) {
            $user = Auth::user();
            Log::info('OneSignal Update ID Attempt (DISABLED)', [
                'user_id' => $user->id,
                'new_player_id' => $id,
            ]);
            // Disabled: OneSignal no longer used. Keeping column for legacy.
            // if ($user->onesignal_player_id !== $id) {
            //     $user->forceFill(['onesignal_player_id' => $id])->save();
            // }
        }
    }
}; ?>

<div x-data="{
    init() {
        console.log('Web Push Handler Initialized');
        this.initWebPush();
    },
    lastNotificationId: null,
    async initWebPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push not supported — fallback to in-tab Notification API only');
            if ('Notification' in window && Notification.permission === 'granted') {
                this.startFallbackPolling();
            }
            if ('Notification' in window && Notification.permission === 'default') {
                console.log('Notification permission not yet requested (fallback mode)');
            }
            window.addEventListener('allsers:notify', (e) => this.showInTabNotification(e.detail));
            return;
        }
        try {
            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await this.syncSubscription(subscription);
            }
            if (Notification.permission === 'granted' && !subscription) {
                console.log('Permission granted but no subscription — will subscribe on toggle');
            }
        } catch (e) {
            console.error('Web Push init error:', e);
        }
    },
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    },
    async syncSubscription(subscription) {
        try {
            const rawKey = subscription.getKey ? subscription.getKey('p256dh') : null;
            const authKey = subscription.getKey ? subscription.getKey('auth') : null;
            const p256dh = rawKey ? btoa(String.fromCharCode.apply(null, new Uint8Array(rawKey))) : null;
            const auth = authKey ? btoa(String.fromCharCode.apply(null, new Uint8Array(authKey))) : null;
            const payload = subscription.toJSON ? subscription.toJSON() : {
                endpoint: subscription.endpoint,
                keys: { p256dh, auth }
            };
            if (!payload.keys) payload.keys = { p256dh, auth };
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            await fetch('/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            console.log('Push subscription synced');
        } catch (e) {
            console.error('Sync subscription failed:', e);
        }
    },
    showInTabNotification(detail) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        if (document.visibilityState === 'visible' && detail && detail.title) {
            try {
                const n = new Notification(detail.title, { body: detail.body || '', icon: detail.icon || '/apple-touch-icon.png', badge: '/favicon.ico', data: { url: detail.url || '/notifications' } });
                n.onclick = () => { window.focus(); if (detail.url) window.location.href = detail.url; n.close(); };
            } catch (e) { console.warn('In-tab notification failed:', e); }
        }
    },
    startFallbackPolling() {
        setInterval(async () => {
            if (Notification.permission !== 'granted' || document.visibilityState !== 'visible') return;
            try {
                const res = await fetch('/push-subscriptions/latest', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                if (!data || !data.id || data.id === this.lastNotificationId) return;
                if (!this.lastNotificationId) { this.lastNotificationId = data.id; return; }
                this.lastNotificationId = data.id;
                const d = data.data || {};
                const titleMap = { message: 'New Message', like: 'New Like!', comment: 'New Comment!', reply: 'New Reply!', user_tagged: 'You were Tagged!', inquiry: 'New Service Inquiry!', challenge_invitation: 'Challenge Invitation!', challenge_winner: 'Congratulations!' };
                const title = titleMap[d.type] || 'Allsers';
                const body = d.message || d.body || 'You have a new notification';
                const url = d.url || data.data?.url || '/notifications';
                this.showInTabNotification({ title, body, url });
            } catch (e) {}
        }, 15000);
        fetch('/push-subscriptions/latest', { headers: { 'Accept': 'application/json' } }).then(r=>r.json()).then(d=>{ if(d && d.id) this.lastNotificationId=d.id; }).catch(()=>{});
    }
}" style="display:none"></div>

{{-- OneSignal handler DISABLED
<div x-data="{
    init() {
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(async (OneSignal) => {
            const checkAndSaveId = async () => {
                const subscriptionId = await OneSignal.User.PushSubscription.id;
                if (subscriptionId) await $wire.updateId(subscriptionId);
            };
            await checkAndSaveId();
            OneSignal.User.PushSubscription.addEventListener('change', async (event) => {
                const newId = event.current?.id; if (newId) await $wire.updateId(newId);
            });
            OneSignal.Notifications.addEventListener('permissionChange', async (permission) => {
                if (permission === 'granted') setTimeout(checkAndSaveId, 2000);
            });
        });
    }
}"></div>
--}}
