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
                init() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                this.latitude = position.coords.latitude;
                                this.longitude = position.coords.longitude;
                            },
                            (error) => {
                                console.error('Error getting location:', error);
                            }
                        );
                    }
                }
            }" x-init="init()">
            @csrf

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
                            <span class="block text-sm font-medium text-black">{{ __('Find a Service') }}</span>
                            <span
                                class="mt-1 flex items-center text-xs text-zinc-500">{{ __('I\'m looking to hire') }}</span>
                        </span>
                    </label>
                    <label
                        class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none hover:border-[var(--color-brand-purple)] has-[:checked]:border-[var(--color-brand-purple)] has-[:checked]:bg-[#f7f1fe] transition-all">
                        <input type="radio" name="role" value="artisan" class="sr-only">
                        <span class="flex flex-col">
                            <span class="block text-sm font-medium text-black">{{ __('Offer Services') }}</span>
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