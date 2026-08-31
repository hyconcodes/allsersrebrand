<?php

use Livewire\Volt\Component;

new class extends Component {}; ?>

<div x-data="{
    show: false,
    isGranted: false,
    isSubscribed: false,
    isChecking: true,
    loading: false,
    vapidKey: document.querySelector('meta[name=vapid-public-key]')?.content || '',
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    },
    async checkState() {
        try {
            this.isGranted = window.Notification ? Notification.permission === 'granted' : false;
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.isSubscribed = this.isGranted;
                return;
            }
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            this.isSubscribed = !!sub;
            this.isGranted = Notification.permission === 'granted';
        } catch (e) {
            this.isGranted = window.Notification ? Notification.permission === 'granted' : false;
        } finally {
            this.isChecking = false;
        }
    },
    shouldShow() {
        if (this.isGranted && this.isSubscribed) return false;
        if (window.Notification && Notification.permission === 'denied') return false;
        return !this.isGranted || !this.isSubscribed;
    },
    async initPrompt() {
        await this.checkState();
        if (!this.shouldShow()) return;
        setTimeout(() => {
            this.checkState().then(() => {
                if (this.shouldShow()) this.show = true;
            });
        }, 5000);
        window.addEventListener('push-subscription-changed', e => {
            this.isSubscribed = e.detail.subscribed;
            if (e.detail.subscribed) this.isGranted = true;
            if (this.isGranted && this.isSubscribed) this.show = false;
        });
    },
    dismiss() {
        this.show = false;
    },
    async subscribe() {
        if (this.loading) return;
        this.loading = true;
        try {
            const pushSupported = ('serviceWorker' in navigator) && ('PushManager' in window);
            if (!pushSupported) {
                const perm = await Notification.requestPermission();
                this.isGranted = perm === 'granted';
                this.isSubscribed = this.isGranted;
                if (this.isGranted) {
                    if (window.Flux) Flux.toast({ variant: 'success', heading: 'Success', text: 'In-tab notifications enabled!' });
                    this.show = false;
                    window.dispatchEvent(new CustomEvent('push-subscription-changed', { detail: { subscribed: true } }));
                }
                return;
            }
            let perm = Notification.permission;
            if (perm !== 'granted') perm = await Notification.requestPermission();
            this.isGranted = perm === 'granted';
            if (perm !== 'granted') {
                if (window.Flux) Flux.toast({ variant: 'error', heading: 'Permission denied', text: 'Please allow notifications in browser settings.' });
                this.show = false;
                return;
            }
            const reg = await navigator.serviceWorker.ready;
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                if (!this.vapidKey) {
                    if (window.Flux) Flux.toast({ variant: 'error', heading: 'Error', text: 'VAPID key not configured.' });
                    return;
                }
                sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.urlBase64ToUint8Array(this.vapidKey) });
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                const res = await fetch('/push-subscriptions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(sub.toJSON()),
                });
                if (!res.ok) throw new Error('Failed to save subscription');
                localStorage.setItem('webpush_vapid_key', this.vapidKey);
            }
            this.isSubscribed = true;
            this.show = false;
            window.dispatchEvent(new CustomEvent('push-subscription-changed', { detail: { subscribed: true } }));
            if (window.Flux) Flux.toast({ variant: 'success', heading: 'Success', text: 'You will now receive real-time notifications!' });
            try {
                const csrf2 = document.querySelector('meta[name=csrf-token]')?.content || '';
                await fetch('/push-subscriptions/test-webpush', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf2, 'Accept': 'application/json' } });
            } catch (e) { console.warn('Test webpush trigger failed', e); }
        } catch (e) {
            console.error('Prompt subscribe error:', e.name, e.message, e);
            if (e.name === 'AbortError' && window.Flux) Flux.toast({ variant: 'error', heading: 'Push service error', text: 'Clear site data and reload, or try Chrome (not Brave incognito).' });
            else if (window.Flux) Flux.toast({ variant: 'error', heading: 'Error', text: e.message || 'Failed to subscribe.' });
        } finally {
            this.loading = false;
        }
    }
}"
x-init="initPrompt()"
x-show="show && !isChecking"
x-transition:enter="transition ease-out duration-500"
x-transition:enter-start="opacity-0 -translate-y-8"
x-transition:enter-end="opacity-100 translate-y-0"
x-transition:leave="transition ease-in duration-300"
x-transition:leave-start="opacity-100 translate-y-0"
x-transition:leave-end="opacity-0 -translate-y-8"
x-cloak
class="fixed top-4 left-1/2 -translate-x-1/2 z-[600] w-[calc(100%-2rem)] max-w-[420px]">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden relative">
        <button @click="dismiss()" class="absolute top-2 right-2 size-6 flex items-center justify-center rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors" aria-label="Dismiss">
            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="p-5 pr-8 flex gap-4">
            <div class="size-10 rounded-full bg-purple-600 flex items-center justify-center shrink-0">
                <svg class="size-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100 leading-tight">Stay updated on Allsers</h4>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">Subscribe to receive real-time notifications for your messages and inquiries!</p>
                <div class="flex gap-2 mt-3">
                    <button @click="subscribe()" :disabled="loading" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-bold rounded-full transition-colors flex items-center gap-1.5">
                        <span x-show="!loading">Subscribe</span>
                        <span x-show="loading" class="size-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span x-show="loading">Subscribing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
