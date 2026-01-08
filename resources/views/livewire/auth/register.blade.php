<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-black">
                {{ __('Create your Allsers account') }}
            </h1>
            <p class="text-sm text-zinc-500">
                {{ __('Find or offer services instantly') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" x-data="{
            password: '',
            latitude: null,
            longitude: null,
            address: '',
            status: 'pending', // pending, active, denied
            requestLocation() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.latitude = position.coords.latitude;
                            this.longitude = position.coords.longitude;
                            this.status = 'active';
                            this.resolveAddress();
                        },
                        (error) => {
                            console.error('Error getting location:', error);
                            this.status = 'denied';
                        }
                    );
                }
            },
            async resolveAddress() {
                if (!this.latitude || !this.longitude) return;
                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${this.latitude}&lon=${this.longitude}&zoom=18`);
                    const data = await response.json();
                    this.address = data.display_name;
                } catch (e) {
                    this.address = 'Location saved';
                }
            }
        }">
            @csrf

            <!-- Better Location Request UI -->
            <div class="rounded-2xl border-2 border-[var(--color-brand-purple)] bg-[#f7f1fe]/30 p-4 transition-all"
                x-show="status !== 'active'">
                <div class="flex items-start gap-3">
                    <div
                        class="size-10 rounded-full bg-[var(--color-brand-purple)] flex items-center justify-center shrink-0">
                        <flux:icon name="map-pin" class="size-5 text-white" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-sm text-black">{{ __('Discover artisans near you') }}</h3>
                        <p class="mt-1 text-xs text-zinc-500 leading-relaxed">
                            {{ __('By sharing your location, we can automatically show you the best service providers just streets away from your doorstep.') }}
                        </p>
                        <div class="mt-4 flex gap-2">
                            <button type="button" @click="requestLocation()"
                                class="bg-[var(--color-brand-purple)] text-white text-xs font-bold px-4 py-2 rounded-xl hover:opacity-90 transition-opacity">
                                {{ __('Find experts near me') }}
                            </button>
                            <button type="button" @click="status = 'denied'"
                                class="text-zinc-500 text-xs font-bold px-4 py-2 rounded-xl hover:bg-zinc-100 transition-colors">
                                {{ __('Not now') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 rounded-xl bg-green-50 px-4 py-3 border border-green-100"
                x-show="status === 'active'" x-transition>
                <div class="size-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-xs font-medium text-green-700">
                    {{ __('Location fixed:') }} <span class="font-bold" x-text="address || 'Coordinates saved'"></span>
                </span>
            </div>
            <input type="hidden" name="latitude" x-model="latitude">
            <input type="hidden" name="longitude" x-model="longitude">

            <!-- Name -->
            <flux:input name="name" :label="__('Full Name')" :value="old('name')" type="text" required autofocus
                autocomplete="name" :placeholder="__('Full name')" />

            <!-- Username -->
            <flux:input name="username" :label="__('Username')" :value="old('username')" type="text" required
                autocomplete="username" :placeholder="__('Username')" />

            <!-- Email Address -->
            <flux:input name="email" :label="__('Email address')" :value="old('email')" type="email" required
                autocomplete="email" placeholder="email@example.com" />

            <!-- Role Selection -->
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-black">{{ __('I want to...') }}</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label
                        class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none hover:border-[var(--color-brand-purple)] has-[:checked]:border-[var(--color-brand-purple)] has-[:checked]:bg-[#f7f1fe] transition-all">
                        <input type="radio" name="role" value="guest" class="sr-only" checked>
                        <span class="flex flex-col">
                            <span
                                class="block text-sm font-medium text-black">{{ __('Find a Service (guests)') }}</span>
                            <span
                                class="mt-1 flex items-center text-xs text-zinc-500">{{ __('I\'m looking to hire') }}</span>
                        </span>
                    </label>
                    <label
                        class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none hover:border-[var(--color-brand-purple)] has-[:checked]:border-[var(--color-brand-purple)] has-[:checked]:bg-[#f7f1fe] transition-all">
                        <input type="radio" name="role" value="artisan" class="sr-only">
                        <span class="flex flex-col">
                            <span
                                class="block text-sm font-medium text-black">{{ __('Offer Services (artisans)') }}</span>
                            <span
                                class="mt-1 flex items-center text-xs text-zinc-500">{{ __('I\'m a service provider') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-2">
                <flux:input name="password" :label="__('Password')" type="password" required autocomplete="new-password"
                    :placeholder="__('Password')" viewable x-model="password" />

                <!-- Password Strength -->
                <div class="h-1 w-full bg-zinc-100 rounded-full overflow-hidden" x-show="password.length > 0"
                    x-transition>
                    <div class="h-full bg-[var(--color-brand-purple)] transition-all duration-500"
                        :style="'width: ' + Math.min(password.length * 12, 100) + '%'"></div>
                </div>
                <p class="text-xs text-zinc-500" x-show="password.length > 0 && password.length < 8">
                    {{ __('Password should be at least 8 characters') }}
                </p>
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary"
                    class="w-full bg-[var(--color-brand-purple)] hover:bg-[var(--color-brand-purple)]/90"
                    data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" class="text-[var(--color-brand-purple)] hover:underline" wire:navigate>
                {{ __('Log in') }}
            </flux:link>
        </div>
    </div>
</x-layouts.auth>
