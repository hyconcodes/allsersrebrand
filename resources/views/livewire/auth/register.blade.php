<x-layouts.auth>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1 text-center">
            <h1 class="text-xl font-bold tracking-tight text-black">
                {{ __('Create Account') }}
            </h1>
            <p class="text-xs text-zinc-500">
                {{ __('Find or offer services instantly') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-4" x-data="{
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

            <!-- Compact Location Request -->
            <button type="button" @click="requestLocation()" x-show="status !== 'active'"
                class="flex items-center justify-between w-full rounded-lg border border-dashed border-[var(--color-brand-purple)]/40 bg-[#f7f1fe]/50 px-3 py-2 text-left hover:bg-[#f7f1fe] transition-colors group">
                <div class="flex items-center gap-2">
                    <flux:icon name="map-pin" class="size-3.5 text-[var(--color-brand-purple)]" />
                    <span class="text-[10px] font-medium text-zinc-600 group-hover:text-[var(--color-brand-purple)]">
                        {{ __('Enable location to find artisans near you') }}
                    </span>
                </div>
                <span class="text-[10px] font-bold text-[var(--color-brand-purple)]">{{ __('Enable') }}</span>
            </button>

            <div class="flex items-center gap-2 rounded-xl bg-green-50 px-3 py-2 border border-green-100"
                x-show="status === 'active'" x-transition>
                <div class="size-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-xs font-medium text-green-700 truncate">
                    <span class="font-bold" x-text="address || 'Location Verified'"></span>
                </span>
            </div>
            <input type="hidden" name="latitude" x-model="latitude">
            <input type="hidden" name="longitude" x-model="longitude">

            <!-- Name & Username Grid -->
            <div class="grid grid-cols-2 gap-3">
                <flux:input name="name" :label="__('Full Name')" :value="old('name')" type="text" required
                    autofocus autocomplete="name" placeholder="John Doe" class="!py-1.5" />

                <flux:input name="username" :label="__('Username')" :value="old('username')" type="text" required
                    autocomplete="username" placeholder="@johndoe" class="!py-1.5" />
            </div>

            <!-- Email Address -->
            <flux:input name="email" :label="__('Email')" :value="old('email')" type="email" required
                autocomplete="email" placeholder="email@example.com" class="!py-1.5" />

            <!-- Compact Role Selection -->
            <div class="grid grid-cols-2 gap-3">
                <label
                    class="relative flex items-center gap-3 cursor-pointer rounded-xl border bg-white px-3 py-2 shadow-sm focus:outline-none hover:border-[var(--color-brand-purple)] has-[:checked]:border-[var(--color-brand-purple)] has-[:checked]:bg-[#f7f1fe] transition-all">
                    <input type="radio" name="role" value="guest"
                        class="size-4 text-[var(--color-brand-purple)] border-gray-300 focus:ring-[var(--color-brand-purple)]"
                        checked>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-black">{{ __('I want to Hire') }}</span>
                    </div>
                </label>
                <label
                    class="relative flex items-center gap-3 cursor-pointer rounded-xl border bg-white px-3 py-2 shadow-sm focus:outline-none hover:border-[var(--color-brand-purple)] has-[:checked]:border-[var(--color-brand-purple)] has-[:checked]:bg-[#f7f1fe] transition-all">
                    <input type="radio" name="role" value="artisan"
                        class="size-4 text-[var(--color-brand-purple)] border-gray-300 focus:ring-[var(--color-brand-purple)]">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-black">{{ __('I want to Work') }}</span>
                    </div>
                </label>
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-1">
                <flux:input name="password" :label="__('Password')" type="password" required autocomplete="new-password"
                    viewable x-model="password" class="!py-1.5" />

                <!-- Password Strength -->
                <div class="h-0.5 w-full bg-zinc-100 rounded-full overflow-hidden mt-1" x-show="password.length > 0">
                    <div class="h-full bg-[var(--color-brand-purple)] transition-all duration-500"
                        :style="'width: ' + Math.min(password.length * 12, 100) + '%'"></div>
                </div>
            </div>

            <p class="text-[10px] text-zinc-400 text-center leading-tight">
                {{ __('By joining, you agree to our') }}
                <a href="{{ route('terms') }}" class="text-zinc-600 hover:underline"
                    target="_blank">{{ __('Terms') }}</a>
                {{ __('&') }}
                <a href="{{ route('privacy') }}" class="text-zinc-600 hover:underline"
                    target="_blank">{{ __('Privacy Policy') }}</a>.
            </p>

            <div class="text-center text-xs text-zinc-600">
                <span>{{ __('Have an account?') }}</span>
                <flux:link :href="route('login')" class="text-[var(--color-brand-purple)] font-bold hover:underline"
                    wire:navigate>
                    {{ __('Log in') }}
                </flux:link>
            </div>
            <flux:button type="submit" variant="primary"
                class="w-full bg-[var(--color-brand-purple)] hover:bg-[var(--color-brand-purple)]/90 h-10 text-sm"
                data-test="register-user-button">
                {{ __('Create Account') }}
            </flux:button>
        </form>

    </div>
</x-layouts.auth>
