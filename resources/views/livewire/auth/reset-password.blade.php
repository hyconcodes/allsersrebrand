<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20 mb-3">
                <flux:icon name="lock-closed" class="size-6" />
            </div>
            <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">
                {{ __('Reset your password') }}
            </h1>
            <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400">
                {{ __('Choose a strong new password for your account.') }}
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5" x-data="{ password: '' }">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4 flex flex-col gap-4">
                <flux:input name="email" value="{{ request('email') }}" :label="__('Email address')" type="email" required autocomplete="email" />

                <div class="flex flex-col gap-2">
                    <flux:input name="password" :label="__('New password')" type="password" required autocomplete="new-password" placeholder="At least 8 characters" viewable x-model="password" />
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" x-show="password.length > 0" x-cloak x-transition>
                        <div class="h-full rounded-full transition-all duration-500" :class="password.length < 8 ? 'bg-amber-500' : password.length < 12 ? 'bg-[var(--color-brand-purple)]' : 'bg-emerald-500'" :style="'width: ' + Math.min(password.length * 8.5, 100) + '%'"></div>
                    </div>
                    <p class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400" x-show="password.length > 0 && password.length < 8">{{ __('Password should be at least 8 characters.') }}</p>
                    <p class="text-[11px] font-bold" :class="password.length >= 12 ? 'text-emerald-600' : 'text-[var(--color-brand-purple)]'" x-show="password.length >= 8" x-text="password.length < 12 ? '{{ __('Good — keep going for a stronger password.') }}' : '{{ __('Strong password!') }}'"></p>
                </div>

                <flux:input name="password_confirmation" :label="__('Confirm new password')" type="password" required autocomplete="new-password" placeholder="Repeat your new password" viewable />
            </div>

            <flux:button type="submit" variant="primary" class="w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all" data-test="reset-password-button">
                <span class="flex items-center justify-center gap-2">
                    {{ __('Reset password') }}
                    <flux:icon name="check" class="size-4" />
                </span>
            </flux:button>

            <p class="text-center text-[13px] font-medium text-zinc-600 dark:text-zinc-400">
                <flux:link :href="route('login')" class="font-bold text-zinc-700 dark:text-zinc-300 hover:text-[var(--color-brand-purple)] hover:underline underline-offset-4" wire:navigate>{{ __('Back to log in') }}</flux:link>
            </p>
        </form>
    </div>
</x-layouts.auth>
