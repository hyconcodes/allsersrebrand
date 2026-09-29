<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col items-center text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-lg shadow-zinc-900/10 mb-3">
                <flux:icon name="arrow-right-end-on-rectangle" class="size-6" />
            </div>
            <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">
                {{ __('Welcome back') }}
            </h1>
            <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400">
                {{ __('Log in to continue to Allsers') }}
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4 flex flex-col gap-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-7 items-center justify-center rounded-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                        <flux:icon name="envelope" class="size-3.5" />
                    </span>
                    <h2 class="text-[11px] font-extrabold tracking-[0.14em] uppercase text-zinc-700 dark:text-zinc-300">{{ __('Your credentials') }}</h2>
                </div>

                <flux:input name="email" :label="__('Email address')" :value="old('email')" type="email" required autofocus autocomplete="email" placeholder="you@example.com" />

                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Password') }}</span>
                        @if (Route::has('password.request'))
                            <flux:link :href="route('password.request')" class="text-xs font-bold text-[var(--color-brand-purple)] hover:underline underline-offset-4" wire:navigate>{{ __('Forgot password?') }}</flux:link>
                        @endif
                    </div>
                    <flux:input name="password" type="password" required autocomplete="current-password" placeholder="••••••••" viewable label="" />
                </div>
            </div>

            <!-- Remember me — high visibility checkbox -->
            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 py-3.5 transition-colors hover:border-zinc-300 dark:hover:border-zinc-700 has-[:checked]:border-[var(--color-brand-purple)]/30 has-[:checked]:bg-[var(--color-brand-purple)]/[0.06]">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="size-[18px] shrink-0 rounded-[6px] border-2 border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 text-[var(--color-brand-purple)] focus:ring-2 focus:ring-[var(--color-brand-purple)]/20 focus:ring-offset-0">
                <span class="flex flex-col">
                    <span class="text-[13px] font-bold leading-none text-zinc-900 dark:text-white">{{ __('Remember me') }}</span>
                    <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Stay signed in on this device') }}</span>
                </span>
                <flux:icon name="shield-check" class="ml-auto size-4 text-zinc-400 dark:text-zinc-500" />
            </label>

            <flux:button variant="primary" type="submit"
                class="w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0"
                data-test="login-button" x-bind:disabled="submitting">
                <span x-show="!submitting" class="flex items-center justify-center gap-2">
                    {{ __('Log in') }}
                    <flux:icon name="arrow-right" class="size-4" />
                </span>
                <span x-show="submitting" x-cloak class="flex items-center justify-center gap-2">
                    <span class="size-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
                    {{ __('Logging in…') }}
                </span>
            </flux:button>

            @if (Route::has('register'))
                <p class="text-center text-[13px] font-medium text-zinc-600 dark:text-zinc-400">
                    {{ __('Don’t have an account?') }}
                    <flux:link :href="route('register')" class="font-extrabold text-[var(--color-brand-purple)] hover:underline underline-offset-4" wire:navigate>{{ __('Create account') }}</flux:link>
                </p>
            @endif

            <p class="text-center text-[11px] leading-relaxed font-medium text-zinc-400 dark:text-zinc-500">
                {{ __('We’ll ask for your location after you log in — only if you want to find pros nearby.') }}
            </p>
        </form>
    </div>
</x-layouts.auth>
