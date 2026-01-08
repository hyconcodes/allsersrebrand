<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-black">
                {{ __('Welcome back to Allsers') }}
            </h1>
            <p class="text-sm text-zinc-500">
                {{ __('Quick access to your services') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6" x-data="{
            latitude: null,
            longitude: null,
            status: 'detecting', // detecting, active, denied
            init() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.latitude = position.coords.latitude;
                            this.longitude = position.coords.longitude;
                            this.status = 'active';
                        },
                        (error) => {
                            console.error('Location error:', error);
                            this.status = 'denied';
                        }, { timeout: 5000 }
                    );
                } else {
                    this.status = 'unsupported';
                }
            }
        }"
            x-init="init()">
            @csrf

            <input type="hidden" name="latitude" x-model="latitude">
            <input type="hidden" name="longitude" x-model="longitude">

            <!-- Location Status (Subtle) -->
            <div class="flex items-center gap-2 px-1 text-[10px]" x-show="status === 'active'" x-transition>
                <div class="size-1.5 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-zinc-500 font-medium lowercase tracking-tight">{{ __('Location sync active') }}</span>
            </div>
            <div class="flex items-center gap-2 px-1 text-[10px]"
                x-show="status === 'detecting' && navigator.geolocation" x-transition>
                <div class="size-1.5 bg-[var(--color-brand-purple)] rounded-full animate-bounce"></div>
                <span
                    class="text-zinc-500 font-medium lowercase tracking-tight">{{ __('Detecting proximity...') }}</span>
            </div>

            <!-- Email Address -->
            <flux:input name="email" :label="__('Email address')" :value="old('email')" type="email" required
                autofocus autocomplete="email" placeholder="email@example.com" />

            <!-- Password -->
            <div class="relative">
                <flux:input name="password" :label="__('Password')" type="password" required
                    autocomplete="current-password" :placeholder="__('Password')" viewable />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0 text-[var(--color-brand-purple)]"
                        :href="route('password.request')" wire:navigate>
                        {{ __('Forgot password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit"
                    class="w-full bg-[var(--color-brand-purple)] hover:bg-[var(--color-brand-purple)]/90"
                    data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" class="text-[var(--color-brand-purple)] hover:underline"
                    wire:navigate>
                    {{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts.auth>
