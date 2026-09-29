<?php

use Livewire\Volt\Component;

new class extends Component {
    public bool $hasLocation = false;

    public function mount(): void
    {
        $this->hasLocation = ! is_null(auth()->user()->latitude) && ! is_null(auth()->user()->longitude);
    }
}; ?>

<div
    x-data="{
        show: false,
        loading: false,
        checking: true,
        address: '',
        errorMsg: '',
        hasLocation: @js($hasLocation),
        dismissedKey: 'allsers_location_dismissed_at',
        wasDismissedRecently() {
            const raw = localStorage.getItem(this.dismissedKey);
            if (!raw) return false;
            const ts = parseInt(raw, 10);
            if (isNaN(ts)) return false;
            const threeDays = 3 * 24 * 60 * 60 * 1000;
            return (Date.now() - ts) < threeDays;
        },
        async initBanner() {
            const tag = '[Allsers][LocationBanner]';
            console.log(`%c${tag}`, 'color:#6a11cb;font-weight:800', 'initBanner', { hasLocation: this.hasLocation, href: location.href });
            this.checking = true;
            try {
                if (this.hasLocation) { console.log(`${tag} hasLocation=true → banner not shown`); this.checking = false; return; }
                if (this.wasDismissedRecently()) { console.log(`${tag} dismissed recently → banner suppressed for 3 days`); this.checking = false; return; }
                if (typeof navigator === 'undefined' || !navigator.geolocation) { console.warn(`${tag} no geolocation API`); this.checking = false; return; }
                if (navigator.permissions && navigator.permissions.query) {
                    try {
                        const status = await navigator.permissions.query({ name: 'geolocation' });
                        console.log(`${tag} PermissionsAPI`, status.state);
                    } catch (e) { console.warn(`${tag} PermissionsAPI query failed`, e); }
                }
                // Listen for global sync — if another component saves location, hide banner instantly
                window.addEventListener('location-synced', (e) => {
                    const addr = e.detail?.short_address || e.detail?.address || '';
                    if (addr) {
                        console.log(`${tag} location-synced received → hiding banner`, e.detail);
                        this.hasLocation = true;
                        this.show = false;
                        this.checking = false;
                    }
                });
                window.addEventListener('location-saved', (e) => {
                    const addr = e.detail?.short_address || e.detail?.address || '';
                    if (addr) {
                        console.log(`${tag} location-saved received → hiding banner`, e.detail);
                        this.hasLocation = true;
                        this.show = false;
                        this.checking = false;
                    }
                });
                setTimeout(() => {
                    if (!this.hasLocation && !this.wasDismissedRecently()) {
                        console.log(`${tag} showing banner`);
                        this.show = true;
                        this.checking = false;
                    } else {
                        console.log(`${tag} banner suppressed after delay`);
                        this.checking = false;
                    }
                }, 2200);
            } catch (e) {
                console.warn(`${tag} initBanner error`, e);
                this.checking = false;
            }
        },
        dismiss() {
            this.show = false;
            localStorage.setItem(this.dismissedKey, String(Date.now()));
        },
        async enableLocation() {
            if (this.loading) return;
            this.loading = true;
            this.errorMsg = '';
            try {
                if (!navigator.geolocation) {
                    this.errorMsg = 'Location is not supported on this device.';
                    if (window.Flux) Flux.toast({ variant: 'error', heading: 'Not supported', text: this.errorMsg });
                    return;
                }
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 12000,
                        maximumAge: 0,
                    });
                });
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        const addr = data.display_name || data.name || '';
                        if (addr) { this.address = addr.split(',').slice(0,3).join(',').trim(); console.log('%c[Allsers][LocationBanner]', 'color:#6a11cb;font-weight:700', 'OSM pre-address', this.address); }
                    } else console.warn('[Allsers][LocationBanner] OSM pre-fetch failed', res.status);
                } catch (e) { console.warn('[Allsers][LocationBanner] OSM pre-fetch error', e); }
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                const saveRes = await fetch('{{ route('location.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ latitude: lat, longitude: lng }),
                });
                if (!saveRes.ok) {
                    const j = await saveRes.json().catch(() => ({}));
                    throw new Error(j.message || 'Failed to save location.');
                }
                const json = await saveRes.json().catch(() => ({}));
                const serverAddr = (json.short_address || json.address || '').trim();
                const readable = serverAddr || this.address || `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                const shortReadable = readable.split(',').slice(0,3).join(',').trim();
                console.log('%c[Allsers][LocationBanner]', 'color:#16a34a;font-weight:800', '✓ Enable location success', { lat, lng, serverAddr, readable, shortReadable, postJson: json });
                localStorage.removeItem(this.dismissedKey);
                this.hasLocation = true;
                this.show = false;
                const heading = 'Location sync.';
                const text = shortReadable;
                const fluxOk = !!(window.Flux && typeof Flux.toast === 'function');
                console.log('[Allsers][LocationBanner] dispatching toast', { heading, text, fluxOk });
                if (fluxOk) { try { Flux.toast({ variant: 'success', heading, text }); console.log('[Allsers][LocationBanner] Flux.toast OK'); } catch (e) { console.warn('[Allsers][LocationBanner] Flux.toast threw', e); } }
                // Always dispatch custom toast too so ui/toast shows it even if Flux race
                try { window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', title: heading, message: text, timeout: 6500 } })); console.log('[Allsers][LocationBanner] custom toast dispatched'); } catch (e) { console.error('[Allsers][LocationBanner] custom toast failed', e); }
                if (!fluxOk) setTimeout(() => { if (window.Flux && typeof Flux.toast === 'function') try { Flux.toast({ variant: 'success', heading, text }); console.log('[Allsers][LocationBanner] retry Flux.toast OK'); } catch (_) {} }, 500);
                window.dispatchEvent(new CustomEvent('location-saved', { detail: { latitude: lat, longitude: lng, address: readable, short_address: shortReadable } }));
                window.dispatchEvent(new CustomEvent('location-synced', { detail: { latitude: lat, longitude: lng, address: readable, short_address: shortReadable } }));
                console.log('[Allsers][LocationBanner] broadcast location-saved + location-synced');
                setTimeout(() => window.location.reload(), 900);
            } catch (e) {
                console.warn('Location enable failed:', e);
                const code = e && e.code;
                if (code === 1) {
                    this.errorMsg = 'Location permission was denied. You can enable it anytime in your browser settings (lock icon in the address bar).';
                    if (window.Flux) Flux.toast({ variant: 'error', heading: 'Permission denied', text: 'Enable location in your browser settings — tap the lock icon near the address bar.' });
                    this.dismiss();
                } else if (code === 2) {
                    this.errorMsg = 'Could not detect your location. Please try again.';
                    if (window.Flux) Flux.toast({ variant: 'error', heading: 'Location unavailable', text: this.errorMsg });
                } else if (code === 3) {
                    this.errorMsg = 'Location request timed out. Please try again.';
                    if (window.Flux) Flux.toast({ variant: 'error', heading: 'Timed out', text: this.errorMsg });
                } else {
                    this.errorMsg = e.message || 'Could not save your location. Please try again.';
                    if (window.Flux) Flux.toast({ variant: 'error', heading: 'Something went wrong', text: this.errorMsg });
                }
            } finally {
                this.loading = false;
            }
        }
    }"
    x-init="initBanner()"
    x-show="show && !checking"
    x-transition:enter="transition ease-out duration-500"
    x-transition:enter-start="opacity-0 -translate-y-3 scale-[0.98]"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-250"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-3"
    x-cloak
    class="w-full mb-4 sm:mb-6"
>
    <div class="relative overflow-hidden rounded-[20px] sm:rounded-[24px] border border-[var(--color-brand-purple)]/15 bg-white dark:bg-zinc-900 shadow-[0_12px_32px_-16px_rgba(106,17,203,0.32)] sm:shadow-[0_16px_40px_-20px_rgba(106,17,203,0.35)]">
        <!-- Decorative blurs — scaled down on mobile -->
        <div class="pointer-events-none absolute -top-10 -right-10 sm:-top-16 sm:-right-16 size-36 sm:size-56 rounded-full bg-[var(--color-brand-purple)]/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-12 -left-8 size-40 sm:size-64 rounded-full bg-purple-400/10 blur-2xl hidden xs:block"></div>

        <!-- Dismiss — larger hit area on mobile -->
        <button @click="dismiss()" aria-label="Dismiss" class="absolute right-2.5 top-2.5 sm:right-3 sm:top-3 z-10 flex size-8 sm:size-7 items-center justify-center rounded-full bg-zinc-900/5 dark:bg-white/10 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-900/10 dark:hover:bg-white/15 hover:text-zinc-700 dark:hover:text-zinc-200 active:scale-95 transition-all">
            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <!-- Content -->
        <div class="relative p-4 pr-11 sm:p-6 sm:pr-6">
            <!-- Header row: icon + title -->
            <div class="flex items-start gap-3 sm:gap-4">
                <div class="flex size-10 sm:size-11 shrink-0 items-center justify-center rounded-xl sm:rounded-2xl bg-[var(--color-brand-purple)] text-white shadow-md sm:shadow-lg shadow-[var(--color-brand-purple)]/20 mt-0.5 sm:mt-0">
                    <flux:icon name="map-pin" class="size-5 sm:size-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="pr-1 text-[14px] sm:text-[15px] font-extrabold tracking-tight leading-tight sm:leading-none text-zinc-900 dark:text-white">
                        {{ __('Discover pros near you') }}
                    </h3>
                    <p class="mt-1 sm:mt-1.5 text-[12.5px] sm:text-[13px] font-medium leading-[1.5] sm:leading-relaxed text-zinc-600 dark:text-zinc-400 break-words">
                        {{ __('Enable location to see verified artisans around you, get accurate distances and faster matches.') }}
                        <span class="hidden sm:inline">{{ __('Your location stays private — we only use it to show nearby services.') }}</span>
                        <span class="sm:hidden text-zinc-500 dark:text-zinc-500">{{ __('Private & only used for nearby services.') }}</span>
                    </p>

                    <!-- Benefits — tight wrap on mobile -->
                    <div class="mt-3 sm:mt-3.5 flex flex-wrap gap-1.5 sm:gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-2.5 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-[11px] font-bold tracking-wide whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                            <span class="size-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                            {{ __('Nearby pros') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-2.5 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-[11px] font-bold tracking-wide whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                            <span class="size-1.5 rounded-full bg-[var(--color-brand-purple)] shrink-0"></span>
                            {{ __('Accurate distances') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-2.5 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-[11px] font-bold tracking-wide whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                            <span class="size-1.5 rounded-full bg-amber-500 shrink-0"></span>
                            {{ __('Faster hiring') }}
                        </span>
                    </div>

                    <p x-show="errorMsg" x-text="errorMsg" x-cloak class="mt-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 px-3 py-2.5 text-[12px] font-medium leading-relaxed text-amber-800 dark:text-amber-200 break-words"></p>

                    <!-- Actions: stacked full-width on mobile, inline on sm+ -->
                    <div class="mt-4 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2.5 sm:gap-3">
                        <button @click="enableLocation()" :disabled="loading" class="inline-flex w-full sm:w-auto h-[46px] sm:h-10 items-center justify-center gap-2 rounded-xl sm:rounded-xl bg-[var(--color-brand-purple)] px-5 text-[14px] sm:text-sm font-extrabold tracking-tight text-white shadow-lg shadow-[var(--color-brand-purple)]/20 hover:bg-[#5a0eb0] hover:shadow-xl active:scale-[0.98] sm:hover:-translate-y-[1px] sm:active:translate-y-0 disabled:opacity-60 disabled:cursor-not-allowed disabled:active:scale-100 transition-all">
                            <span x-show="!loading" class="flex items-center justify-center gap-2">
                                <flux:icon name="map-pin" class="size-4" />
                                {{ __('Enable location') }}
                            </span>
                            <span x-show="loading" x-cloak class="flex items-center justify-center gap-2">
                                <span class="size-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
                                {{ __('Locating…') }}
                            </span>
                        </button>

                        <!-- Secondary row on mobile: centered, on desktop inline -->
                        <div class="flex items-center justify-center sm:justify-start gap-3 sm:gap-3 w-full sm:w-auto">
                            <button @click="dismiss()" class="py-2 px-2 -mx-2 text-[13px] font-bold text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:underline underline-offset-4 active:text-zinc-900 transition-colors">
                                {{ __('Maybe later') }}
                            </button>
                            <span class="hidden sm:inline-flex items-center gap-1.5 text-[11px] font-medium text-zinc-400 dark:text-zinc-500 whitespace-nowrap">
                                <flux:icon name="lock-closed" class="size-3 shrink-0" />
                                {{ __('Private & secure') }}
                            </span>
                        </div>
                    </div>

                    <!-- Privacy hint — mobile only, below buttons -->
                    <p class="mt-2.5 sm:hidden flex items-center justify-center gap-1.5 text-[11px] font-medium leading-none text-zinc-400 dark:text-zinc-500">
                        <flux:icon name="lock-closed" class="size-3 shrink-0" />
                        {{ __('Private & secure — you can turn it off anytime.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
