<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $username = '';
    public string $profile_picture = '';
    public $photo; // New upload
    public string $gender = '';
    public string $work = '';
    public string $bio = '';
    public string $experience_year = '';
    public string $work_status = '';
    public string $phone_number = '';
    public string $address = '';
    public string $country_code = 'NG';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->username = $user->username ?? '';
        $this->profile_picture = $user->profile_picture ?? '';
        $this->gender = $user->gender ?? '';
        $this->work = $user->work ?? '';
        $this->bio = $user->bio ?? '';
        $this->experience_year = $user->experience_year ? (string) $user->experience_year : '';
        $this->work_status = $user->work_status ?? '';
        $this->phone_number = $user->phone_number ?? '';
        $this->address = $user->address ?? '';
        $this->country_code = $user->country_code ?? 'NG';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'photo' => ['nullable', 'image', 'max:1024'], // 1MB Max
            'gender' => ['nullable', 'string', 'max:50'],
            'work' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'experience_year' => ['nullable', 'integer', 'min:0'],
            'work_status' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        if ($this->photo) {
            $user->profile_picture = $this->photo->store('profilePics', 'public');
        }

        $user->fill([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'gender' => $validated['gender'],
            'work' => $validated['work'],
            'bio' => $validated['bio'],
            'experience_year' => $validated['experience_year'],
            'work_status' => $validated['work_status'],
            'phone_number' => $validated['phone_number'],
            'address' => $validated['address'],
            'country_code' => $validated['country_code'],
        ]);
        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<x-settings.layout :active="'profile'">
    <form wire:submit="updateProfileInformation" class="space-y-8">

        <!-- Profile Picture -->
        <div class="flex items-center gap-5">
            <div class="relative shrink-0">
                @if ($photo)
                    <div
                        class="size-24 rounded-full bg-zinc-200 overflow-hidden ring-2 ring-[var(--color-brand-purple)] ring-offset-2 ring-offset-white dark:ring-offset-zinc-950">
                        <img src="{{ $photo->temporaryUrl() }}" alt="Profile Photo" class="size-full object-cover">
                    </div>
                @elseif ($profile_picture)
                    <div class="size-24 rounded-full bg-zinc-200 overflow-hidden">
                        <img src="{{ route('images.show', ['path' => $profile_picture]) }}" alt="Profile Photo"
                            class="size-full object-cover">
                    </div>
                @else
                    <div
                        class="size-24 rounded-full bg-[var(--color-brand-purple)]/10 flex items-center justify-center text-[var(--color-brand-purple)] text-3xl font-bold">
                        {{ auth()->user()->initials() }}
                    </div>
                @endif

                <label for="photo-upload"
                    class="absolute bottom-0 right-0 p-1.5 bg-white dark:bg-zinc-800 rounded-full shadow-md cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700">
                    <flux:icon name="camera" class="size-4 text-zinc-500" />
                    <input id="photo-upload" type="file" wire:model="photo" class="hidden" accept="image/*">
                </label>
            </div>

            <div class="flex flex-col">
                <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ __('Profile Photo') }}</h3>
                <p class="text-sm text-zinc-500">{{ __('Update your profile picture.') }}</p>
            </div>
        </div>

        <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

        <!-- Account -->
        <div class="space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Account') }}</h2>
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />
            <flux:input wire:model="username" :label="__('Username')" type="text" autocomplete="username" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" readonly
                disabled />
        </div>

        <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

        <!-- Contact -->
        <div class="space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Contact') }}</h2>
            <flux:input wire:model="phone_number" :label="__('Phone Number')" type="text" />
            <flux:input wire:model="address" :label="__('Address')" type="text" />
        </div>

        <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

        <!-- Work -->
        <div class="space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Work') }}</h2>
            <flux:input wire:model="gender" :label="__('Gender')" type="text" />
            <flux:select wire:model="work_status" :label="__('Work Status')" placeholder="Select status">
                <option value="employed">Employed</option>
                <option value="unemployed">Unemployed</option>
                <option value="student">Student</option>
                <option value="freelancer">Freelancer</option>
            </flux:select>
            <flux:input wire:model="work" :label="__('Work / Job Title')" type="text" />
            <flux:input wire:model="experience_year" :label="__('Experience (Years)')" type="number" min="0" />
        </div>

        <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

        <!-- Location -->
        <div class="space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Location') }}</h2>
            <flux:select wire:model="country_code" :label="__('Country / Currency Region')" placeholder="Select country">
                <option value="NG">Nigeria (₦)</option>
                <option value="US">United States ($)</option>
                <option value="GB">United Kingdom (£)</option>
                <option value="EU">European Union (€)</option>
                <option value="GH">Ghana (₵)</option>
                <option value="KE">Kenya (KSh)</option>
                <option value="ZA">South Africa (R)</option>
                <option value="CA">Canada (C$)</option>
                <option value="AU">Australia (A$)</option>
                <option value="NL">Netherlands (€)</option>
            </flux:select>
            <p class="text-xs text-zinc-500 italic">
                {{ __('This determines the currency symbol shown on your posts.') }}
            </p>
        </div>

        <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

        <!-- Bio -->
        <div class="space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Bio') }}</h2>
            <flux:textarea wire:model="bio" :label="__('About you')" rows="4" placeholder="Tell us about yourself..." />
        </div>

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !auth()->user()->hasVerifiedEmail())
            <div>
                <flux:text class="mt-4">
                    {{ __('Your email address is unverified.') }}

                    <flux:link class="text-sm cursor-pointer"
                        wire:click.prevent="resendVerificationNotification">
                        {{ __('Click here to re-send the verification email.') }}
                    </flux:link>
                </flux:text>

                @if (session('status') === 'verification-link-sent')
                    <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </flux:text>
                @endif
            </div>
        @endif

        <div class="space-y-3 pt-2 border-t border-zinc-100 dark:border-zinc-800">
            <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                {{ __('Save') }}
            </flux:button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>

    <!-- Notifications -->
    <div class="border-t border-zinc-100 dark:border-zinc-800 pt-8">
        <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ __('Notifications') }}</h3>
        <p class="mt-1 text-sm text-zinc-500">
            {{ __('Stay updated with real-time push notifications for messages and inquiries.') }}
        </p>

        <div class="mt-5">
            <div x-data="{
                isGranted: false,
                isSubscribed: false,
                isChecking: true,
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
                async checkSubscription() {
                    this.isChecking = true;
                    try {
                        this.isGranted = window.Notification ? Notification.permission === 'granted' : false;
                        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                            this.isSubscribed = this.isGranted;
                            return;
                        }
                        const reg = await this.getRegistration().catch(() => null);
                        if (!reg) { this.isSubscribed = false; return; }
                        const sub = await reg.pushManager.getSubscription();
                        this.isSubscribed = !!sub;
                        this.isGranted = Notification.permission === 'granted';
                    } catch (e) {
                        this.isGranted = window.Notification ? Notification.permission === 'granted' : false;
                    } finally {
                        this.isChecking = false;
                    }
                },
                async subscribe() {
                    try {
                        this.vapidKey = (document.querySelector('meta[name=vapid-public-key]')?.content || '').trim() || this.vapidKey;
                        const pushSupported = ('serviceWorker' in navigator) && ('PushManager' in window);
                        if (!pushSupported) {
                            const perm = await Notification.requestPermission();
                            this.isGranted = perm === 'granted';
                            this.isSubscribed = this.isGranted;
                            if (this.isGranted && window.Flux) Flux.toast({ variant: 'success', heading: 'Success', text: 'In-tab notifications enabled!' });
                            return;
                        }
                        let perm = Notification.permission;
                        if (perm !== 'granted') perm = await Notification.requestPermission();
                        this.isGranted = perm === 'granted';
                        if (perm !== 'granted') {
                            if (window.Flux) Flux.toast({ variant: 'error', heading: 'Permission denied', text: 'Please allow notifications in browser settings.' });
                            return;
                        }
                        if (!this.vapidKey) {
                            this.vapidKey = (document.querySelector('meta[name=vapid-public-key]')?.content || '').trim();
                            if (!this.vapidKey) {
                                if (window.Flux) Flux.toast({ variant: 'error', heading: 'Error', text: 'VAPID key not configured.' });
                                return;
                            }
                        }
                        const reg = await this.getRegistration();
                        let sub = await reg.pushManager.getSubscription();
                        if (!sub) {
                            sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.urlBase64ToUint8Array(this.vapidKey) });
                            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                            const res = await fetch('/push-subscriptions', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                                credentials: 'same-origin',
                                body: JSON.stringify(sub.toJSON()),
                            });
                            if (!res.ok) throw new Error('Failed to save subscription');
                            localStorage.setItem('webpush_vapid_key', this.vapidKey);
                        }
                        this.isSubscribed = true;
                        window.dispatchEvent(new CustomEvent('push-subscription-changed', { detail: { subscribed: true } }));
                        if (window.Flux) Flux.toast({ variant: 'success', heading: 'Success', text: 'You will now receive real-time notifications!' });
                        try {
                            const csrf2 = document.querySelector('meta[name=csrf-token]')?.content || '';
                            await fetch('/push-subscriptions/test-webpush', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf2, 'Accept': 'application/json' }, credentials: 'same-origin' });
                        } catch {}
                    } catch (e) {
                        console.error('Subscribe error:', e.name, e.message, e);
                        if (e.name === 'AbortError' && window.Flux) Flux.toast({ variant: 'error', heading: 'Push service error', text: 'Clear site data and reload, or try Chrome (not Brave incognito). FCM may be blocked by VPN.' });
                        else if (window.Flux) Flux.toast({ variant: 'error', heading: 'Error', text: e.message || 'Failed to subscribe.' });
                    }
                }
            }" x-init="checkSubscription(); window.addEventListener('push-subscription-changed', e => { isSubscribed = e.detail.subscribed; if(e.detail.subscribed) isGranted = true; })"
                class="flex flex-col gap-3">
                <div x-show="!isChecking" x-cloak class="space-y-3">
                    <template x-if="!isGranted && !isSubscribed">
                        <div class="space-y-3">
                            <flux:button @click="subscribe()" icon="bell" variant="outline" class="w-full">
                                {{ __('Enable Browser Notifications') }}
                            </flux:button>
                            <p class="text-xs text-zinc-500">{{ __('Allow notifications to stay updated.') }}</p>
                        </div>
                    </template>
                    <template x-if="isGranted && !isSubscribed">
                        <div class="space-y-3">
                            <div class="h-2 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full w-1/2 bg-green-600 dark:bg-green-500 rounded-full"></div>
                            </div>
                            <div class="flex items-center gap-2 text-sm font-semibold text-green-700 dark:text-green-400">
                                <flux:icon name="check-circle" variant="solid" class="size-4" />
                                <span>{{ __('Permission granted — not yet subscribed') }}</span>
                            </div>
                            <flux:button @click="subscribe()" icon="bell" variant="primary" class="w-full">
                                {{ __('Subscribe to Push Notifications') }}
                            </flux:button>
                            <p class="text-xs text-zinc-500">{{ __('Tap to complete push subscription.') }}</p>
                        </div>
                    </template>
                    <template x-if="isGranted && isSubscribed">
                        <div class="space-y-3">
                            <div class="h-2 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full w-full bg-green-600 dark:bg-green-500 rounded-full"></div>
                            </div>
                            <flux:button icon="bell" variant="outline" class="!bg-green-50 !text-green-700 !border-green-100 w-full" disabled>
                                {{ __('Notifications are active') }}
                            </flux:button>
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-green-50/50 border border-green-100 sm:border-0 sm:bg-transparent">
                                <flux:icon name="check-circle" variant="solid" class="size-4 text-green-600" />
                                <span class="text-sm text-green-700 font-semibold uppercase tracking-tight">{{ __('Live') }}</span>
                                <span class="text-xs text-green-600 ml-auto">{{ __('100% enabled') }}</span>
                            </div>
                        </div>
                    </template>
                    <template x-if="!isGranted && isSubscribed">
                        <div class="space-y-3">
                            <div class="h-2 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full w-full bg-green-600 dark:bg-green-500 rounded-full"></div>
                            </div>
                            <flux:button icon="bell" variant="outline" class="!bg-green-50 !text-green-700 !border-green-100 w-full" disabled>
                                {{ __('Notifications are active') }}
                            </flux:button>
                        </div>
                    </template>
                </div>
                <div x-show="isChecking" class="h-10 w-full bg-zinc-100 dark:bg-zinc-800 animate-pulse rounded-xl"></div>
            </div>
            {{-- OneSignal version DISABLED
            <div x-data="{
                isSubscribed: false,
                async checkSubscription() {
                    window.OneSignalDeferred = window.OneSignalDeferred || [];
                    OneSignalDeferred.push(async (OneSignal) => { this.isSubscribed = OneSignal.Notifications.permission; });
                },
                async subscribe() {
                    window.OneSignalDeferred = window.OneSignalDeferred || [];
                    OneSignalDeferred.push(async (OneSignal) => {
                        await OneSignal.Notifications.requestPermission();
                        this.isSubscribed = OneSignal.Notifications.permission;
                    });
                }
            }"></div>
            --}}
        </div>
    </div>

    <livewire:settings.delete-user-form />
</x-settings.layout>
