<?php

use Livewire\Volt\Component;

new class extends Component {
    public string $context = 'page'; // 'lila' | 'finder' | 'page'
}; ?>

<div
    x-data="{
        syncing: false,
        lastSyncAt: 0,
        throttleMs: 1000,
        ctx: '{{ $context }}',
        _style: 'color:#6a11cb;font-weight:800',
        _log(...args) { console.log(`%c[Allsers][LocationSync][${this.ctx}]`, this._style, ...args); },
        _info(...args) { console.info(`%c[Allsers][LocationSync][${this.ctx}]`, this._style, ...args); },
        _warn(...args) { console.warn(`[Allsers][LocationSync][${this.ctx}]`, ...args); },
        _error(...args) { console.error(`[Allsers][LocationSync][${this.ctx}]`, ...args); },
        _group(label, fn) {
            try { console.group(`%c[Allsers][LocationSync][${this.ctx}] ${label}`, this._style); fn(); } finally { console.groupEnd(); }
        },
        initSync() {
            this._group('initSync — component mounted', () => {
                this._log('href', location.href);
                this._log('navigator.geolocation exists?', typeof navigator !== 'undefined' && !!navigator.geolocation);
                this._log('Permissions API exists?', !!(navigator.permissions && navigator.permissions.query));
                this._log('Flux exists?', !!(window.Flux && typeof Flux.toast === 'function'), window.Flux);
                this._log('throttleMs', this.throttleMs, '(1s debounce only — every entry will sync)');
                this._log('ts', new Date().toISOString());
            });

            if (typeof navigator === 'undefined' || !navigator.geolocation) {
                this._warn('ABORT: navigator.geolocation not available — no sync possible on this browser/device');
                return;
            }

            const doSync = (reason) => {
                this._group(`doSync triggered — reason: ${reason}`, () => {
                    this._log('href', location.href);
                    this._log('syncing flag', this.syncing);
                    this._log('lastSyncAt', this.lastSyncAt, this.lastSyncAt ? `(${((Date.now()-this.lastSyncAt)/1000).toFixed(1)}s ago)` : '(never)');
                    this._log('now', Date.now());
                });
                this.syncLocation(reason);
            };

            // Initial sync — small delay so Flux, Livewire & toast are fully ready
            setTimeout(() => doSync('mount+delay:700ms'), 700);

            // Every SPA navigation to this page (e.g. /lila ↔ /finder)
            document.addEventListener('livewire:navigated', () => doSync('livewire:navigated'));

            // Banner save — just log, do NOT block next page entry
            window.addEventListener('location-saved', (e) => {
                this._log('event: location-saved received (banner/manual)', e.detail);
            });

            // Observe permission changes live
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' }).then(status => {
                    this._log('PermissionsAPI initial state →', status.state);
                    this._log('granted = silent sync OK | prompt = native dialog may require gesture → silent call can fail code 1 | denied = blocked');
                    status.onchange = () => this._log('PermissionsAPI state changed →', status.state);
                }).catch(e => this._warn('PermissionsAPI query failed', e));
            }
        },
        formatShortAddress(displayName) {
            if (!displayName) return '';
            const parts = displayName.split(',').map(s => s.trim()).filter(Boolean);
            return parts.slice(0, 3).join(', ');
        },
        dispatchToast(readableAddress, source) {
            const shortAddr = this.formatShortAddress(readableAddress) || (readableAddress || '').trim() || 'Your location was updated.';
            const heading = 'Location sync.';
            const text = shortAddr;
            this._group(`dispatchToast [source: ${source}]`, () => {
                this._log('heading', heading);
                this._log('text', text);
                this._log('readableAddress', readableAddress);
                this._log('shortAddr', shortAddr);
                this._log('window.Flux exists?', !!(window.Flux));
                this._log('Flux.toast is function?', !!(window.Flux && typeof Flux.toast === 'function'));
            });

            // Try Flux first
            let fluxOk = false;
            if (window.Flux && typeof Flux.toast === 'function') {
                try {
                    Flux.toast({ variant: 'success', heading, text });
                    fluxOk = true;
                    this._log('✓ Flux.toast dispatched', { heading, text });
                } catch (e) {
                    this._error('✗ Flux.toast threw', e);
                }
            } else {
                this._warn('Flux.toast not available at dispatch time — will fallback to custom toast event', { Flux: window.Flux });
            }

            // Always dispatch custom toast event too (guaranteed via x-ui/toast)
            try {
                const detail = { type: 'success', title: heading, message: text, timeout: 6500 };
                window.dispatchEvent(new CustomEvent('toast', { detail }));
                this._log('✓ Custom toast event dispatched', detail);
            } catch (e) {
                this._error('✗ Failed to dispatch custom toast event', e);
            }

            // Broadcast for page UIs (Lila header, Finder base location)
            try {
                window.dispatchEvent(new CustomEvent('location-synced', {
                    detail: { address: readableAddress, short_address: shortAddr, source, ctx: this.ctx }
                }));
                this._log('✓ location-synced broadcast dispatched');
            } catch (e) { this._warn('location-synced broadcast failed', e); }

            // Retry Flux once shortly after (covers race where Flux loads after this component)
            if (!fluxOk) {
                this._log('Scheduling Flux retry in 500ms…');
                setTimeout(() => {
                    if (window.Flux && typeof Flux.toast === 'function') {
                        try { Flux.toast({ variant: 'success', heading, text }); this._log('✓ Retry Flux.toast dispatched'); } catch (e) { this._warn('Retry Flux.toast threw', e); }
                    } else {
                        this._warn('Retry: Flux still not available', { Flux: window.Flux });
                    }
                }, 500);
            }
        },
        async checkPermission() {
            if (!navigator.permissions || !navigator.permissions.query) {
                this._log('checkPermission: PermissionsAPI not supported → null (will try geolocation anyway)');
                return null;
            }
            try {
                const s = await navigator.permissions.query({ name: 'geolocation' });
                this._log('checkPermission →', s.state);
                return s.state; // 'granted' | 'prompt' | 'denied'
            } catch (e) {
                this._warn('checkPermission query failed', e);
                return null;
            }
        },
        async syncLocation(reason) {
            const now = Date.now();
            this._group(`syncLocation entry — reason: ${reason}`, () => {
                this._log('now', now, new Date(now).toISOString());
                this._log('lastSyncAt', this.lastSyncAt, this.lastSyncAt ? `${((now-this.lastSyncAt)/1000).toFixed(1)}s ago` : 'never');
                this._log('throttleMs', this.throttleMs, 'will skip if elapsed < throttleMs');
                this._log('syncing flag', this.syncing);
            });

            if (this.syncing) {
                this._warn('SKIP: already syncing — wait for current run to finish');
                return;
            }
            if (now - this.lastSyncAt < this.throttleMs) {
                const elapsed = now - this.lastSyncAt;
                this._warn(`SKIP: throttled — only ${elapsed}ms since last sync, need ${this.throttleMs}ms.`, { elapsed, throttleMs: this.throttleMs });
                this._info('Tip: throttle is intentional to avoid spamming toasts on rapid nav. It will auto-sync again after throttle window.');
                return;
            }

            const perm = await this.checkPermission();
            this._log('Permission state before getCurrentPosition', { perm, reason });

            if (perm === 'denied') {
                this._warn('ABORT: permission = denied — browser blocks geolocation. Silent sync not possible.');
                console.info(`%c[Allsers][LocationSync][${this.ctx}] ℹ Permission DENIED — user must enable via the banner (Enable location) or browser site settings (lock icon). No auto prompt will appear.`, 'color:#b45309;font-weight:700');
                const detail = { type: 'warning', title: 'TURN ON YOUR LOCATION', message: 'Location is blocked. Enable it to find trusted pros near you.', timeout: 6500 };
                if (window.Flux && typeof Flux.toast === 'function') {
                    try { Flux.toast({ variant: 'warning', heading: detail.title, text: detail.message }); } catch (_) {}
                    try { window.dispatchEvent(new CustomEvent('toast', { detail })); } catch (_) {}
                } else {
                    window.dispatchEvent(new CustomEvent('toast', { detail }));
                }
                this._log('Dispatched TURN ON YOUR LOCATION toast (blocked)', detail);
                return;
            }

            if (perm === 'prompt') {
                this._info('Permission = prompt — will attempt getCurrentPosition; if browser blocks without gesture, code 1 toast will follow. No immediate nudge to avoid double toast.');
            } else if (perm === 'granted') {
                this._log('Permission = granted — silent sync should succeed without prompt');
            } else {
                this._log('Permission = unknown/null — attempting getCurrentPosition anyway (browser will decide)');
            }

            this.syncing = true;
            this._log('→ Calling navigator.geolocation.getCurrentPosition …', { enableHighAccuracy: true, timeout: 12000, maximumAge: 0, reason });

            try {
                const pos = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(
                        (p) => { console.log(`%c[Allsers][LocationSync][${this.ctx}] ✓ getCurrentPosition SUCCESS`, 'color:#16a34a;font-weight:800', { lat: p.coords.latitude, lng: p.coords.longitude, accuracy_m: p.coords.accuracy, timestamp: p.timestamp, coords: p.coords }); resolve(p); },
                        (e) => { console.warn(`[Allsers][LocationSync][${this.ctx}] ✗ getCurrentPosition ERROR`, { code: e.code, message: e.message, error: e }); reject(e); },
                        { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
                    );
                });

                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const acc = pos.coords.accuracy;
                this._log('Position acquired', { lat, lng, accuracy_m: acc });

                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                this._log('→ POST /location', { lat, lng, accuracy_m: acc, csrf_present: !!csrf, reason });

                const res = await fetch('{{ route('location.update') }}', {
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

                this._log('POST /location response', { ok: res.ok, status: res.status, statusText: res.statusText });

                if (!res.ok) {
                    let body = {};
                    try { body = await res.json(); } catch (_) { try { body = { text: await res.text() }; } catch (_) {} }
                    this._error('POST /location failed', { status: res.status, body });
                    throw new Error(body.message || body.text || `Location save failed (${res.status})`);
                }

                const json = await res.json();
                this._log('POST /location JSON', json);
                const readable = (json.short_address || json.address || '').trim();
                this.lastSyncAt = now;

                if (readable) {
                    this._log('✓ Got readable address from server', { readable, short_address: json.short_address, full: json.address });
                    this.dispatchToast(readable, `server:${reason}`);
                } else {
                    this._warn('Server returned no address — falling back to OSM reverse geocode', { json, lat, lng });
                    try {
                        const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        this._log('OSM reverse fetch', { ok: r.ok, status: r.status });
                        if (r.ok) {
                            const d = await r.json();
                            this._log('OSM reverse JSON', d);
                            const dn = (d.display_name || d.name || '').trim();
                            if (dn) {
                                this.dispatchToast(dn, `osm-fallback:${reason}`);
                            } else {
                                this._warn('OSM returned empty display_name', d);
                                this.dispatchToast(`${lat.toFixed(4)}, ${lng.toFixed(4)}`, `coords-fallback:${reason}`);
                            }
                        } else {
                            this.dispatchToast(`${lat.toFixed(4)}, ${lng.toFixed(4)}`, `coords-fallback:${reason}`);
                        }
                    } catch (e) {
                        this._error('OSM fetch failed', e);
                        this.dispatchToast(`${lat.toFixed(4)}, ${lng.toFixed(4)}`, `coords-fallback:${reason}`);
                    }
                }
            } catch (e) {
                const code = e && e.code;
                const msg = e && (e.message || String(e));
                this._group('✗ syncLocation FAILED', () => {
                    this._error('code', code);
                    this._error('message', msg);
                    this._error('error object', e);
                    this._error('reason', reason);
                    this._error('perm was', perm);
                });
                if (code === 1) {
                    console.info(`%c[Allsers][LocationSync][${this.ctx}] ✗ PERMISSION_DENIED (code 1) — user dismissed/denied the prompt, or browser blocked silent prompt without user gesture.`, 'color:#dc2626;font-weight:700');
                    this._warn('Hint: On Chrome/Safari, silent getCurrentPosition with perm=prompt often requires a user gesture. Manual Enable location button will work; silent entry re-sync will work after user grants once (→ granted).');
                    const d2 = { type: 'warning', title: 'TURN ON YOUR LOCATION', message: 'Please allow location to find pros nearby.', timeout: 6500 };
                    if (window.Flux && typeof Flux.toast === 'function') try { Flux.toast({ variant: 'warning', heading: d2.title, text: d2.message }); } catch (_) {}
                    try { window.dispatchEvent(new CustomEvent('toast', { detail: d2 })); } catch (_) {}
                    this._log('Dispatched TURN ON YOUR LOCATION toast (code 1)', d2);
                } else if (code === 2) {
                    this._warn('POSITION_UNAVAILABLE (code 2) — GPS/network unavailable. Try again or move to better signal.');
                } else if (code === 3) {
                    this._warn('TIMEOUT (code 3) — location request timed out after 12s. Will retry on next entry.');
                } else if (msg && String(msg).includes('Location save failed')) {
                    this._error('Server save failed — see POST logs above');
                } else {
                    this._warn('Unknown geolocation error — full error logged above');
                }
            } finally {
                this.syncing = false;
                this._log('syncLocation finished', { ctx: this.ctx, reason, syncing: this.syncing, lastSyncAt: this.lastSyncAt });
            }
        }
    }"
    x-init="initSync()"
    class="hidden"
    aria-hidden="true"
></div>
