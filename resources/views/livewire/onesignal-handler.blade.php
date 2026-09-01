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
        }
    }
}; ?>

<div x-data="{
    lastNotificationId: null,
    _pollInterval: null,
    init() {
        console.log('[WebPush] Handler initialized');
        if (typeof Notification === 'undefined') {
            console.warn('[WebPush] Notification API not available — check Permissions-Policy header');
            return;
        }
        this.initWebPush();
    },
    async initWebPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('[WebPush] Push not supported');
            if (Notification.permission === 'granted') this.startFallbackPolling();
            return;
        }
        try {
            const registration = await navigator.serviceWorker.ready;
            const vapidKey = (document.querySelector('meta[name=vapid-public-key]')?.content || '').trim();
            let subscription = await registration.pushManager.getSubscription();
            const storedVapid = localStorage.getItem('webpush_vapid_key') || '';
            const vapidChanged = vapidKey && storedVapid && storedVapid !== vapidKey;
            if (subscription && vapidChanged) {
                console.warn('[WebPush] VAPID changed — resubscribing...', storedVapid.substring(0,8), '->', vapidKey.substring(0,8));
                try { await subscription.unsubscribe(); } catch {}
                try {
                    const csrfDel = document.querySelector('meta[name=csrf-token]')?.content || '';
                    await fetch('/push-subscriptions', { method: 'DELETE', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfDel, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ endpoint: subscription.endpoint }) });
                } catch {}
                subscription = null;
            }
            if (subscription) {
                const ok = await this.syncSubscription(subscription);
                if (!ok && vapidKey) {
                    console.warn('[WebPush] Sync failed — cleaning up stale subscription');
                    try { await subscription.unsubscribe(); } catch {}
                    subscription = null;
                } else if (vapidKey) {
                    localStorage.setItem('webpush_vapid_key', vapidKey);
                }
            }
            if (Notification.permission === 'granted' && !subscription && vapidKey) {
                console.log('[WebPush] Permission granted but no subscription — auto-subscribing...');
                await this.autoSubscribe(registration, vapidKey);
            }
            if (Notification.permission === 'granted') this.startFallbackPolling();
        } catch (e) {
            console.error('[WebPush] Init error:', e);
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
    async autoSubscribe(registration, vapidKey, retry = 0) {
        try {
            if (!vapidKey) return;
            const newSub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidKey),
            });
            const ok = await this.syncSubscription(newSub);
            if (!ok) throw new Error('Sync failed after subscribe');
            localStorage.setItem('webpush_vapid_key', vapidKey);
            console.log('[WebPush] Auto-subscribed');
            window.dispatchEvent(new CustomEvent('push-subscription-changed', { detail: { subscribed: true } }));
            try {
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                await fetch('/push-subscriptions/test-webpush', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            } catch {}
        } catch (e) {
            console.error('[WebPush] Auto-subscribe failed:', e.name, e.message);
            if (e.name === 'AbortError' && retry === 0) {
                try {
                    const oldSub = await registration.pushManager.getSubscription();
                    if (oldSub) await oldSub.unsubscribe();
                } catch {}
                await new Promise(r => setTimeout(r, 1000));
                return this.autoSubscribe(registration, vapidKey, 1);
            }
        }
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
            const res = await fetch('/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const txt = await res.text().catch(() => '');
                console.error('[WebPush] Sync failed:', res.status, txt);
                return false;
            }
            console.log('[WebPush] Subscription synced');
            return true;
        } catch (e) {
            console.error('[WebPush] Sync failed:', e);
            return false;
        }
    },
    startFallbackPolling() {
        if (this._pollInterval) return;
        this._pollInterval = setInterval(async () => {
            if (typeof Notification === 'undefined' || Notification.permission !== 'granted' || document.visibilityState !== 'visible') return;
            try {
                const res = await fetch('/push-subscriptions/latest', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
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
                try {
                    const n = new Notification(title, { body, icon: '/apple-touch-icon.png', badge: '/favicon.ico', data: { url } });
                    n.onclick = () => { window.focus(); if (url) window.location.href = url; n.close(); };
                } catch (e) { console.warn('[WebPush] In-tab notification failed:', e); }
            } catch (e) {}
        }, 15000);
        fetch('/push-subscriptions/latest', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r=>r.json()).then(d=>{ if(d && d.id) this.lastNotificationId=d.id; }).catch(()=>{});
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
